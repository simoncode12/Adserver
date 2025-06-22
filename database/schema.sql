-- RTB and RON Campaign Platform Database Schema
-- AdServer Platform Complete Database Structure

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Core Tables
-- --------------------------------------------------------

-- Admin Users Table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `user_type` enum('admin','advertiser','publisher') DEFAULT 'admin',
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advertisers Table
CREATE TABLE `advertisers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `billing_email` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Publishers Table  
CREATE TABLE `publishers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_email` varchar(100) DEFAULT NULL,
  `payment_method` enum('bank_transfer','paypal','wire') DEFAULT 'paypal',
  `revenue_share` decimal(5,2) DEFAULT 50.00,
  `minimum_payout` decimal(8,2) DEFAULT 100.00,
  `current_earnings` decimal(10,2) DEFAULT 0.00,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Websites Table
CREATE TABLE `websites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `publisher_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `monthly_pageviews` int(11) DEFAULT 0,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `publisher_id` (`publisher_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Zones Table
CREATE TABLE `zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `zone_type` enum('banner','native','video','popunder') DEFAULT 'banner',
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `zone_code` text DEFAULT NULL,
  `floor_price` decimal(8,4) DEFAULT 0.0001,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `website_id` (`website_id`),
  FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` enum('adult','mainstream') DEFAULT 'mainstream',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RTB Campaigns Table
CREATE TABLE `rtb_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `advertiser_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rtb_endpoint_url` varchar(500) NOT NULL,
  `bid_type` enum('CPM','CPC') DEFAULT 'CPM',
  `bid_amount` decimal(8,4) NOT NULL,
  `daily_budget` decimal(10,2) DEFAULT NULL,
  `total_budget` decimal(10,2) DEFAULT NULL,
  `spent_amount` decimal(10,2) DEFAULT 0.00,
  `category_id` int(11) DEFAULT NULL,
  `target_countries` json DEFAULT NULL,
  `target_devices` json DEFAULT NULL,
  `target_browsers` json DEFAULT NULL,
  `target_os` json DEFAULT NULL,
  `banner_sizes` json DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','paused','completed','draft') DEFAULT 'draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `advertiser_id` (`advertiser_id`),
  KEY `category_id` (`category_id`),
  FOREIGN KEY (`advertiser_id`) REFERENCES `advertisers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RON Campaigns Table
