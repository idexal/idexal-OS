# Dedicated-project GCP root. Bootstrap owns identities, API enablement and
# retained state; Terraform never needs project IAM administrator privileges.
terraform {
  required_version = "= 1.16.5"
  backend "gcs" {}
  required_providers {
    google = { source = "hashicorp/google", version = "= 6.49.0" }
    random = { source = "hashicorp/random", version = "= 3.7.2" }
  }
}

variable "deployment_id" { type = string }
variable "domain_name" { type = string }
variable "owner_email" { type = string }
variable "idexal_os_version" { type = string }
variable "control_plane_origin" { type = string }
variable "size" {
  type    = string
  default = "small"
}
variable "project_id" { type = string }
variable "project_number" { type = string }
variable "region" { type = string }
variable "dns_zone_name" { type = string }
variable "images" {
  type = object({ den_api = string, den_web = string, health = string })
  validation {
    condition     = alltrue([for image in values(var.images) : can(regex("^[a-z0-9-]+-docker\\.pkg\\.dev/[a-z0-9-]+/[a-z0-9-]+/[a-z0-9_]+@sha256:[a-f0-9]{64}$", image))])
    error_message = "Cloud Run images must be customer Artifact Registry images pinned by digest."
  }
}

module "contract" {
  source               = "../../contract"
  deployment_id        = var.deployment_id
  domain_name          = var.domain_name
  owner_email          = var.owner_email
  idexal_os_version     = var.idexal_os_version
  control_plane_origin = var.control_plane_origin
  size                 = var.size
}

locals {
  name          = module.contract.name
  labels        = module.contract.labels
  runtime_sa    = "${local.name}-runtime@${var.project_id}.iam.gserviceaccount.com"
  web_sa        = "${local.name}-web@${var.project_id}.iam.gserviceaccount.com"
  health_sa     = "${local.name}-health@${var.project_id}.iam.gserviceaccount.com"
  scheduler_sa  = "${local.name}-scheduler@${var.project_id}.iam.gserviceaccount.com"
  web_url       = module.contract.web_url
  api_url       = module.contract.api_url
  service_names = { den_api = "${local.name}-api", den_web = "${local.name}-web" }
  api_env = {
    NODE_ENV                       = "production", CI = "true", DB_MODE = "mysql", IDEXAL_DEV_MODE = "0"
    DEN_ORG_MODE                   = "single_org", DEN_SINGLE_ORG_NAME = "idexal Os", DEN_SINGLE_ORG_SLUG = "idexalos"
    DEN_SINGLE_ORG_OWNER_EMAILS    = var.owner_email, DEN_SINGLE_ORG_ALLOW_PUBLIC_SIGNUP = "false"
    DEN_REQUIRE_EMAIL_VERIFICATION = "false", DEN_BASE_URL = local.web_url, BETTER_AUTH_URL = local.web_url
    DEN_API_PUBLIC_URL             = local.api_url, DEN_MCP_RESOURCE_URL = "${local.api_url}/mcp"
    DEN_MCP_CLAIM_NAMESPACE        = local.web_url, DEN_BETTER_AUTH_TRUSTED_ORIGINS = local.web_url
    DEN_WEB_APP_HOSTS              = var.domain_name, DEN_BOOTSTRAP_ADMIN_EMAILS = var.owner_email
    DEN_CORS_ORIGINS               = "${local.web_url},${local.api_url}", CORS_ORIGINS = "${local.web_url},${local.api_url}"
    DEN_AUTOMATIONS_ENABLED        = "false", DEN_AUTOMATIONS_RUNTIME_ENABLED = "false", PROVISIONER_MODE = "stub"
  }
  web_env = {
    NODE_ENV        = "production", CI = "true", DEN_BASE_URL = local.web_url, DEN_WEB_PUBLIC_ORIGIN = local.web_url
    DEN_AUTH_ORIGIN = local.web_url, DEN_WEB_IDEXAL_AUTH_CALLBACK_URL = local.web_url
    DEN_API_BASE    = local.api_url, DEN_API_PUBLIC_URL = local.api_url
  }
  secret_env = {
    DATABASE_URL                     = "database-url", BETTER_AUTH_SECRET = "auth", DEN_DB_ENCRYPTION_KEY = "encryption"
    DEN_INITIAL_ADMIN_BOOTSTRAP_CODE = "bootstrap"
  }
  secret_keys = toset(["database-url", "auth", "encryption", "bootstrap"])
  secret_values = {
    database-url = "mysql://idexalos:${urlencode(random_password.values["database"].result)}@localhost/idexal_os_den?socket=${urlencode("/cloudsql/${google_sql_database_instance.main.connection_name}")}"
    auth         = random_password.values["auth"].result
    encryption   = random_password.values["encryption"].result
    bootstrap    = random_password.values["bootstrap"].result
  }
}
provider "google" {
  project                     = var.project_id
  region                      = var.region
  impersonate_service_account = "${module.contract.name}-runner@${var.project_id}.iam.gserviceaccount.com"
  default_labels              = local.labels
}

