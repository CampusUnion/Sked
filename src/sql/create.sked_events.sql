CREATE TABLE `sked_events` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `label` VARCHAR(255) NOT NULL COMMENT 'Label for the event',
    `description` TEXT DEFAULT NULL COMMENT 'Optional detailed description of the event',
    `starts_at` DATETIME DEFAULT NULL COMMENT 'First occurance of the event',
    `lead_time` INT NOT NULL DEFAULT 0 COMMENT 'Number of minutes relative to starts_at to send reminder (+ is before, - is after)',
    `duration` SMALLINT DEFAULT 60 COMMENT 'Length of the event in minutes',
    `Mon` TINYINT(1) DEFAULT 0,
    `Tue` TINYINT(1) DEFAULT 0,
    `Wed` TINYINT(1) DEFAULT 0,
    `Thu` TINYINT(1) DEFAULT 0,
    `Fri` TINYINT(1) DEFAULT 0,
    `Sat` TINYINT(1) DEFAULT 0,
    `Sun` TINYINT(1) DEFAULT 0,
    `interval` VARCHAR(7) DEFAULT 'Once' COMMENT 'Options are "Once", "1" (daily), "7" (weekly), and "Monthly"',
    `frequency` TINYINT DEFAULT NULL COMMENT 'Number of intervals between events (1 is every, 2 is every-other, etc.)',
    `ends_at` DATETIME DEFAULT NULL COMMENT 'Last event in the series (NOT the ending time of the first event). NULL if once or indefinite',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY (`starts_at`),
    KEY (`Mon`),
    KEY (`Tue`),
    KEY (`Wed`),
    KEY (`Thu`),
    KEY (`Fri`),
    KEY (`Sat`),
    KEY (`Sun`),
    KEY (`interval`),
    KEY (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
