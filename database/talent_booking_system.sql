-- =====================================================================
-- TALENT BOOKING MANAGEMENT SYSTEM - DATABASE
-- Import this file into phpMyAdmin (XAMPP/WAMP) to create the full DB.
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `talent_booking_system`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `talent_booking_system`;

-- ---------------------------------------------------------------------
-- Table: users
-- Holds all three roles: admin, customer, provider
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fullname` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','customer','provider') NOT NULL DEFAULT 'customer',
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `remember_token` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: categories
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: providers
-- Extended profile for users with role = 'provider'
-- ---------------------------------------------------------------------
CREATE TABLE `providers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `experience_years` TINYINT UNSIGNED DEFAULT 0,
  `location` VARCHAR(150) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `skills` VARCHAR(500) DEFAULT NULL,
  `service_description` TEXT DEFAULT NULL,
  `video_links` TEXT DEFAULT NULL COMMENT 'Comma-separated video URLs',
  `approval_status` ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `average_rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_providers_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  INDEX `idx_providers_approval` (`approval_status`),
  INDEX `idx_providers_location` (`location`),
  INDEX `idx_providers_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: bookings
-- ---------------------------------------------------------------------
CREATE TABLE `bookings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `event_name` VARCHAR(200) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `event_date` DATE NOT NULL,
  `event_location` VARCHAR(200) NOT NULL,
  `event_description` TEXT DEFAULT NULL,
  `booking_status` ENUM('pending','accepted','rejected','cancelled','ongoing','completed') NOT NULL DEFAULT 'pending',
  `provider_completed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `customer_completed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  INDEX `idx_bookings_status` (`booking_status`),
  INDEX `idx_bookings_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: payments
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('mpesa','airtel_money','tigo_pesa','halopesa','bank_transfer') NOT NULL,
  `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_reference` VARCHAR(150) DEFAULT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  INDEX `idx_payments_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: commissions
-- Platform commission recorded once a booking is confirmed completed by
-- both sides (see customer/booking_view.php confirm_completion action).
-- Read by admin/commissions.php and provider/earnings.php. This table
-- was referenced throughout the app but was MISSING from the schema
-- entirely - both of those pages, and the completion-confirmation flow,
-- would fatal with "Table 'commissions' doesn't exist". Added here.
-- ---------------------------------------------------------------------
CREATE TABLE `commissions` (
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
-- Table: reviews
-- ---------------------------------------------------------------------
CREATE TABLE `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL COMMENT '1 to 5',
  `review` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating_range` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: notifications
-- ---------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread','read') NOT NULL DEFAULT 'unread',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: gallery
-- ---------------------------------------------------------------------
CREATE TABLE `gallery` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gallery_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: availability
-- ---------------------------------------------------------------------
CREATE TABLE `availability` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `available_date` DATE NOT NULL,
  `status` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_availability_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_provider_date` (`provider_id`, `available_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: settings
-- Single-row table for site-wide settings
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_name` VARCHAR(150) NOT NULL DEFAULT 'Talent Booking System',
  `logo` VARCHAR(255) DEFAULT NULL,
  `contact_email` VARCHAR(150) DEFAULT NULL,
  `contact_phone` VARCHAR(20) DEFAULT NULL,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: activity_logs
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `activity` VARCHAR(500) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_activitylogs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: password_resets
-- ---------------------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_password_resets_email` (`email`),
  INDEX `idx_password_resets_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_attempts
-- Tracks failed login attempts (by email AND by IP) for rate limiting.
-- ---------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier_type` ENUM('email','ip') NOT NULL,
  `identifier_value` VARCHAR(191) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_attempts_lookup` (`identifier_type`, `identifier_value`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: audit_logs
-- Written by includes/functions.php::audit_log(). This table was
-- referenced throughout the app (bookings, payments, reviews, provider
-- profile changes, admin/Audit_logs.php) but was MISSING from the
-- schema entirely - every audit_log() call would fatal with
-- "Table 'audit_logs' doesn't exist". Added here to fix that.
-- ---------------------------------------------------------------------
CREATE TABLE `audit_logs` (
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
-- Table: booking_locations
-- Live location sharing: the customer's device pushes its current
-- GPS position here (one row per booking, upserted) while a booking is
-- 'ongoing', so the provider can see roughly where to go. The customer
-- must explicitly opt in (is_sharing = 1) and can stop at any time;
-- sharing is also only ever readable by the provider while the booking
-- is 'ongoing' (see customer/location_share.php, customer/location_ajax.php,
-- provider/track_location.php, provider/location_poll.php).
-- ---------------------------------------------------------------------
CREATE TABLE `booking_locations` (
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
-- Table: messages (real-time chat between customer and provider,
-- scoped to a booking)
-- ---------------------------------------------------------------------
CREATE TABLE `messages` (
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

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default site settings
INSERT INTO `settings` (`site_name`, `logo`, `contact_email`, `contact_phone`, `commission_rate`) VALUES
('Talent Booking System', NULL, 'support@talentbooking.com', '+255700000000', 10.00);

-- Default admin account
-- Email: admin@talentbooking.com | Password: Admin@123
-- Hash below is generated with PHP password_hash('Admin@123', PASSWORD_DEFAULT)
INSERT INTO `users` (`fullname`, `email`, `phone`, `password`, `role`, `status`) VALUES
('System Administrator', 'admin@talentbooking.com', '+255700000001', 'Admin@123', 'admin', 'active');

-- Categories
INSERT INTO `categories` (`category_name`, `description`) VALUES
('MC', 'Masters of Ceremony for events and weddings'),
('Comedian', 'Stand-up comedians and entertainers'),
('Musician', 'Live band and solo musicians'),
('Singer', 'Vocalists for hire'),
('DJ', 'Disc jockeys for parties and events'),
('Content Creator', 'Social media and digital content creators'),
('Influencer', 'Social media influencers for brand events'),
('Event Host', 'Professional event hosts and presenters'),
('Motivational Speaker', 'Speakers for corporate and inspirational events'),
('Dancer', 'Solo and group dance performers'),
('Photographer', 'Event and portrait photographers'),
('Videographer', 'Event videographers and video editors');

-- Sample customers (password for all: Customer@123)
INSERT INTO `users` (`fullname`, `email`, `phone`, `password`, `role`, `status`) VALUES
('Asha Mwakalinga', 'asha@example.com', '+255711000001', '$2y$10$92Ix2m8Y2b6b1XwG5Z6UOu8pQeYVh8y0m7C0kQxT3E1V8jvW1r7Nq', 'customer', 'active'),
('Baraka Simon', 'baraka@example.com', '+255711000002', '$2y$10$92Ix2m8Y2b6b1XwG5Z6UOu8pQeYVh8y0m7C0kQxT3E1V8jvW1r7Nq', 'customer', 'active');

-- Sample providers (password for all: Provider@123)
INSERT INTO `users` (`fullname`, `email`, `phone`, `password`, `role`, `status`) VALUES
('DJ Kombo', 'djkombo@example.com', '+255722000001', '$2y$10$92Ix2m8Y2b6b1XwG5Z6UOu8pQeYVh8y0m7C0kQxT3E1V8jvW1r7Nq', 'provider', 'active'),
('MC Fahari', 'mcfahari@example.com', '+255722000002', '$2y$10$92Ix2m8Y2b6b1XwG5Z6UOu8pQeYVh8y0m7C0kQxT3E1V8jvW1r7Nq', 'provider', 'active'),
('Comedian Chapa', 'chapa@example.com', '+255722000003', '$2y$10$92Ix2m8Y2b6b1XwG5Z6UOu8pQeYVh8y0m7C0kQxT3E1V8jvW1r7Nq', 'provider', 'active');

INSERT INTO `providers` (`user_id`, `category_id`, `bio`, `experience_years`, `location`, `price`, `skills`, `service_description`, `approval_status`) VALUES
(3, 5, 'Professional DJ with 8 years of experience spinning at weddings and corporate events.', 8, 'Dar es Salaam', 500000.00, 'Mixing, Scratching, MC-ing', 'Full sound system and lighting included.', 'approved'),
(4, 1, 'Charismatic MC known for keeping crowds engaged at weddings and launches.', 6, 'Arusha', 350000.00, 'Public Speaking, Bilingual (EN/SW)', 'Available for weddings, corporate events, and product launches.', 'approved'),
(5, 2, 'Stand-up comedian with viral sets and corporate-friendly material.', 4, 'Dodoma', 300000.00, 'Stand-up, Improv', 'Clean or adult sets available depending on audience.', 'pending');

-- Sample booking + payment + review flow
INSERT INTO `bookings` (`customer_id`, `provider_id`, `event_name`, `event_type`, `event_date`, `event_location`, `event_description`, `booking_status`) VALUES
(6, 1, 'Asha & James Wedding', 'Wedding', '2026-09-15', 'Dar es Salaam', 'Reception DJ needed from 6pm to midnight.', 'completed'),
(6, 2, 'Company Anniversary', 'Corporate', '2026-10-02', 'Arusha', 'MC needed for 200-guest corporate event.', 'accepted'),
(7, 1, 'Birthday Party', 'Birthday', '2026-11-20', 'Dar es Salaam', '30th birthday celebration, need good sound system.', 'pending');

INSERT INTO `payments` (`booking_id`, `amount`, `payment_method`, `payment_status`, `transaction_reference`) VALUES
(1, 500000.00, 'mpesa', 'paid', 'MPESA-REF-100234');

INSERT INTO `reviews` (`booking_id`, `customer_id`, `provider_id`, `rating`, `review`) VALUES
(1, 6, 1, 5, 'DJ Kombo was fantastic! Great music selection and very professional.');

-- Recalculate provider average rating from seeded reviews
UPDATE `providers` SET `average_rating` = 5.00 WHERE `id` = 1;

-- Sample notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `status`) VALUES
(3, 'New Booking Request', 'You have a new booking request for "Birthday Party".', 'unread'),
(6, 'Booking Accepted', 'MC Fahari accepted your booking for "Company Anniversary".', 'unread');

COMMIT;
