ALTER TABLE `team_member` MODIFY COLUMN `org_membership_id` varchar(64);--> statement-breakpoint
ALTER TABLE `team_member` ADD `user_id` varchar(64);--> statement-breakpoint
ALTER TABLE `team_member` ADD `membership_key` varchar(64);--> statement-breakpoint
ALTER TABLE `team` ADD `member_count` int DEFAULT 0 NOT NULL;--> statement-breakpoint
UPDATE `team_member` `tm` INNER JOIN `member` `m` ON `tm`.`org_membership_id` = `m`.`id` SET `tm`.`user_id` = `m`.`user_id` WHERE `tm`.`user_id` IS NULL AND `m`.`user_id` IS NOT NULL;--> statement-breakpoint
UPDATE `team_member` SET `membership_key` = REPLACE(REPLACE(TRIM(TRAILING '=' FROM REPLACE(TO_BASE64(UNHEX(SHA2(CONCAT('["', `team_id`, '","', `user_id`, '"]'), 256))), '\n', '')), '+', '-'), '/', '_') WHERE `membership_key` IS NULL AND `user_id` IS NOT NULL;--> statement-breakpoint
UPDATE `team` SET `member_count` = (SELECT COUNT(*) FROM `team_member` WHERE `team_member`.`team_id` = `team`.`id`);--> statement-breakpoint
ALTER TABLE `team_member` ADD CONSTRAINT `team_member_membership_key` UNIQUE(`membership_key`);--> statement-breakpoint
CREATE INDEX `team_member_user_id` ON `team_member` (`user_id`);
