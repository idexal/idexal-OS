ALTER TABLE `automation_revision` MODIFY COLUMN `execution_target` enum('desktop','cloud') NOT NULL DEFAULT 'desktop';--> statement-breakpoint
ALTER TABLE `automation_run` MODIFY COLUMN `execution_target` enum('desktop','cloud') NOT NULL DEFAULT 'desktop';--> statement-breakpoint
ALTER TABLE `automation_revision` ADD `action` mediumtext;--> statement-breakpoint
ALTER TABLE `automation_run` ADD `codemode_receipt_id` varchar(64);--> statement-breakpoint
ALTER TABLE `automation_run` ADD `validated_result` mediumtext;--> statement-breakpoint
ALTER TABLE `automation` ADD `latest_successful_run_id` varchar(64);--> statement-breakpoint
ALTER TABLE `automation` ADD `latest_successful_result` mediumtext;--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `automation_run_id` varchar(64);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `plugin_id` varchar(64);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `config_object_id` varchar(64);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `config_object_version_id` varchar(64);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `validated_result` mediumtext;--> statement-breakpoint
CREATE INDEX `codemode_run_automation` ON `codemode_run` (`automation_run_id`);