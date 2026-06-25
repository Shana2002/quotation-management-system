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

-- Ensure the import connection treats string literals (e.g. the "•" bullets in
-- benefits/projection JSON) as utf8mb4, regardless of the client default.
SET NAMES utf8mb4;

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
    `phone`         VARCHAR(40)  NOT NULL,
    `position`      VARCHAR(100) NOT NULL,
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
    `plan_type`   VARCHAR(40)    NOT NULL DEFAULT 'royal_plus',
    `description` TEXT           DEFAULT NULL,
    `amount`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    `parameters`  LONGTEXT       DEFAULT NULL,  -- JSON: rates / prices / durations
    `benefits`    TEXT           DEFAULT NULL,  -- benefits & conditions text
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plans_status` (`status`),
    KEY `idx_plans_type` (`plan_type`),
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
    `plan_id`            INT UNSIGNED  DEFAULT NULL,
    `plan_type`          VARCHAR(40)   DEFAULT NULL,
    `inputs`             LONGTEXT      DEFAULT NULL,  -- JSON: captured quotation inputs
    `projection`         LONGTEXT      DEFAULT NULL,  -- JSON: computed projection (headers/rows/summary)
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
    KEY `idx_quotations_plan` (`plan_id`),
    KEY `idx_quotations_status` (`status`),
    KEY `idx_quotations_created_at` (`created_at`),
    CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_quotations_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON DELETE SET NULL,
    CONSTRAINT `fk_quotations_plan`     FOREIGN KEY (`plan_id`)     REFERENCES `plans` (`id`)     ON DELETE SET NULL
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
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `position`,`status`, `manager_id`, `created_by`) VALUES
    (1, 1, 'System Administrator', 'admin@qms.local',     '$2y$10$5TcMmovSpmGIYC4WhvQkcO5pvFSfG5EVd2VbS/BoPAV8IbMIVecgu', '0112345678','Admin Manager', 'active', NULL, NULL),

-- OXIAURA Agarwood plantation products. `parameters` holds the admin-editable
-- rates/prices as JSON; `benefits` holds the conditions printed on the PDF.
INSERT INTO `plans` (`id`, `name`, `plan_type`, `description`, `amount`, `parameters`, `benefits`, `status`) VALUES
    (1, 'Royal Plus', 'royal_plus',
        'Interest/harvest-income plan, 1–4 year tenure, monthly or annual payout.', 0.00,
        '{"years":{"1":{"monthly_rate":2,"annual_rate":24},"2":{"monthly_rate":2,"annual_rate":24},"3":{"monthly_rate":2,"annual_rate":24},"4":{"monthly_rate":2,"annual_rate":24}}}',
        '• Guaranteed harvest income for the full tenure.\n• Ownership share of an Agarwood plantation land.\n• Full capital returned together with the total maturity value.\n• Free to choose monthly or annual harvest payout.',
        'active'),
    (2, 'Guaranteed Plus', 'guaranteed_plus',
        'Interest/harvest-income plan, 2–5 year tenure, monthly or annual payout.', 0.00,
        '{"years":{"2":{"monthly_rate":2,"annual_rate":24},"3":{"monthly_rate":2,"annual_rate":24},"4":{"monthly_rate":2,"annual_rate":24},"5":{"monthly_rate":2,"annual_rate":24}}}',
        '• Guaranteed harvest income across a 2–5 year tenure.\n• Higher returns for longer commitment periods.\n• Full capital returned together with the total maturity value.\n• Free to choose monthly or annual harvest payout.',
        'active'),
    (3, 'Monthly Wealth Plan', 'monthly_wealth',
        'One-time investment repaid monthly over 96 months plus a maturity benefit.', 0.00,
        '{"repay_months":96,"monthly_repay_rate":1.5,"maturity_benefit_rate":25}',
        '• Single one-time investment, no recurring payments required.\n• Steady monthly income for the full 8-year (96 month) term.\n• Additional maturity benefit paid at the end of the term.\n• Backed by an appreciating Agarwood plantation asset.',
        'active'),
    (4, 'Supreme Plus Plan', 'supreme_plus',
        'Pay-in over 50 months, then converts to a Monthly Wealth plan with repayments.', 0.00,
        '{"contribution_months":50,"repay_months":96,"monthly_repay_rate":1.5,"maturity_benefit_rate":25}',
        '• Build capital with affordable monthly payments over the pay-in term.\n• Automatically converts to a Monthly Wealth plan once complete.\n• Earns monthly re-payments plus a maturity benefit thereafter.\n• Ideal for disciplined, long-term wealth building.',
        'active'),
    (5, 'Golden Crop', 'golden_crop',
        'Company plants a chosen crop on the customer''s bare land; priced per 10 perches.', 0.00,
        '{"crops":[{"name":"Agarwood","price_per_10perch":250000,"harvest_value_per_10perch":1500000},{"name":"Sandalwood","price_per_10perch":300000,"harvest_value_per_10perch":1800000},{"name":"Teak","price_per_10perch":150000,"harvest_value_per_10perch":900000}]}',
        '• The company plants and maintains the crop on your own land.\n• You retain full ownership of the land and the trees.\n• Significant projected harvest income at maturity.\n• Professional plantation management throughout the growth cycle.',
        'active'),
    (6, 'Plant Selling', 'plant_selling',
        'Direct sale of plants, priced per plant by crop type.', 0.00,
        '{"crops":[{"name":"Agarwood","price_per_plant":1500,"harvest_value_per_plant":25000},{"name":"Sandalwood","price_per_plant":2000,"harvest_value_per_plant":30000},{"name":"Teak","price_per_plant":800,"harvest_value_per_plant":12000}]}',
        '• High-quality, nursery-raised plants supplied directly.\n• Choice of premium crop varieties.\n• Strong projected harvest value per plant at maturity.\n• Optional planting and maintenance guidance available.',
        'active');

INSERT INTO `customers` (`id`, `name`, `address`, `telephone`, `nic`, `email`, `created_by`) VALUES
    (1, 'Acme Holdings (Pvt) Ltd', 'No. 42, Galle Road, Colombo 03',         '0112556677', '199012345678', 'info@acme.lk',     2),
    (2, 'Sunrise Traders',         'No. 8, Kandy Road, Kadawatha',           '0332244556', '882345678V',   'sales@sunrise.lk', 3),
    (3, 'Janaka Bandara',          'No. 120/A, Temple Road, Maharagama',     '0771234567', '912233445V',   'janaka.b@mail.com',3);

-- Plan-type quotations. `total`/`subtotal` hold the headline amount (capital);
-- `inputs` and `projection` are JSON consumed by the show view and the PDF.
INSERT INTO `quotations`
    (`id`, `quotation_number`, `customer_id`, `created_by`, `plan_id`, `plan_type`, `inputs`, `projection`,
     `subtotal`, `discount`, `tax`, `total`, `notes`, `terms`, `expiry_date`, `status`, `verification_token`) VALUES
    (1, 'QTN-202606-0001', 1, 2, 1, 'royal_plus',
        '{"investment":1000000,"period_years":1,"method":"monthly"}',
        '{"intro":"Royal Plus plan with harvest income will be made in the following manner.","headers":["Year","Investment","Monthly harvest Profit","Total maturity value"],"rows":[["1 Year","Rs. 1,000,000.00","Rs. 20,000.00 x 12","Rs. 1,240,000.00"]],"summary":{"Investment":"Rs. 1,000,000.00","Total harvest profit":"Rs. 240,000.00","Total maturity value":"Rs. 1,240,000.00"},"headline_amount":1000000,"plan_label":"Royal Plus","letter_title":"Investing for Agarwood Land","benefits":"• Guaranteed harvest income for the full tenure.\\n• Ownership share of an Agarwood plantation land.\\n• Full capital returned together with the total maturity value.\\n• Free to choose monthly or annual harvest payout."}',
        1000000.00, 0.00, 0.00, 1000000.00,
        'Royal Plus 1 year monthly payout.', NULL, '2026-07-14', 'sent',
        'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2'),
    (2, 'QTN-202606-0002', 2, 3, 3, 'monthly_wealth',
        '{"investment":500000}',
        '{"intro":"Monthly Wealth plan with a one-time investment, repaid over 96 months (8 years), is illustrated below.","headers":["Investment","Monthly Re-payment","Term","Maturity Benefit","Total Value"],"rows":[["Rs. 500,000.00","Rs. 7,500.00 x 96","8 Years","Rs. 125,000.00","Rs. 845,000.00"]],"summary":{"Investment":"Rs. 500,000.00","Total re-payments":"Rs. 720,000.00","Maturity benefit":"Rs. 125,000.00","Total value":"Rs. 845,000.00"},"headline_amount":500000,"plan_label":"Monthly Wealth Plan","letter_title":"Monthly Wealth Plan — Agarwood Investment","benefits":"• Single one-time investment, no recurring payments required.\\n• Steady monthly income for the full 8-year (96 month) term.\\n• Additional maturity benefit paid at the end of the term.\\n• Backed by an appreciating Agarwood plantation asset."}',
        500000.00, 0.00, 0.00, 500000.00,
        NULL, NULL, '2026-06-28', 'accepted',
        'b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3'),
    (3, 'QTN-202606-0003', 3, 3, 5, 'golden_crop',
        '{"crop":"Agarwood","land_perches":20}',
        '{"intro":"Golden Crop plan — planting Agarwood on your land is illustrated below.","headers":["Crop","Land Extent","Investment","Projected Harvest Income"],"rows":[["Agarwood","20 Perches","Rs. 500,000.00","Rs. 3,000,000.00"]],"summary":{"Investment":"Rs. 500,000.00","Projected harvest income":"Rs. 3,000,000.00"},"headline_amount":500000,"plan_label":"Golden Crop","letter_title":"Golden Crop — Plantation on Your Land","benefits":"• The company plants and maintains the crop on your own land.\\n• You retain full ownership of the land and the trees.\\n• Significant projected harvest income at maturity.\\n• Professional plantation management throughout the growth cycle."}',
        500000.00, 0.00, 0.00, 500000.00,
        'Golden Crop on 20 perches.', NULL, '2026-07-10', 'draft',
        'c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('company_name',    'OXIAURA Plantation (PVT) LTD.'),
    ('company_reg_no',  'PV00248151'),
    ('company_address', 'No. 05/01/02, Cyril Janz Mawatha, Galle Road, Panadura.'),
    ('company_phone',   '038 225 4330'),
    ('company_email',   'info@oxiaura.com'),
    ('company_website', 'www.oxiaura.com'),
    ('company_logo',    ''),
    ('quotation_prefix','QTN'),
    ('tax_rate',        '0'),
    ('currency_symbol', 'Rs.'),
    ('signatory_name',  'Hansaka Ravishan'),
    ('signatory_title', 'Admin Executive'),
    ('default_terms',   'This quotation is valid for 30 days from the date of issue. Figures shown are projections based on the selected plan and are subject to the terms of the investment agreement.');

-- Done.
