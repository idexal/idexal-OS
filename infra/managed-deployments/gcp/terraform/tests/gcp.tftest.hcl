mock_provider "google" {}
mock_provider "random" {}

override_data {
  target = data.google_project.target
  values = { number = "123456789012" }
}
override_data {
  target = data.google_dns_managed_zone.target
  values = { dns_name = "example.com.", visibility = "public" }
}
variables {
  deployment_id        = "12345678-1234-1234-1234-123456789012"
  domain_name          = "idexal-os.example.com"
  owner_email          = "admin@example.com"
  idexal_os_version     = "0.18.0"
  control_plane_origin = "https://api.example.com"
  size                 = "small"
  project_id           = "test-project"
  project_number       = "123456789012"
  region               = "us-central1"
  dns_zone_name        = "public-zone"
  images = {
    den_api = "us-central1-docker.pkg.dev/test-project/ow-123456781234/den_api@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
    den_web = "us-central1-docker.pkg.dev/test-project/ow-123456781234/den_web@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
    health  = "us-central1-docker.pkg.dev/test-project/ow-123456781234/health@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc"
  }
}
run "contract_and_security" {
  command = plan
  assert {
    condition     = output.web_url == "https://idexal-os.example.com" && output.api_url == "https://api.idexal-os.example.com" && output.setup_url == "https://idexal-os.example.com/setup"
    error_message = "GCP must implement the same provider-neutral URL contract."
  }
  assert {
    condition     = google_sql_database_instance.main.deletion_protection && google_sql_database_instance.main.settings[0].deletion_protection_enabled && google_sql_database_instance.main.settings[0].backup_configuration[0].enabled
    error_message = "Database protection and backups must remain enabled."
  }
  assert {
    condition     = alltrue([for service in google_cloud_run_v2_service.services : service.ingress == "INGRESS_TRAFFIC_INTERNAL_LOAD_BALANCER"])
    error_message = "Services must not expose a public bypass around the HTTPS load balancer."
  }
  assert {
    condition     = google_cloud_run_v2_job.health.template[0].template[0].service_account == "ow-123456781234-health@test-project.iam.gserviceaccount.com" && google_cloud_run_v2_job.migrate.template[0].template[0].max_retries == 0
    error_message = "Health must use the dedicated identity and migrations must not auto-retry."
  }
  assert {
    condition     = google_cloud_scheduler_job.health.http_target[0].oauth_token[0].service_account_email != google_cloud_run_v2_job.health.template[0].template[0].service_account
    error_message = "Scheduler invocation and health report identities must be separate."
  }
  assert {
    condition     = google_cloud_run_v2_service.services["den_web"].template[0].service_account == "ow-123456781234-web@test-project.iam.gserviceaccount.com" && google_cloud_run_v2_service.services["den_api"].template[0].service_account != google_cloud_run_v2_service.services["den_web"].template[0].service_account
    error_message = "Web must not inherit the API's secret/database identity."
  }
  assert {
    condition     = length(google_cloud_run_v2_service.services["den_web"].template[0].volumes) == 0 && alltrue([for env in google_cloud_run_v2_service.services["den_web"].template[0].containers[0].env : length(env.value_source) == 0])
    error_message = "Web must have no Cloud SQL mount or secret environment values."
  }
  assert {
    condition     = alltrue([for service in google_cloud_run_v2_service.services : length(service.traffic) == 1 && service.traffic[0].type == "TRAFFIC_TARGET_ALLOCATION_TYPE_LATEST" && service.traffic[0].percent == 100])
    error_message = "A completed rollout must route all traffic to the latest revision."
  }
  assert {
    condition     = length(google_sql_database_instance.main.settings[0].ip_configuration[0].authorized_networks) == 0
    error_message = "Cloud SQL must use the authenticated connector, not public authorized networks."
  }
}
run "wrong_project_is_blocked" {
  command = plan
  variables { project_number = "999999999999" }
  expect_failures = [terraform_data.target]
}
run "unrelated_dns_is_blocked" {
  command = plan
  variables { domain_name = "idexal-os.unrelated.test" }
  expect_failures = [terraform_data.target]
}
run "foreign_image_is_blocked" {
  command = plan
  variables {
    images = {
      den_api = "us-central1-docker.pkg.dev/foreign-project/ow-123456781234/den_api@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
      den_web = "us-central1-docker.pkg.dev/test-project/ow-123456781234/den_web@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
      health  = "us-central1-docker.pkg.dev/test-project/ow-123456781234/health@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc"
    }
  }
  expect_failures = [terraform_data.target]
}
