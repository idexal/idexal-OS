CREATE TABLE `remote_mcp_app` (
	`config_object_id` varchar(64) NOT NULL,
	`organization_id` varchar(64) NOT NULL,
	`plugin_id` varchar(64) NOT NULL,
	`active_version_id` varchar(64),
	`source_url` varchar(2048) NOT NULL,
	`resolved_source_url` varchar(2048) NOT NULL,
	`status` enum('active','retired') NOT NULL DEFAULT 'active',
	`created_at` timestamp(3) NOT NULL DEFAULT (now()),
	`updated_at` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
	`retired_at` timestamp(3),
	CONSTRAINT `remote_mcp_app_config_object_id` PRIMARY KEY(`config_object_id`),
	CONSTRAINT `remote_mcp_app_plugin_id` UNIQUE(`plugin_id`)
);
--> statement-breakpoint
ALTER TABLE `config_object` MODIFY COLUMN `object_type` enum('skill','agent','command','tool','mcp','hook','context','custom','script','app') NOT NULL;--> statement-breakpoint
ALTER TABLE `connector_mapping` MODIFY COLUMN `object_type` enum('skill','agent','command','tool','mcp','hook','context','custom','script','app') NOT NULL;--> statement-breakpoint
CREATE INDEX `remote_mcp_app_organization_id` ON `remote_mcp_app` (`organization_id`);--> statement-breakpoint
CREATE INDEX `remote_mcp_app_active_version_id` ON `remote_mcp_app` (`active_version_id`);--> statement-breakpoint
CREATE INDEX `remote_mcp_app_status` ON `remote_mcp_app` (`status`);