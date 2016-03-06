CREATE TABLE `sked_event_tags` (
    `sked_event_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`sked_event_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
