# Exercise the managed platform directly without adding outputs to its interface.
mock_provider "aws" {
  mock_data "aws_iam_policy_document" {
    defaults = { json = "{\"Version\":\"2012-10-17\",\"Statement\":[]}" }
  }
  mock_resource "aws_cloudwatch_log_group" {
    defaults = { arn = "arn:aws:logs:us-east-1:123456789012:log-group:/ecs/test" }
  }
  mock_resource "aws_ecs_cluster" {
    defaults = { arn = "arn:aws:ecs:us-east-1:123456789012:cluster/idexal-os-den" }
  }
  mock_resource "aws_lb" {
    defaults = { arn = "arn:aws:elasticloadbalancing:us-east-1:123456789012:loadbalancer/app/idexalos/abc" }
  }
  mock_resource "aws_lb_target_group" {
    defaults = { arn = "arn:aws:elasticloadbalancing:us-east-1:123456789012:targetgroup/idexalos/abc" }
  }
  mock_resource "aws_lb_listener" {
    defaults = { arn = "arn:aws:elasticloadbalancing:us-east-1:123456789012:listener/app/idexalos/abc/def" }
  }
  mock_resource "aws_iam_role" {
    defaults = { arn = "arn:aws:iam::123456789012:role/idexalos" }
  }
  mock_resource "aws_service_discovery_service" {
    defaults = { arn = "arn:aws:servicediscovery:us-east-1:123456789012:service/srv-abc" }
  }
  mock_resource "aws_secretsmanager_secret" {
    defaults = { arn = "arn:aws:secretsmanager:us-east-1:123456789012:secret:idexalos" }
  }
  mock_resource "aws_service_discovery_private_dns_namespace" {
    defaults = { arn = "arn:aws:servicediscovery:us-east-1:123456789012:namespace/ns-abc" }
  }
  mock_resource "aws_ecs_task_definition" {
    defaults = { arn = "arn:aws:ecs:us-east-1:123456789012:task-definition/idexalos:1" }
  }
}
mock_provider "random" {
}
mock_provider "http" {
  mock_data "http" {
    defaults = { status_code = 200, response_body = "-----BEGIN CERTIFICATE-----test" }
  }
}

variables {
  idexal_os_version    = "0.18.58"
  vpc_id              = "vpc-test"
  alb_subnet_ids      = ["subnet-a", "subnet-b"]
  service_subnet_ids  = ["subnet-a", "subnet-b"]
  database_subnet_ids = ["subnet-a", "subnet-b"]
  owner_emails        = ["admin@example.com"]
  domain_name         = "idexal-os.example.com"
  certificate_arn     = "arn:aws:acm:us-east-1:123456789012:certificate/test"
}

run "strict_default_and_all_db_clients_trust_rds" {
  command = apply
  module { source = "./modules/idexal-os-aws-ecs" }
  # Mocked unit apply targets definitions only: no real rollout provisioner.
  plan_options {
    target = [aws_ecs_task_definition.api_ca, aws_ecs_task_definition.web_ca, aws_secretsmanager_secret_version.app]
  }
  override_data {
    target = data.http.rds_ca
    values = { status_code = 200, response_body = "-----BEGIN CERTIFICATE-----test" }
  }
  assert {
    condition     = var.database.tls_query == "sslaccept=strict"
    error_message = "Managed RDS must verify its certificate by default."
  }
  assert {
    condition     = data.http.rds_ca.url == "https://truststore.pki.rds.amazonaws.com/us-east-1/us-east-1-bundle.pem" && data.http.rds_ca.insecure == false
    error_message = "Fetch the regional public RDS bundle over verified HTTPS."
  }
  assert {
    condition = alltrue([for command in [
      local.api_containers[0].command[0], local.api_containers[1].command[0], local.web_containers[0].command[0]
      ] : startswith(command, local.tls_setup) &&
      strcontains(command, "export NODE_EXTRA_CA_CERTS=/tmp/idexal-os-db-ca.pem;") &&
      strcontains(command, "node /tmp/idexal-os-db-tls-check.cjs; exec ") &&
      strcontains(command, base64encode("-----BEGIN CERTIFICATE-----test"))
    ])
    error_message = "Migration, API and web must write the CA and export trust before starting Node."
  }
  assert {
    condition = (
      length(local.api_containers) == 2 &&
      local.api_containers[0].name == "migrate" &&
      local.api_containers[1].dependsOn == [{ containerName = "migrate", condition = "SUCCESS" }]
    )
    error_message = "This exception must not adopt the direct module's migration lifecycle."
  }
  assert {
    condition     = alltrue([for entrypoint in [local.api_containers[0].entryPoint, local.api_containers[1].entryPoint, local.web_containers[0].entryPoint] : entrypoint == ["/bin/sh", "-ec"]])
    error_message = "Every database client must fail closed on its TLS startup check."
  }
  assert {
    condition = (
      endswith(local.initial_secret_values.DATABASE_URL, "?sslaccept=accept") &&
      endswith(local.secret_values.DATABASE_URL, "?sslaccept=strict") &&
      alltrue([for key, value in local.secret_values : key == "DATABASE_URL" || local.initial_secret_values[key] == value])
    )
    error_message = "Initial and strict versions must preserve every key/credential except the TLS query."
  }
}

run "reject_accept" {
  command = plan
  module { source = "./modules/idexal-os-aws-ecs" }
  variables { database = { tls_query = "sslaccept=accept" } }
  expect_failures = [var.database]
}

run "reject_conflicting_query" {
  command = plan
  module { source = "./modules/idexal-os-aws-ecs" }
  variables { database = { tls_query = "sslaccept=strict&sslaccept=accept" } }
  expect_failures = [var.database]
}

run "reject_failed_ca_fetch" {
  command = plan
  module { source = "./modules/idexal-os-aws-ecs" }
  override_data {
    target = data.http.rds_ca
    values = { status_code = 503, response_body = "-----BEGIN CERTIFICATE-----test" }
  }
  expect_failures = [data.http.rds_ca]
}

run "reject_non_pem_ca" {
  command = plan
  module { source = "./modules/idexal-os-aws-ecs" }
  override_data {
    target = data.http.rds_ca
    values = { status_code = 200, response_body = "not a certificate" }
  }
  expect_failures = [data.http.rds_ca]
}
