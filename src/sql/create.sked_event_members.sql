CREATE TABLE `sked_event_members` (
    `sked_event_id` INT UNSIGNED COMMENT '',
    `member_id` INT UNSIGNED COMMENT '',
    `owner` TINYINT(1) UNSIGNED COMMENT '',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT TIMESTAMP,

    PRIMARY KEY (`sked_event_id`, `participant_id`),
    FOREIGN KEY (`sked_event_id`) REFERENCES `sked_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