data "google_project" "target" { project_id = var.project_id }
data "google_dns_managed_zone" "target" { name = var.dns_zone_name }
# Hard preconditions, not advisory Terraform check blocks.
resource "terraform_data" "target" {
  lifecycle {
    precondition {
      condition     = tostring(data.google_project.target.number) == var.project_number
      error_message = "Wrong GCP project number."
    }
    precondition {
      condition     = data.google_dns_managed_zone.target.visibility == "public" && ("${var.domain_name}." == data.google_dns_managed_zone.target.dns_name || endswith("${var.domain_name}.", ".${data.google_dns_managed_zone.target.dns_name}"))
      error_message = "The domain must belong to the selected public DNS zone."
    }
    precondition {
      condition     = alltrue([for image in values(var.images) : startswith(image, "${var.region}-docker.pkg.dev/${var.project_id}/${local.name}/")])
      error_message = "Images must belong to this deployment's customer repository."
    }
  }
}

resource "random_password" "values" {
  for_each = toset(["database", "auth", "encryption", "bootstrap"])
  length   = 48
  special  = false
  lifecycle { prevent_destroy = true }
}
resource "google_sql_database_instance" "main" {
  name                = "${local.name}-mysql"
  region              = var.region
  database_version    = "MYSQL_8_0"
  deletion_protection = true
  settings {
    tier                        = "db-custom-1-3840"
    availability_type           = "ZONAL"
    disk_size                   = 20
    disk_autoresize             = true
    deletion_protection_enabled = true
    user_labels                 = local.labels
    ip_configuration { ipv4_enabled = true }
    backup_configuration {
      enabled            = true
      binary_log_enabled = true
      start_time         = "03:00"
      backup_retention_settings {
        retained_backups = 7
        retention_unit   = "COUNT"
      }
    }
  }
  depends_on = [terraform_data.target]
  lifecycle { prevent_destroy = true }
}
resource "google_sql_database" "main" {
  name     = "idexal_os_den"
  instance = google_sql_database_instance.main.name
  lifecycle { prevent_destroy = true }
}
resource "google_sql_user" "main" {
  name     = "idexalos"
  instance = google_sql_database_instance.main.name
  password = random_password.values["database"].result
  lifecycle { prevent_destroy = true }
}
resource "google_secret_manager_secret" "values" {
  for_each  = local.secret_keys
  secret_id = "${local.name}-${each.key}"
  labels    = local.labels
  replication {
    auto {}
  }
  lifecycle { prevent_destroy = true }
}
resource "google_secret_manager_secret_version" "values" {
  for_each    = local.secret_keys
  secret      = google_secret_manager_secret.values[each.key].id
  secret_data = local.secret_values[each.key]
  lifecycle { prevent_destroy = true }
}
resource "google_secret_manager_secret_iam_member" "runtime" {
  for_each  = local.secret_keys
  secret_id = google_secret_manager_secret.values[each.key].id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${local.runtime_sa}"
}

