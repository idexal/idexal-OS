CREATE TABLE `scim_group_role_grant` (
	`id` varchar(64) NOT NULL,
	`group_id` varchar(64) NOT NULL,
	`provider_id` varchar(255) NOT NULL,
	`organization_id` varchar(64) NOT NULL,
	`user_id` varchar(64) NOT NULL,
	`role` varchar(255) NOT NULL,
	`role_grant_key` varchar(768) NOT NULL,
	`is_role_projected` boolean NOT NULL,
	`created_at` timestamp(3) NOT NULL DEFAULT (now()),
	CONSTRAINT `scim_group_role_grant_id` PRIMARY KEY(`id`),
	CONSTRAINT `scim_group_role_grant_role_grant_key` UNIQUE(`role_grant_key`)
);
--> statement-breakpoint
CREATE TABLE `scim_group_role` (
	`id` varchar(64) NOT NULL,
	`group_id` varchar(64) NOT NULL,
	`role` varchar(255) NOT NULL,
	`role_key` varchar(768) NOT NULL,
	`created_at` timestamp(3) NOT NULL DEFAULT (now()),
	CONSTRAINT `scim_group_role_id` PRIMARY KEY(`id`),
	CONSTRAINT `scim_group_role_role_key` UNIQUE(`role_key`)
);
--> statement-breakpoint
ALTER TABLE `scim_group_member` ADD `membership_key` varchar(768);--> statement-breakpoint
ALTER TABLE `scim_group` ADD `scim_group_id` varchar(64);--> statement-breakpoint
UPDATE `scim_group` SET `scim_group_id` = `id` WHERE `scim_group_id` IS NULL;--> statement-breakpoint
ALTER TABLE `scim_group` MODIFY COLUMN `scim_group_id` varchar(64) NOT NULL;--> statement-breakpoint
ALTER TABLE `scim_group` ADD `external_id_key` varchar(768);--> statement-breakpoint
ALTER TABLE `scim_group_member` ADD CONSTRAINT `scim_group_member_membership_key` UNIQUE(`membership_key`);--> statement-breakpoint
ALTER TABLE `scim_group` ADD CONSTRAINT `scim_group_scim_group_id` UNIQUE(`scim_group_id`);--> statement-breakpoint
ALTER TABLE `scim_group` ADD CONSTRAINT `scim_group_external_id_key` UNIQUE(`external_id_key`);--> statement-breakpoint
CREATE INDEX `scim_group_role_grant_group_id` ON `scim_group_role_grant` (`group_id`);--> statement-breakpoint
CREATE INDEX `scim_group_role_grant_provider_org` ON `scim_group_role_grant` (`provider_id`,`organization_id`);--> statement-breakpoint
CREATE INDEX `scim_group_role_grant_user_id` ON `scim_group_role_grant` (`user_id`);--> statement-breakpoint
CREATE INDEX `scim_group_role_group_id` ON `scim_group_role` (`group_id`);