CREATE TABLE `ron_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `advertiser_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `bid_type` enum('CPM','CPC') DEFAULT 'CPM',
  `bid_amount` decimal(8,4) NOT NULL,
  `daily_budget` decimal(10,2) DEFAULT NULL,
  `total_budget` decimal(10,2) DEFAULT NULL,
  `spent_amount` decimal(10,2) DEFAULT 0.00,
  `category_id` int(11) DEFAULT NULL,
  `target_countries` json DEFAULT NULL,
  `target_devices` json DEFAULT NULL,
  `target_browsers` json DEFAULT NULL,
  `target_os` json DEFAULT NULL,
  `banner_sizes` json DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','paused','completed','draft') DEFAULT 'draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `advertiser_id` (`advertiser_id`),
  KEY `category_id` (`category_id`),
  FOREIGN KEY (`advertiser_id`) REFERENCES `advertisers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creatives Table
CREATE TABLE `creatives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `campaign_type` enum('rtb','ron') NOT NULL,
  `name` varchar(255) NOT NULL,
  `creative_type` enum('html5','third_party','image','video') DEFAULT 'html5',
  `content_html` longtext DEFAULT NULL,
  `content_url` varchar(500) DEFAULT NULL,
  `click_url` varchar(500) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `status` enum('active','inactive','pending_review') DEFAULT 'pending_review',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `campaign_type` (`campaign_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Reference Data Tables
-- --------------------------------------------------------

-- Countries Table
CREATE TABLE `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Browsers Table
CREATE TABLE `browsers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `version` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Devices Table
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `category` enum('mobile','desktop','tablet','smart_tv','console') DEFAULT 'desktop',
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Operating Systems Table
CREATE TABLE `operating_systems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `category` enum('mobile','desktop','server') DEFAULT 'desktop',
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Banner Sizes Table
CREATE TABLE `banner_sizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `category` enum('banner','mobile','skyscraper','rectangle','square') DEFAULT 'banner',
  `iab_standard` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dimensions` (`width`,`height`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Tracking and Statistics Tables
-- --------------------------------------------------------

-- Bid Requests Table
CREATE TABLE `bid_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(255) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `campaign_type` enum('rtb','ron') DEFAULT NULL,
  `bid_price` decimal(8,4) DEFAULT NULL,
  `win_price` decimal(8,4) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `country` varchar(3) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `status` enum('request','response','win','loss') DEFAULT 'request',
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `zone_id` (`zone_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campaign Statistics Table
CREATE TABLE `campaign_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `campaign_type` enum('rtb','ron') NOT NULL,
  `date` date NOT NULL,
  `impressions` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0,
  `spend` decimal(10,4) DEFAULT 0.0000,
  `revenue` decimal(10,4) DEFAULT 0.0000,
  `ctr` decimal(6,4) DEFAULT 0.0000,
  `cpm` decimal(8,4) DEFAULT 0.0000,
  `cpc` decimal(8,4) DEFAULT 0.0000,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_date` (`campaign_id`,`campaign_type`,`date`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Insert Reference Data
-- --------------------------------------------------------

-- Insert default categories
INSERT INTO `categories` (`name`, `slug`, `type`, `description`) VALUES
('Adult Entertainment', 'adult', 'adult', 'Adult content and entertainment'),
('News & Politics', 'news', 'mainstream', 'News, politics and current affairs'),
('Entertainment', 'entertainment', 'mainstream', 'Movies, TV, celebrities and entertainment'),
('Sports', 'sports', 'mainstream', 'Sports news, results and analysis'),
('Technology', 'technology', 'mainstream', 'Technology, software and gadgets'),
('Lifestyle', 'lifestyle', 'mainstream', 'Lifestyle, health and wellness'),
('Business', 'business', 'mainstream', 'Business, finance and economics'),
('Gaming', 'gaming', 'mainstream', 'Video games and gaming content'),
('Travel', 'travel', 'mainstream', 'Travel, tourism and destinations'),
('Education', 'education', 'mainstream', 'Education and learning resources');

-- Insert common banner sizes
INSERT INTO `banner_sizes` (`name`, `width`, `height`, `category`, `iab_standard`) VALUES
('Medium Rectangle', 300, 250, 'rectangle', 1),
('Mobile Banner', 300, 100, 'mobile', 1),  
('Mobile Banner Small', 300, 50, 'mobile', 1),
('Large Mobile Banner', 300, 500, 'mobile', 0),
('Super Banner', 900, 250, 'banner', 0),
('Leaderboard', 728, 90, 'banner', 1),
('Wide Skyscraper', 160, 600, 'skyscraper', 1),
('Large Rectangle', 336, 280, 'rectangle', 1),
('Square', 250, 250, 'square', 1),
('Small Square', 200, 200, 'square', 1),
('Button 1', 120, 90, 'banner', 1),
('Button 2', 120, 60, 'banner', 1),
('Micro Bar', 88, 31, 'banner', 1),
('Billboard', 970, 250, 'banner', 1),
('Portrait', 300, 1050, 'mobile', 0);

-- Insert common countries
INSERT INTO `countries` (`code`, `name`, `continent`) VALUES
('US', 'United States', 'North America'),
('CA', 'Canada', 'North America'),
('GB', 'United Kingdom', 'Europe'),
('AU', 'Australia', 'Oceania'),
('DE', 'Germany', 'Europe'),
('FR', 'France', 'Europe'),
('IT', 'Italy', 'Europe'),
('ES', 'Spain', 'Europe'),
('NL', 'Netherlands', 'Europe'),
('BR', 'Brazil', 'South America'),
('MX', 'Mexico', 'North America'),
('IN', 'India', 'Asia'),
('ID', 'Indonesia', 'Asia'),
('MY', 'Malaysia', 'Asia'),
('SG', 'Singapore', 'Asia'),
('TH', 'Thailand', 'Asia'),
('VN', 'Vietnam', 'Asia'),
('PH', 'Philippines', 'Asia'),
('JP', 'Japan', 'Asia'),
('KR', 'South Korea', 'Asia');

-- Insert common browsers
INSERT INTO `browsers` (`name`, `slug`) VALUES
('Google Chrome', 'chrome'),
('Mozilla Firefox', 'firefox'),
('Safari', 'safari'),
('Microsoft Edge', 'edge'),
('Opera', 'opera'),
('Internet Explorer', 'ie'),
('Samsung Internet', 'samsung'),
('UC Browser', 'uc');

-- Insert common devices
INSERT INTO `devices` (`name`, `slug`, `category`) VALUES
('Desktop', 'desktop', 'desktop'),
('Mobile Phone', 'mobile', 'mobile'),
('Tablet', 'tablet', 'tablet'),
('Smart TV', 'smart_tv', 'smart_tv'),
('Gaming Console', 'console', 'console');

-- Insert common operating systems
INSERT INTO `operating_systems` (`name`, `slug`, `category`) VALUES
('Windows', 'windows', 'desktop'),
('macOS', 'macos', 'desktop'),
('Linux', 'linux', 'desktop'),
('Android', 'android', 'mobile'),
('iOS', 'ios', 'mobile'),
('iPadOS', 'ipados', 'mobile'),
('Chrome OS', 'chromeos', 'desktop'),
('Windows Mobile', 'winmobile', 'mobile');

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `user_type`, `status`) VALUES
('admin', 'admin@adserver.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 'active');

COMMIT;