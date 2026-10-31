CREATE TABLE `program_agent_selection` (
	`organization_id` varchar(64) NOT NULL,
	`org_membership_id` varchar(64) NOT NULL,
	`program_id` varchar(64) NOT NULL,
	`selected_at` timestamp(3) NOT NULL DEFAULT (now()),
	`created_at` timestamp(3) NOT NULL DEFAULT (now()),
	`updated_at` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
	CONSTRAINT `program_agent_selection_org_member` UNIQUE(`organization_id`,`org_membership_id`)
);
--> statement-breakpoint
CREATE INDEX `program_agent_selection_program` ON `program_agent_selection` (`organization_id`,`program_id`);