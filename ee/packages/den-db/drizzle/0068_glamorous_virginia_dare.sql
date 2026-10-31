ALTER TABLE `scim_group_member` ADD `organization_id` varchar(64);--> statement-breakpoint
ALTER TABLE `scim_group_member` ADD `provider_id` varchar(255);--> statement-breakpoint
CREATE INDEX `scim_group_member_provider_org` ON `scim_group_member` (`provider_id`,`organization_id`);