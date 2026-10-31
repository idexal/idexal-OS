# Minimal old-state fixture: the published resources have skip_destroy=false.
terraform {
  required_providers {
    aws = { source = "hashicorp/aws", version = "= 5.100.0" }
  }
}
resource "aws_ecs_task_definition" "api" {
  family                = "idexal-os-den-api"
  skip_destroy          = false
  container_definitions = jsonencode([{ name = "den-api", image = "example.test/api:legacy", essential = true }])
}
resource "aws_ecs_task_definition" "web" {
  family                = "idexal-os-den-web"
  skip_destroy          = false
  container_definitions = jsonencode([{ name = "den-web", image = "example.test/web:legacy", essential = true }])
}
