# Isolated mocked state transitions; no real rollout provisioner is targeted.
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
  mock_resource "aws_service_discovery_private_dns_namespace" {
    defaults = { arn = "arn:aws:servicediscovery:us-east-1:123456789012:namespace/ns-abc" }
  }
  mock_resource "aws_secretsmanager_secret" {
    defaults = { arn = "arn:aws:secretsmanager:us-east-1:123456789012:secret:idexalos" }
  }
  mock_resource "aws_ecs_task_definition" {
    defaults = { arn = "arn:aws:ecs:us-east-1:123456789012:task-definition/idexalos:1" }
  }
}
mock_provider "random" {}
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

run "legacy_false_skip_destroy_state" {
  command   = apply
  state_key = "legacy"
  module { source = "./tests/legacy" }
  assert {
    condition     = !aws_ecs_task_definition.api.skip_destroy && !aws_ecs_task_definition.web.skip_destroy
    error_message = "The upgrade starts with real old-state false skip_destroy values."
  }
}

run "upgrade_forgets_legacy_and_creates_retained_ca_revisions" {
  command   = plan
  state_key = "legacy"
  module { source = "./modules/idexal-os-aws-ecs" }
  assert {
    condition     = aws_ecs_task_definition.api_ca.skip_destroy && aws_ecs_task_definition.web_ca.skip_destroy
    error_message = "New addresses must retain revisions from their first creation."
  }
  assert {
    condition     = aws_ecs_service.api.wait_for_steady_state && aws_ecs_service.web.wait_for_steady_state
    error_message = "The CA service phase must finish before strict secret promotion."
  }
}

run "create_ca_definition_state" {
  command   = apply
  state_key = "ca"
  module { source = "./modules/idexal-os-aws-ecs" }
  plan_options {
    target = [aws_ecs_task_definition.api_ca, aws_ecs_task_definition.web_ca]
  }
  assert {
    condition     = aws_ecs_task_definition.api_ca.skip_destroy && aws_ecs_task_definition.web_ca.skip_destroy
    error_message = "Created state must contain true skip_destroy, not a replacement-time edit."
  }
}

run "future_update_replaces_only_retained_ca_definitions" {
  command   = plan
  state_key = "ca"
  module { source = "./modules/idexal-os-aws-ecs" }
  variables { tls_task_revision = "second-update" }
  # Mock providers do not execute the AWS SDK's ForceNew planner. Explicitly
  # exercise its replacement shape; the live second update proves real ForceNew.
  plan_options {
    replace = [aws_ecs_task_definition.api_ca, aws_ecs_task_definition.web_ca]
  }
  assert {
    condition     = aws_ecs_task_definition.api_ca.skip_destroy && aws_ecs_task_definition.web_ca.skip_destroy && var.tls_task_revision == "second-update"
    error_message = "A later definition-changing update must keep skip_destroy true."
  }
}
