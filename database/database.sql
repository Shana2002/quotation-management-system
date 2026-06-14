-- =====================================================================
--  Quotation Management System (QMS) — Database Schema + Seed
-- ---------------------------------------------------------------------
--  Engine : InnoDB        Charset : utf8mb4 (utf8mb4_unicode_ci)
--  Target : MySQL 8 and MariaDB 10.4+ (portable subset of SQL)
--
--  Import:
--    mysql -u root -p < database/database.sql
--  or via phpMyAdmin (import this file).
--
--  Default logins (CHANGE IMMEDIATELY in production):
--    Admin     : admin@qms.local      / Admin@123
--    Manager   : manager@qms.local    / Manager@123
--    Executive : executive@qms.local  / Executive@123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `qms`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `qms`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `login_activity`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `quotation_items`;
DROP TABLE IF EXISTS `quotations`;
DROP TABLE IF EXISTS `plans`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  roles
-- ---------------------------------------------------------------------
CREATE TABLE `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  users  (admins, managers, executives)
--  manager_id : self-reference linking an executive to their manager.
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`       INT UNSIGNED NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `email`         VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(40)  DEFAULT NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `manager_id`    INT UNSIGNED DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `last_login_at` DATETIME     DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_manager` (`manager_id`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role`    FOREIGN KEY (`role_id`)    REFERENCES `roles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  customers
-- ---------------------------------------------------------------------
CREATE TABLE `customers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `address`    VARCHAR(500) DEFAULT NULL,
    `telephone`  VARCHAR(40)  DEFAULT NULL,
    `nic`        VARCHAR(30)  NOT NULL,
    `email`      VARCHAR(190) DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_nic` (`nic`),
    KEY `idx_customers_name` (`name`),
    KEY `idx_customers_creator` (`created_by`),
    CONSTRAINT `fk_customers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  plans
-- ---------------------------------------------------------------------
CREATE TABLE `plans` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150)   NOT NULL,
    `description` TEXT           DEFAULT NULL,
    `amount`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plans_status` (`status`),
    KEY `idx_plans_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  quotations
-- ---------------------------------------------------------------------
CREATE TABLE `quotations` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quotation_number`   VARCHAR(50)   NOT NULL,
    `customer_id`        INT UNSIGNED  NOT NULL,
    `created_by`         INT UNSIGNED  DEFAULT NULL,
    `subtotal`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax`                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total`              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`              TEXT          DEFAULT NULL,
    `terms`              TEXT          DEFAULT NULL,
    `expiry_date`        DATE          DEFAULT NULL,
    `status`             ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    `verification_token` VARCHAR(64)   NOT NULL,
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotation_number` (`quotation_number`),
    UNIQUE KEY `uq_quotation_token` (`verification_token`),
    KEY `idx_quotations_customer` (`customer_id`),
    KEY `idx_quotations_creator` (`created_by`),
    KEY `idx_quotations_status` (`status`),
    KEY `idx_quotations_created_at` (`created_at`),
    CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_quotations_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  quotation_items  (line items; plan-backed or custom)
-- ---------------------------------------------------------------------
CREATE TABLE `quotation_items` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quotation_id` INT UNSIGNED  NOT NULL,
    `plan_id`      INT UNSIGNED  DEFAULT NULL,
    `description`  VARCHAR(500)  NOT NULL,
    `quantity`     DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `line_total`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_items_quotation` (`quotation_id`),
    KEY `idx_items_plan` (`plan_id`),
    CONSTRAINT `fk_items_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_plan`      FOREIGN KEY (`plan_id`)      REFERENCES `plans` (`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  settings  (key/value; company info, branding, defaults)
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  activity_logs  (audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    DEFAULT NULL,
    `action`      VARCHAR(100)    NOT NULL,
    `entity_type` VARCHAR(80)     DEFAULT NULL,
    `entity_id`   INT UNSIGNED    DEFAULT NULL,
    `description` VARCHAR(500)    DEFAULT NULL,
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `user_agent`  VARCHAR(255)    DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_logs_created_at` (`created_at`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  login_activity  (every login attempt — success or failure)
-- ---------------------------------------------------------------------
CREATE TABLE `login_activity` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    DEFAULT NULL,
    `email`      VARCHAR(190)    NOT NULL,
    `ip_address` VARCHAR(45)     DEFAULT NULL,
    `user_agent` VARCHAR(255)    DEFAULT NULL,
    `status`     ENUM('success','failed') NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_user` (`user_id`),
    KEY `idx_login_email` (`email`),
    KEY `idx_login_created_at` (`created_at`),
    CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SEED DATA
-- =====================================================================

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
    (1, 'admin',     'Full system access and configuration'),
    (2, 'manager',   'Manages a team of executives and their quotations'),
    (3, 'executive', 'Creates and manages own quotations');

-- Users. Password hashes generated with PHP password_hash() (bcrypt).
--   Admin@123 / Manager@123 / Executive@123
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `status`, `manager_id`, `created_by`) VALUES
    (1, 1, 'System Administrator', 'admin@qms.local',     '$2y$10$5TcMmovSpmGIYC4WhvQkcO5pvFSfG5EVd2VbS/BoPAV8IbMIVecgu', '0112345678', 'active', NULL, NULL),
    (2, 2, 'Mahesh Perera',        'manager@qms.local',   '$2y$10$5hBY1JN.NS7olYZKUjcYf.shPXxA9OSgAB8HS7VxqG/Z.q8yvlH2C', '0712345678', 'active', NULL, 1),
    (3, 3, 'Nimal Fernando',       'executive@qms.local', '$2y$10$rKRBRhQ1irmnUY9E9y9k3upcWaHfDaPwypBxIS9ZrZL0yqj//ATJ.', '0763334444', 'active', 2,    1),
    (4, 3, 'Kasun Silva',          'kasun@qms.local',     '$2y$10$rKRBRhQ1irmnUY9E9y9k3upcWaHfDaPwypBxIS9ZrZL0yqj//ATJ.', '0775556666', 'active', 2,    1);

