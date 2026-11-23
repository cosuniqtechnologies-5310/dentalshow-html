-- Dental Show 2027 - Space Booking / Exhibitor Application Database Schema
-- Database: u672271818_dentalshow
-- Table: exhibitor_bookings

CREATE TABLE IF NOT EXISTS `exhibitor_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_ref` VARCHAR(30) NOT NULL UNIQUE,
  `membership_status` ENUM('TNDTMA Member', 'Non-Member', 'Foreign Company') NOT NULL DEFAULT 'TNDTMA Member',
  `company_name` VARCHAR(255) NOT NULL,
  `address` TEXT NOT NULL,
  `city_state` VARCHAR(150) NOT NULL,
  `pincode` VARCHAR(20) NOT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'India',
  `gst_no` VARCHAR(50) DEFAULT NULL,
  `pan_no` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `contact_person` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(50) NOT NULL,
  `designation` VARCHAR(100) DEFAULT NULL,
  `alt_contact_no` VARCHAR(50) DEFAULT NULL,
  `fascia_name` VARCHAR(255) NOT NULL COMMENT 'Display name on stall fascia',
  
  `stall_type` VARCHAR(50) DEFAULT NULL COMMENT 'Regular, Corner, Island / Special Space',
  `stalls_required` INT(11) DEFAULT 0,
  `islands_required` INT(11) DEFAULT 0,
  `preferred_zone` VARCHAR(150) DEFAULT NULL,
  `space_sqmtr` DECIMAL(10,2) DEFAULT NULL,
  
  `signatory_name` VARCHAR(150) NOT NULL,
  `signatory_designation` VARCHAR(100) NOT NULL,
  `agreed_terms` TINYINT(1) NOT NULL DEFAULT 1,
  
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_ref` (`booking_ref`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
