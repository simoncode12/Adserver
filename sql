-- Database creation
CREATE DATABASE IF NOT EXISTS rtb_adserver CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rtb_adserver;

-- Users table (admin, publisher, advertiser)
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role ENUM('admin', 'publisher', 'advertiser') NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL, -- store hashed password
    email VARCHAR(255) NOT NULL,
    status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'pending',
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    company VARCHAR(255),
    account_balance DECIMAL(15, 4) DEFAULT 0.0000,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username_unique (username),
    UNIQUE KEY email_unique (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Publishers table
CREATE TABLE publishers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    website_name VARCHAR(255) NOT NULL,
    website_category VARCHAR(255),
    website_description TEXT,
    status ENUM('active', 'pending', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    payout_method VARCHAR(255),
    payout_details TEXT,
    min_payout DECIMAL(10, 2) DEFAULT 50.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY website_url_unique (website_url),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    CONSTRAINT fk_publishers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Ad Zones table
CREATE TABLE ad_zones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    publisher_id BIGINT UNSIGNED NOT NULL,
    zone_name VARCHAR(255) NOT NULL,
    zone_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    width INT UNSIGNED,
    height INT UNSIGNED,
    rtb_url TEXT, -- URL for real-time bidding
    rtb_enabled TINYINT(1) DEFAULT 1,
    embed_code TEXT, -- Generated code for publishers to embed
    fallback_ad TEXT, -- Fallback ad if no RTB bids
    fallback_url VARCHAR(255),
    floor_price DECIMAL(10, 4) DEFAULT 0.0000, -- Minimum CPM price
    max_refresh_rate INT UNSIGNED DEFAULT 0, -- Max refreshes per impression (0 = unlimited)
    status ENUM('active', 'paused', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_zone_type (zone_type),
    INDEX idx_status (status),
    CONSTRAINT fk_zones_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Advertisers table
CREATE TABLE advertisers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    industry VARCHAR(255),
    budget DECIMAL(15, 2) DEFAULT 0.00,
    billing_address TEXT,
    payment_method VARCHAR(255),
    payment_details TEXT,
    status ENUM('active', 'pending', 'suspended') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    CONSTRAINT fk_advertisers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Campaigns table
CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    campaign_name VARCHAR(255) NOT NULL,
    campaign_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    targeting_criteria JSON, -- Store detailed targeting as JSON
    targeting_geos TEXT, -- Comma-separated country codes
    targeting_devices TEXT, -- desktop, mobile, tablet
    targeting_browsers TEXT, -- chrome, firefox, safari, etc.
    targeting_os TEXT, -- windows, macos, android, ios, etc.
    targeting_languages TEXT, -- en, es, fr, etc.
    targeting_hours TEXT, -- comma-separated hour ranges
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    daily_budget DECIMAL(10, 2),
    total_budget DECIMAL(15, 2),
    bid_type ENUM('cpm', 'cpc', 'cpa') DEFAULT 'cpm',
    bid_amount DECIMAL(10, 4) NOT NULL, -- Bid amount (CPM, CPC, CPA)
    frequency_cap INT UNSIGNED DEFAULT 0, -- 0 = unlimited
    frequency_interval INT UNSIGNED DEFAULT 86400, -- in seconds, default 24h
    pacing ENUM('standard', 'accelerated') DEFAULT 'standard',
    status ENUM('active', 'paused', 'completed', 'pending', 'deleted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_advertiser_id (advertiser_id),
    INDEX idx_campaign_type (campaign_type),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    CONSTRAINT fk_campaigns_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Ads table
CREATE TABLE ads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    ad_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    ad_name VARCHAR(255) NOT NULL,
    ad_content TEXT, -- HTML content or path to creative
    banner_url VARCHAR(255), -- URL for banner image
    vast_url TEXT, -- VAST URL for video ads
    click_url TEXT, -- Landing page URL
    impression_trackers TEXT, -- Optional, comma-separated list of tracking pixels
    click_trackers TEXT, -- Optional, comma-separated list of click trackers
    width INT UNSIGNED,
    height INT UNSIGNED,
    status ENUM('active', 'pending_review', 'rejected', 'paused') DEFAULT 'pending_review',
    rejection_reason TEXT,
    weight INT UNSIGNED DEFAULT 1, -- For ad rotation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_ad_type (ad_type),
    INDEX idx_status (status),
    CONSTRAINT fk_ads_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Statistics table (hourly granularity)
CREATE TABLE statistics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    hour TINYINT UNSIGNED NOT NULL, -- 0-23
    ad_zone_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    ad_id BIGINT UNSIGNED NOT NULL,
    publisher_id BIGINT UNSIGNED NOT NULL,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    country VARCHAR(2), -- ISO country code
    device_type ENUM('desktop', 'mobile', 'tablet'),
    browser VARCHAR(50),
    operating_system VARCHAR(50),
    requests INT UNSIGNED DEFAULT 0, -- RTB requests
    impressions INT UNSIGNED DEFAULT 0,
    clicks INT UNSIGNED DEFAULT 0,
    conversions INT UNSIGNED DEFAULT 0,
    revenue DECIMAL(15, 6) DEFAULT 0.000000, -- Publisher revenue
    cost DECIMAL(15, 6) DEFAULT 0.000000, -- Advertiser cost
    profit DECIMAL(15, 6) DEFAULT 0.000000, -- Platform profit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY stats_unique (date, hour, ad_zone_id, campaign_id, ad_id, country, device_type, browser, operating_system),
    INDEX idx_date (date),
    INDEX idx_hour (hour),
    INDEX idx_ad_zone_id (ad_zone_id),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_ad_id (ad_id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_advertiser_id (advertiser_id),
    INDEX idx_country (country),
    INDEX idx_device_type (device_type),
    CONSTRAINT fk_stats_ad_zone FOREIGN KEY (ad_zone_id) REFERENCES ad_zones (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stats_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stats_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stats_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stats_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Daily Statistics (aggregated for faster reporting)
CREATE TABLE daily_statistics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    ad_zone_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    ad_id BIGINT UNSIGNED NOT NULL,
    publisher_id BIGINT UNSIGNED NOT NULL,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    country VARCHAR(2), -- ISO country code
    device_type ENUM('desktop', 'mobile', 'tablet'),
    requests INT UNSIGNED DEFAULT 0,
    impressions INT UNSIGNED DEFAULT 0,
    clicks INT UNSIGNED DEFAULT 0,
    conversions INT UNSIGNED DEFAULT 0,
    revenue DECIMAL(15, 6) DEFAULT 0.000000,
    cost DECIMAL(15, 6) DEFAULT 0.000000,
    profit DECIMAL(15, 6) DEFAULT 0.000000,
    ctr DECIMAL(10, 6) DEFAULT 0.000000, -- Click-through rate
    cvr DECIMAL(10, 6) DEFAULT 0.000000, -- Conversion rate
    ecpm DECIMAL(10, 6) DEFAULT 0.000000, -- Effective CPM
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY daily_stats_unique (date, ad_zone_id, campaign_id, ad_id, country, device_type),
    INDEX idx_date (date),
    INDEX idx_ad_zone_id (ad_zone_id),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_ad_id (ad_id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_advertiser_id (advertiser_id),
    INDEX idx_country (country),
    INDEX idx_device_type (device_type),
    CONSTRAINT fk_daily_stats_ad_zone FOREIGN KEY (ad_zone_id) REFERENCES ad_zones (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_stats_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_stats_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_stats_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_daily_stats_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- RTB Bid Requests Log
CREATE TABLE rtb_bid_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_zone_id BIGINT UNSIGNED NOT NULL,
    publisher_id BIGINT UNSIGNED NOT NULL,
    request_id VARCHAR(255) NOT NULL, -- Unique request ID
    request_data JSON, -- Full OpenRTB request
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer TEXT,
    country VARCHAR(2),
    device_type ENUM('desktop', 'mobile', 'tablet'),
    browser VARCHAR(50),
    operating_system VARCHAR(50),
    is_bot TINYINT(1) DEFAULT 0,
    request_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_time INT UNSIGNED, -- Response time in milliseconds
    winning_bid_price DECIMAL(10, 6) DEFAULT 0.000000,
    winning_campaign_id BIGINT UNSIGNED,
    winning_ad_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_request_id (request_id),
    INDEX idx_ad_zone_id (ad_zone_id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_country (country),
    INDEX idx_device_type (device_type),
    INDEX idx_request_timestamp (request_timestamp),
    INDEX idx_winning_campaign_id (winning_campaign_id),
    INDEX idx_winning_ad_id (winning_ad_id),
    INDEX idx_is_bot (is_bot),
    CONSTRAINT fk_rtb_requests_ad_zone FOREIGN KEY (ad_zone_id) REFERENCES ad_zones (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rtb_requests_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Payments to Publishers
CREATE TABLE publisher_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    publisher_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 4) NOT NULL,
    method VARCHAR(255) NOT NULL,
    transaction_id VARCHAR(255),
    status ENUM('pending', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    payment_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date),
    CONSTRAINT fk_payments_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Payments from Advertisers
CREATE TABLE advertiser_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 4) NOT NULL,
    method VARCHAR(255) NOT NULL,
    transaction_id VARCHAR(255),
    status ENUM('pending', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    payment_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_advertiser_id (advertiser_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date),
    CONSTRAINT fk_payments_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- API Keys for RTB integrations
CREATE TABLE api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    permissions JSON, -- Stored as JSON array of permissions
    ip_whitelist TEXT, -- Comma-separated list of allowed IPs
    is_active TINYINT(1) DEFAULT 1,
    last_used DATETIME,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY api_key_unique (api_key),
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- System Settings
CREATE TABLE system_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_name VARCHAR(255) NOT NULL,
    setting_value TEXT,
    description TEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_name_unique (setting_name)
) ENGINE=InnoDB;

-- Initial system settings
INSERT INTO system_settings (setting_name, setting_value, description, is_public)
VALUES 
    ('site_name', 'RTB AdServer', 'Site name displayed in browser title', 1),
    ('min_bid_amount', '0.01', 'Minimum bid amount (CPM)', 1),
    ('platform_fee_percentage', '20', 'Platform fee percentage', 0),
    ('min_publisher_payment', '50', 'Minimum publisher payment amount', 1),
    ('default_currency', 'USD', 'Default currency for the platform', 1);

-- Stored Procedure for Daily Statistics Aggregation
DELIMITER //

CREATE PROCEDURE aggregate_daily_statistics(IN p_date DATE)
BEGIN
    -- Delete existing aggregated data for the date if any
    DELETE FROM daily_statistics WHERE date = p_date;
    
    -- Insert aggregated data
    INSERT INTO daily_statistics (
        date, ad_zone_id, campaign_id, ad_id, publisher_id, advertiser_id, 
        country, device_type, requests, impressions, clicks, conversions,
        revenue, cost, profit, ctr, cvr, ecpm, created_at, updated_at
    )
    SELECT 
        date,
        ad_zone_id,
        campaign_id,
        ad_id,
        publisher_id,
        advertiser_id,
        country,
        device_type,
        SUM(requests) AS requests,
        SUM(impressions) AS impressions,
        SUM(clicks) AS clicks,
        SUM(conversions) AS conversions,
        SUM(revenue) AS revenue,
        SUM(cost) AS cost,
        SUM(profit) AS profit,
        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
        CASE WHEN SUM(clicks) > 0 THEN SUM(conversions) / SUM(clicks) ELSE 0 END AS cvr,
        CASE WHEN SUM(impressions) > 0 THEN (SUM(revenue) * 1000) / SUM(impressions) ELSE 0 END AS ecpm,
        NOW(),
        NOW()
    FROM 
        statistics
    WHERE 
        date = p_date
    GROUP BY 
        date, ad_zone_id, campaign_id, ad_id, publisher_id, advertiser_id, country, device_type;
END //

DELIMITER ;

-- Create event to run daily aggregation automatically
CREATE EVENT IF NOT EXISTS daily_stats_aggregation
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 HOUR)
DO
    CALL aggregate_daily_statistics(DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY));
