# TLS trust and ordered rollout for the managed pre-contract implementation.
data "http" "rds_ca" {
  url      = "https://truststore.pki.rds.amazonaws.com/${local.region}/${local.region}-bundle.pem"
  insecure = false
  lifecycle {
    postcondition {
      condition     = self.status_code == 200 && strcontains(self.response_body, "-----BEGIN CERTIFICATE-----")
      error_message = "The RDS CA bundle must be fetched successfully over verified HTTPS."
    }
  }
}

variable "tls_task_revision" {
  description = "Non-secret task-definition revision marker for controlled update proofs; leave empty for normal installs."
  type        = string
  default     = ""
  validation {
    condition     = can(regex("^[a-z0-9-]{0,40}$", var.tls_task_revision))
    error_message = "The task revision marker must be at most 40 lowercase letters, digits or hyphens."
  }
}

locals {
  tls_deployment_environment = {
    AWS_REGION  = local.region
    TLS_CLUSTER = local.cluster_arn
    TLS_TASK_DEFINITIONS = jsonencode({
      (aws_ecs_service.api.name) = aws_ecs_task_definition.api_ca.arn
      (aws_ecs_service.web.name) = aws_ecs_task_definition.web_ca.arn
    })
  }
  initial_secret_values = merge(local.secret_values, {
    DATABASE_URL = replace(local.database_url, "?${var.database.tls_query}", "?sslaccept=accept")
  })
  db_environment_list = concat(local.environment_list, [{ name = "IDEXAL_MANAGED_TASK_REVISION", value = var.tls_task_revision }])
  # This checks actual environment input without printing any URL or credential.
  # A first CA-only deployment can still use the initial accept-mode version;
  # only the final deployment's strict marker is evidence for strict mode.
  tls_check = <<-JS
    const fs = require("node:fs");
    const { X509Certificate } = require("node:crypto");
    try {
      const url = new URL(process.env.DATABASE_URL);
      if (url.protocol !== "mysql:") throw new Error();
      const mode = url.search === "?sslaccept=accept" ? "bootstrap-accept" :
        ["?sslaccept=strict", "?sslmode=verify-full"].includes(url.search) ? "strict" : null;
      if (!mode) throw new Error();
      const certificates = fs.readFileSync(process.env.NODE_EXTRA_CA_CERTS, "utf8")
        .match(/-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/g);
      if (!certificates || !certificates.length) throw new Error();
      for (const certificate of certificates) new X509Certificate(certificate);
      console.log("IDEXAL_MANAGED_DB_TLS mode=" + mode + " ca=validated");
    } catch {
      console.error("Managed database TLS configuration rejected.");
      process.exit(1);
    }
  JS
  tls_setup = "printf '%s' '${base64encode(data.http.rds_ca.response_body)}' | base64 -d > /tmp/idexal-os-db-ca.pem; export NODE_EXTRA_CA_CERTS=/tmp/idexal-os-db-ca.pem; printf '%s' '${base64encode(local.tls_check)}' | base64 -d > /tmp/idexal-os-db-tls-check.cjs; node /tmp/idexal-os-db-tls-check.cjs; "
}

# Both services must have completed their CA-enabled deployment before the
# current secret can become strict. Their circuit-breaker rollback target is
# therefore also CA-enabled before the final forced deployment begins.
resource "aws_secretsmanager_secret_version" "tls" {
  secret_id     = aws_secretsmanager_secret.app.id
  secret_string = jsonencode(local.secret_values)
  depends_on    = [terraform_data.ca_ready]
}

# An explicit completed-deployment check precedes promotion, in addition to
# provider steady-state waits: rollback must already target a CA revision.
resource "terraform_data" "ca_ready" {
  triggers_replace = [aws_ecs_task_definition.api_ca.arn, aws_ecs_task_definition.web_ca.arn]
  provisioner "local-exec" {
    command     = "python3 \"${path.module}/../../../runner/runner.py\" tls-ready"
    environment = local.tls_deployment_environment
  }
}

# Runs inside the existing installer, using only its existing ECS UpdateService
# and Describe permissions. Failed waits fail apply; no successful run receipt
# is emitted until both services have completed the strict deployment.
resource "terraform_data" "strict_redeploy" {
  triggers_replace = [aws_secretsmanager_secret_version.tls.version_id]
  provisioner "local-exec" {
    command     = "python3 \"${path.module}/../../../runner/runner.py\" tls-redeploy"
    environment = local.tls_deployment_environment
  }
}