resource "google_cloud_run_v2_job" "migrate" {
  name     = "${local.name}-migrate"
  location = var.region
  template {
    template {
      service_account = local.runtime_sa
      max_retries     = 0
      timeout         = "1800s"
      containers {
        image   = var.images.den_api
        command = ["node"]
        args    = ["/app/ee/packages/den-db/dist/scripts/bootstrap.js"]
        resources { limits = { cpu = "2", memory = "2Gi" } }
        env {
          name  = "DB_MODE"
          value = "mysql"
        }
        dynamic "env" {
          for_each = { DATABASE_URL = "database-url", DEN_DB_ENCRYPTION_KEY = "encryption" }
          content {
            name = env.key
            value_source {
              secret_key_ref {
                secret  = google_secret_manager_secret.values[env.value].secret_id
                version = google_secret_manager_secret_version.values[env.value].version
              }
            }
          }
        }
        volume_mounts {
          name       = "cloudsql"
          mount_path = "/cloudsql"
        }
      }
      volumes {
        name = "cloudsql"
        cloud_sql_instance { instances = [google_sql_database_instance.main.connection_name] }
      }
    }
  }
  depends_on = [google_sql_database.main, google_sql_user.main, google_secret_manager_secret_iam_member.runtime]
}

resource "google_cloud_run_v2_service" "services" {
  for_each            = local.service_names
  name                = each.value
  location            = var.region
  ingress             = "INGRESS_TRAFFIC_INTERNAL_LOAD_BALANCER"
  deletion_protection = true
  traffic {
    type    = "TRAFFIC_TARGET_ALLOCATION_TYPE_LATEST"
    percent = 100
  }
  template {
    # Web proxies authenticated browser requests but never needs database or
    # Secret Manager access. A compromised web revision must not inherit API IAM.
    service_account = each.key == "den_api" ? local.runtime_sa : local.web_sa
    timeout         = "300s"
    scaling {
      min_instance_count = 1
      max_instance_count = 10
    }
    containers {
      image = var.images[each.key]
      ports { container_port = each.key == "den_api" ? 8788 : 3005 }
      resources { limits = { cpu = each.key == "den_api" ? "2" : "1", memory = each.key == "den_api" ? "2Gi" : "1Gi" } }
      dynamic "env" {
        for_each = each.key == "den_api" ? local.api_env : local.web_env
        content {
          name  = env.key
          value = env.value
        }
      }
      dynamic "env" {
        for_each = each.key == "den_api" ? local.secret_env : {}
        content {
          name = env.key
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.values[env.value].secret_id
              version = google_secret_manager_secret_version.values[env.value].version
            }
          }
        }
      }
      dynamic "volume_mounts" {
        for_each = each.key == "den_api" ? [1] : []
        content {
          name       = "cloudsql"
          mount_path = "/cloudsql"
        }
      }
    }
    dynamic "volumes" {
      for_each = each.key == "den_api" ? [1] : []
      content {
        name = "cloudsql"
        cloud_sql_instance { instances = [google_sql_database_instance.main.connection_name] }
      }
    }
  }
  depends_on = [google_cloud_run_v2_job.migrate]
}
resource "google_cloud_run_v2_service_iam_member" "public" {
  for_each = local.service_names
  name     = google_cloud_run_v2_service.services[each.key].name
  location = var.region
  role     = "roles/run.invoker"
  member   = "allUsers"
}
resource "google_compute_region_network_endpoint_group" "services" {
  for_each              = local.service_names
  name                  = "${each.value}-neg"
  region                = var.region
  network_endpoint_type = "SERVERLESS"
  cloud_run { service = google_cloud_run_v2_service.services[each.key].name }
}
resource "google_compute_backend_service" "services" {
  for_each              = local.service_names
  name                  = "${each.value}-backend"
  load_balancing_scheme = "EXTERNAL_MANAGED"
  protocol              = "HTTP"
  backend { group = google_compute_region_network_endpoint_group.services[each.key].id }
}
resource "google_compute_global_address" "main" { name = "${local.name}-ip" }
resource "google_compute_managed_ssl_certificate" "main" {
  name = "${local.name}-tls"
  managed { domains = [var.domain_name, "api.${var.domain_name}"] }
}
resource "google_compute_url_map" "main" {
  name            = "${local.name}-routes"
  default_service = google_compute_backend_service.services["den_web"].id
  host_rule {
    hosts        = ["api.${var.domain_name}"]
    path_matcher = "api"
  }
  path_matcher {
    name            = "api"
    default_service = google_compute_backend_service.services["den_api"].id
  }
}
resource "google_compute_target_https_proxy" "main" {
  name             = "${local.name}-https"
  url_map          = google_compute_url_map.main.id
  ssl_certificates = [google_compute_managed_ssl_certificate.main.id]
}
resource "google_compute_global_forwarding_rule" "https" {
  name                  = "${local.name}-https"
  target                = google_compute_target_https_proxy.main.id
  ip_address            = google_compute_global_address.main.address
  port_range            = "443"
  load_balancing_scheme = "EXTERNAL_MANAGED"
}
resource "google_dns_record_set" "services" {
  for_each     = toset([var.domain_name, "api.${var.domain_name}"])
  managed_zone = var.dns_zone_name
  name         = "${each.value}."
  type         = "A"
  ttl          = 300
  rrdatas      = [google_compute_global_address.main.address]
}

