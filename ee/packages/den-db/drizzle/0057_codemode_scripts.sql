CREATE TABLE `codemode_run` (
	`id` varchar(64) NOT NULL,
	`organization_id` varchar(64) NOT NULL,
	`org_membership_id` varchar(64),
	`source` varchar(255) NOT NULL,
	`code_digest` varchar(80) NOT NULL,
	`status` enum('succeeded','failed') NOT NULL,
	`error_kind` varchar(64),
	`error_message` text,
	`tool_calls` json,
	`tool_call_count` int NOT NULL DEFAULT 0,
	`duration_ms` int NOT NULL DEFAULT 0,
	`started_at` timestamp(3) NOT NULL,
	`finished_at` timestamp(3) NOT NULL,
	`created_at` timestamp(3) NOT NULL DEFAULT (now()),
	CONSTRAINT `codemode_run_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `config_object` MODIFY COLUMN `object_type` enum('skill','agent','command','tool','mcp','hook','context','custom','script') NOT NULL;--> statement-breakpoint
ALTER TABLE `connector_mapping` MODIFY COLUMN `object_type` enum('skill','agent','command','tool','mcp','hook','context','custom','script') NOT NULL;--> statement-breakpoint
CREATE INDEX `codemode_run_org_created` ON `codemode_run` (`organization_id`,`created_at`);