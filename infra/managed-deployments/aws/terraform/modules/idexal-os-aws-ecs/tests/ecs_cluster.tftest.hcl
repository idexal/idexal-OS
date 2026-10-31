# Offline checks (mocked AWS provider): terraform init && terraform test

mock_provider "aws" {
  mock_data "aws_iam_policy_document" {
    defaults = { json = "{\"Version\":\"2012-10-17\",\"Statement\":[]}" }
  }
  mock_resource "aws_cloudwatch_log_group" {
    defaults = { arn = "arn:aws:logs:us-east-1:123456789012:log-group:idexalos" }
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
  mock_resource "aws_secretsmanager_secret" {
    defaults = { arn = "arn:aws:secretsmanager:us-east-1:123456789012:secret:idexalos" }
  }
  mock_resource "aws_service_discovery_service" {
    defaults = { arn = "arn:aws:servicediscovery:us-east-1:123456789012:service/srv-abc" }
  }
  mock_resource "aws_ecs_task_definition" {
    defaults = { arn = "arn:aws:ecs:us-east-1:123456789012:task-definition/idexalos:1" }
  }
}

mock_provider "random" {}

variables {
  idexal_os_version    = "0.0.0-test"
  vpc_id              = "vpc-123"
  alb_subnet_ids      = ["subnet-a", "subnet-b"]
  service_subnet_ids  = ["subnet-a", "subnet-b"]
  database_subnet_ids = ["subnet-c", "subnet-d"]
  owner_emails        = ["admin@example.com"]
  domain_name         = "idexal-os.example.com"
  certificate_arn     = "arn:aws:acm:us-east-1:123456789012:certificate/abc"
}

run "creates_cluster_by_default" {
  command = apply

  assert {
    condition     = length(aws_ecs_cluster.this) == 1
    error_message = "Expected the module to create a cluster when ecs_cluster_arn is empty."
  }
  assert {
    condition     = aws_ecs_service.api.cluster == aws_ecs_cluster.this[0].arn && aws_ecs_service.web.cluster == aws_ecs_cluster.this[0].arn
    error_message = "Services must run in the created cluster."
  }
  assert {
    condition     = output.cluster_name == "idexal-os-den"
    error_message = "cluster_name should be the created cluster."
  }
}

run "uses_existing_cluster" {
  command = apply

  variables {
    ecs_cluster_arn = "arn:aws:ecs:us-east-1:123456789012:cluster/platform"
  }

  assert {
    condition     = length(aws_ecs_cluster.this) == 0
    error_message = "No cluster should be created when ecs_cluster_arn is set."
  }
  assert {
    condition     = aws_ecs_service.api.cluster == var.ecs_cluster_arn && aws_ecs_service.web.cluster == var.ecs_cluster_arn
    error_message = "Services must run in the existing cluster."
  }
  assert {
    condition     = output.cluster_name == "platform" && output.cluster_arn == var.ecs_cluster_arn
    error_message = "Outputs should describe the existing cluster."
  }
}

run "rejects_non_arn" {
  command = plan

  variables {
    ecs_cluster_arn = "platform"
  }

  expect_failures = [var.ecs_cluster_arn]
}

run "prefixes_service_names_in_a_shared_cluster" {
  command = apply

  variables {
    ecs_cluster_arn     = "arn:aws:ecs:us-east-1:123456789012:cluster/platform"
    service_name_prefix = "ow-1234-"
  }

  assert {
    condition     = aws_ecs_service.api.name == "ow-1234-den-api" && aws_ecs_service.web.name == "ow-1234-den-web"
    error_message = "service_name_prefix should prefix every ECS service name."
  }
}
