CREATE TABLE IF NOT EXISTS `sked_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY(`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