INSERT INTO `plans` (`id`, `name`, `description`, `amount`, `status`) VALUES
    (1, 'Basic Plan',        'Entry-level package suitable for individuals and small needs.',        15000.00, 'active'),
    (2, 'Standard Plan',     'Most popular package with a balanced set of features.',                35000.00, 'active'),
    (3, 'Premium Plan',      'Comprehensive package with priority support and extras.',              75000.00, 'active'),
    (4, 'Enterprise Plan',   'Custom enterprise solution with dedicated account management.',       150000.00, 'active'),
    (5, 'Legacy Plan',       'Discontinued package retained for historical quotations.',             10000.00, 'inactive');

INSERT INTO `customers` (`id`, `name`, `address`, `telephone`, `nic`, `email`, `created_by`) VALUES
    (1, 'Acme Holdings (Pvt) Ltd', 'No. 42, Galle Road, Colombo 03',         '0112556677', '199012345678', 'info@acme.lk',     2),
    (2, 'Sunrise Traders',         'No. 8, Kandy Road, Kadawatha',           '0332244556', '882345678V',   'sales@sunrise.lk', 3),
    (3, 'Janaka Bandara',          'No. 120/A, Temple Road, Maharagama',     '0771234567', '912233445V',   'janaka.b@mail.com',3);

-- Quotations (totals precomputed to match their items below).
INSERT INTO `quotations`
    (`id`, `quotation_number`, `customer_id`, `created_by`, `subtotal`, `discount`, `tax`, `total`, `notes`, `terms`, `expiry_date`, `status`, `verification_token`) VALUES
    (1, 'QTN-202606-0001', 1, 2, 110000.00, 5000.00, 5250.00, 110250.00,
        'Includes one-time onboarding.', 'Valid for 30 days. Prices exclusive of any third-party charges.',
        '2026-07-14', 'sent',     'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2'),
    (2, 'QTN-202606-0002', 2, 3, 35000.00,  0.00,    1750.00, 36750.00,
        NULL, 'Valid for 14 days.',
        '2026-06-28', 'accepted', 'b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3'),
    (3, 'QTN-202606-0003', 3, 3, 15000.00,  1500.00, 675.00,  14175.00,
        'Customer requested a discount.', 'Valid for 30 days.',
        '2026-07-10', 'draft',    'c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4');

INSERT INTO `quotation_items`
    (`quotation_id`, `plan_id`, `description`, `quantity`, `unit_price`, `line_total`) VALUES
    (1, 2, 'Standard Plan', 1.00, 35000.00, 35000.00),
    (1, 3, 'Premium Plan',  1.00, 75000.00, 75000.00),
    (2, 2, 'Standard Plan', 1.00, 35000.00, 35000.00),
    (3, 1, 'Basic Plan',    1.00, 15000.00, 15000.00);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('company_name',    'QMS Solutions (Pvt) Ltd'),
    ('company_address', 'No. 100, Main Street, Colombo 01, Sri Lanka'),
    ('company_phone',   '+94 11 234 5678'),
    ('company_email',   'hello@qms-solutions.lk'),
    ('company_website', 'www.qms-solutions.lk'),
    ('company_logo',    ''),
    ('quotation_prefix','QTN'),
    ('tax_rate',        '5'),
    ('currency_symbol', 'Rs.'),
    ('default_terms',   'This quotation is valid for 30 days from the date of issue. Prices are subject to change without prior notice after the validity period. Payment terms: 50% advance, balance on delivery.');

-- Done.
