CREATE TABLE IF NOT EXISTS `#__pluggen_blueprints` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(255) NOT NULL DEFAULT '',
	`type_id` varchar(64) NOT NULL DEFAULT '',
	`model` mediumtext COMMENT 'The plugin model, as JSON',
	`published` tinyint NOT NULL DEFAULT 1,
	`access` int unsigned NOT NULL DEFAULT 1,
	`ordering` int NOT NULL DEFAULT 0,
	`checked_out` int unsigned DEFAULT NULL,
	`checked_out_time` datetime DEFAULT NULL,
	`created` datetime DEFAULT NULL,
	`created_by` int unsigned NOT NULL DEFAULT 0,
	`modified` datetime DEFAULT NULL,
	`modified_by` int unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_state` (`published`),
	KEY `idx_type` (`type_id`),
	KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
