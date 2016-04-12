CREATE TABLE IF NOT EXISTS `sked_event_members` (
    `sked_event_id` INT UNSIGNED COMMENT '',
    `member_id` INT UNSIGNED COMMENT '',
    `owner` TINYINT(1) UNSIGNED COMMENT '',
    `lead_time` INT NOT NULL DEFAULT 0 COMMENT 'Number of minutes relative to starts_at to send reminder (+ is before, - is after)',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`sked_event_id`, `member_id`),
    FOREIGN KEY (`sked_event_id`) REFERENCES `sked_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
