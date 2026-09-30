-- =====================================================================
-- Incremental migration for an EXISTING database.
-- Run this once via phpMyAdmin's SQL tab (or `mysql -u root -p db < this`)
-- if you already imported talent_booking_system.sql before this session.
--
-- Safe to run even if some pieces already exist: CREATE TABLE IF NOT
-- EXISTS is used everywhere possible. The two ADD COLUMN statements will
-- error with "Duplicate column name" if you already have those columns -
-- that's fine, it just means you already have them; ignore that error
-- and continue.
-- =====================================================================

USE `talent_booking_system`;

-- ---------------------------------------------------------------------
-- Was completely missing: every audit_log() call (bookings, payments,
-- reviews, provider profile changes, admin/Audit_logs.php) would fatal
-- without this table.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `user_role` ENUM('admin','customer','provider') DEFAULT NULL,
  `action` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_auditlogs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_auditlogs_role` (`user_role`),
  INDEX `idx_auditlogs_action` (`action`),
  INDEX `idx_auditlogs_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Login rate limiting (added earlier this session, included again here
-- in case you're applying everything in one go).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier_type` ENUM('email','ip') NOT NULL,
  `identifier_value` VARCHAR(191) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_attempts_lookup` (`identifier_type`, `identifier_value`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Was completely missing: provider/booking_view.php and
-- customer/booking_view.php both read/write these two columns to
-- coordinate the "provider marks complete -> customer confirms" flow.
-- Without them, that whole flow would fatal.
-- ---------------------------------------------------------------------
ALTER TABLE `bookings`
  ADD COLUMN `provider_completed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `booking_status`,
  ADD COLUMN `customer_completed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `provider_completed`;

-- ---------------------------------------------------------------------
-- New: live location sharing (customer -> provider) for 'ongoing'
-- bookings. See customer/location_share.php, customer/location_ajax.php,
-- provider/track_location.php, provider/location_poll.php.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking_locations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `accuracy_meters` DECIMAL(7,2) DEFAULT NULL,
  `is_sharing` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookinglocations_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookinglocations_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Was completely missing: admin/commissions.php and provider/earnings.php
-- both query this table, and the booking-completion flow in
-- customer/booking_view.php inserts into it. Without it, all three fatal.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL UNIQUE,
  `payment_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `payment_amount` DECIMAL(10,2) NOT NULL,
  `commission_rate` DECIMAL(5,2) NOT NULL,
  `commission_amount` DECIMAL(10,2) NOT NULL,
  `provider_amount` DECIMAL(10,2) NOT NULL,
  `commission_status` ENUM('pending','earned','paid') NOT NULL DEFAULT 'earned',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_commissions_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  INDEX `idx_commissions_status` (`commission_status`),
  INDEX `idx_commissions_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Was completely missing: admin/settings.php reads/writes this column,
-- and the commission-calculation step in customer/booking_view.php
-- reads it (falling back to 10% if absent - so this one degrades
-- gracefully, but the admin settings page itself would fatal on save).
-- ---------------------------------------------------------------------
ALTER TABLE `settings`
  ADD COLUMN `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00;

-- ---------------------------------------------------------------------
-- New: real-time chat between customer and provider, scoped to a
-- booking. See chat_ajax.php, customer/chat.php, provider/chat.php.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_messages_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_messages_booking` (`booking_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
