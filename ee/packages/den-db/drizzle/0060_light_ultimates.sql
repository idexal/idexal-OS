ALTER TABLE `codemode_run` ADD `script_input` mediumtext;--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `script_input_digest` varchar(80);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `input_schema_digest` varchar(80);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `result_markdown` mediumtext;--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `result_digest` varchar(80);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `output_schema_digest` varchar(80);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `renderer_version` varchar(64);--> statement-breakpoint
ALTER TABLE `codemode_run` ADD `artifact_content_deleted_at` timestamp(3);--> statement-breakpoint
CREATE INDEX `codemode_run_artifact_history` ON `codemode_run` (`config_object_id`,`finished_at`);