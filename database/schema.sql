-- RTB and RON Campaign Platform Database Schema
-- Created for user_up database with Puputchen12$ credentials

CREATE DATABASE IF NOT EXISTS `user_up` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `user_up`;

-- --------------------------------------------------------
-- 1. users table
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager') DEFAULT 'manager',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. advertisers table
-- --------------------------------------------------------
CREATE TABLE `advertisers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. publishers table
-- --------------------------------------------------------
CREATE TABLE `publishers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `revenue_share` DECIMAL(5,2) DEFAULT 50.00,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. categories table
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `type` ENUM('adult', 'mainstream') NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. websites table
-- --------------------------------------------------------
CREATE TABLE `websites` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `publisher_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `category_id` INT NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`publisher_id`) REFERENCES `publishers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. zones table
-- --------------------------------------------------------
CREATE TABLE `zones` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `website_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `zone_type` ENUM('banner', 'popup', 'native') NOT NULL,
  `size` VARCHAR(20),
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`website_id`) REFERENCES `websites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. banner_sizes table
-- --------------------------------------------------------
CREATE TABLE `banner_sizes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `width` INT NOT NULL,
  `height` INT NOT NULL,
  `name` VARCHAR(20) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. countries table
-- --------------------------------------------------------
CREATE TABLE `countries` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `code` VARCHAR(2) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. browsers table
-- --------------------------------------------------------
CREATE TABLE `browsers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `user_agent_pattern` VARCHAR(255),
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. devices table
-- --------------------------------------------------------
CREATE TABLE `devices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `type` ENUM('desktop', 'mobile', 'tablet') NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. operating_systems table
-- --------------------------------------------------------
CREATE TABLE `operating_systems` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `type` ENUM('windows', 'macos', 'linux', 'android', 'ios') NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. rtb_campaigns table
-- --------------------------------------------------------
CREATE TABLE `rtb_campaigns` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `advertiser_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `endpoint_url` TEXT NOT NULL,
  `bid_type` ENUM('cpm', 'cpc') NOT NULL,
  `bid_amount` DECIMAL(10,4) NOT NULL,
  `category_id` INT NOT NULL,
  `banner_sizes` JSON,
  `target_countries` JSON,
  `target_browsers` JSON,
  `target_devices` JSON,
  `target_os` JSON,
  `status` ENUM('active', 'paused', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`advertiser_id`) REFERENCES `advertisers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. ron_campaigns table
-- --------------------------------------------------------
CREATE TABLE `ron_campaigns` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `advertiser_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `bid_type` ENUM('cpm', 'cpc') NOT NULL,
  `bid_amount` DECIMAL(10,4) NOT NULL,
  `category_id` INT NOT NULL,
  `banner_sizes` JSON,
  `target_countries` JSON,
  `target_browsers` JSON,
  `target_devices` JSON,
  `target_os` JSON,
  `status` ENUM('active', 'paused', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`advertiser_id`) REFERENCES `advertisers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. creatives table
-- --------------------------------------------------------
CREATE TABLE `creatives` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `campaign_id` INT NOT NULL,
  `campaign_type` ENUM('rtb', 'ron') NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `creative_type` ENUM('html5', 'script') NOT NULL,
  `content` TEXT NOT NULL,
  `size` VARCHAR(20) NOT NULL,
  `bid_amount` DECIMAL(10,4) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 15. campaign_stats table (for tracking performance)
-- --------------------------------------------------------
CREATE TABLE `campaign_stats` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `campaign_id` INT NOT NULL,
  `campaign_type` ENUM('rtb', 'ron') NOT NULL,
  `date` DATE NOT NULL,
  `impressions` INT DEFAULT 0,
  `clicks` INT DEFAULT 0,
  `revenue` DECIMAL(10,4) DEFAULT 0.0000,
  `cost` DECIMAL(10,4) DEFAULT 0.0000,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 16. rtb_endpoints table (for buying traffic)
-- --------------------------------------------------------
CREATE TABLE `rtb_endpoints` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `url` TEXT NOT NULL,
  `category_id` INT NOT NULL,
  `banner_sizes` JSON,
  `floor_price` DECIMAL(10,4) DEFAULT 0.0001,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert Initial Data
-- --------------------------------------------------------

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES 
('admin', 'admin@example.com', '$2y$10$cfDDFRefenTcUrZ8AuopuefrMuZYn90QAXELbcqwXfyfrW570ahhO', 'admin');

-- Insert default categories
INSERT INTO `categories` (`name`, `type`) VALUES 
('Adult Entertainment', 'adult'),
('Dating', 'adult'),
('Gambling', 'adult'),
('News', 'mainstream'),
('Technology', 'mainstream'),
('Finance', 'mainstream'),
('Health', 'mainstream'),
('Sports', 'mainstream'),
('Entertainment', 'mainstream'),
('Travel', 'mainstream');

-- Insert standard banner sizes (Exoclick compatible)
INSERT INTO `banner_sizes` (`width`, `height`, `name`) VALUES 
(300, 250, '300x250'),
(300, 100, '300x100'),
(300, 50, '300x50'),
(300, 500, '300x500'),
(900, 250, '900x250'),
(728, 90, '728x90'),
(160, 600, '160x600');

-- Insert common countries
INSERT INTO `countries` (`code`, `name`) VALUES 
('US', 'United States'),
('GB', 'United Kingdom'),
('CA', 'Canada'),
('AU', 'Australia'),
('DE', 'Germany'),
('FR', 'France'),
('IT', 'Italy'),
('ES', 'Spain'),
('NL', 'Netherlands'),
('ID', 'Indonesia'),
('MY', 'Malaysia'),
('SG', 'Singapore'),
('TH', 'Thailand'),
('VN', 'Vietnam'),
('PH', 'Philippines');

-- Insert common browsers
INSERT INTO `browsers` (`name`, `user_agent_pattern`) VALUES 
('Chrome', 'Chrome'),
('Firefox', 'Firefox'),
('Safari', 'Safari'),
('Edge', 'Edge'),
('Opera', 'Opera');

-- Insert device types
INSERT INTO `devices` (`name`, `type`) VALUES 
('Desktop', 'desktop'),
('Mobile', 'mobile'),
('Tablet', 'tablet');

-- Insert operating systems
INSERT INTO `operating_systems` (`name`, `type`) VALUES 
('Windows', 'windows'),
('macOS', 'macos'),
('Linux', 'linux'),
('Android', 'android'),
('iOS', 'ios');

-- Insert sample Exoclick RTB endpoint
INSERT INTO `rtb_endpoints` (`name`, `url`, `category_id`, `banner_sizes`, `floor_price`) VALUES 
('Exoclick RTB', 'http://rtb.exoclick.com/rtb.php?idzone=5128252&fid=e573a1c2a656509b0112f7213359757be76929c7', 1, '["300x250", "300x100", "300x50", "300x500", "900x250", "728x90", "160x600"]', 0.0010);