resource "google_cloud_run_v2_job" "health" {
  name     = "${local.name}-health"
  location = var.region
  template {
    template {
      service_account = local.health_sa
      max_retries     = 0
      timeout         = "240s"
      containers {
        image   = var.images.health
        command = ["python3", "-c"]
        args    = [file("${path.module}/../health/health_agent.py")]
        resources { limits = { cpu = "1", memory = "512Mi" } }
        dynamic "env" {
          for_each = {
            DEPLOYMENT_ID    = var.deployment_id, CONTROL_PLANE_ORIGIN = var.control_plane_origin
            PROJECT_ID       = var.project_id, REGION = var.region, API_URL = local.api_url, WEB_URL = local.web_url
            SERVICE_NAMES    = jsonencode(local.service_names), DATABASE_INSTANCE = google_sql_database_instance.main.name
            CERTIFICATE_NAME = google_compute_managed_ssl_certificate.main.name, IDEXAL_VERSION = var.idexal_os_version
            EXPECTED_IMAGES  = jsonencode({ den_api = var.images.den_api, den_web = var.images.den_web })
          }
          content {
            name  = env.key
            value = env.value
          }
        }
      }
    }
  }
}
resource "google_cloud_run_v2_job_iam_member" "scheduler" {
  name     = google_cloud_run_v2_job.health.name
  location = var.region
  role     = "roles/run.invoker"
  member   = "serviceAccount:${local.scheduler_sa}"
}
resource "google_cloud_scheduler_job" "health" {
  name             = "${local.name}-health"
  region           = var.region
  schedule         = "*/${module.contract.health_interval_minutes} * * * *"
  time_zone        = "Etc/UTC"
  attempt_deadline = "30s"
  http_target {
    uri         = "https://run.googleapis.com/v2/projects/${var.project_id}/locations/${var.region}/jobs/${google_cloud_run_v2_job.health.name}:run"
    http_method = "POST"
    body        = base64encode("{}")
    headers     = { "Content-Type" = "application/json" }
    oauth_token {
      service_account_email = local.scheduler_sa
      scope                 = "https://www.googleapis.com/auth/cloud-platform"
    }
  }
  depends_on = [google_cloud_run_v2_job_iam_member.scheduler]
}

output "web_url" { value = local.web_url }
output "api_url" { value = local.api_url }
output "setup_url" { value = "${local.web_url}/setup" }
output "health_agent" { value = google_cloud_run_v2_job.health.name }
output "service_names" { value = local.service_names }
