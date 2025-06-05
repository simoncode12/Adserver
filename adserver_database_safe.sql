-- AdServer Platform Database Schema
-- Complete SQL structure for SSP/DSP AdServer with RTB capabilities
-- Safe version without DROP DATABASE command

USE user_ad;

-- Drop tables if they exist (in correct order to handle foreign key constraints)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS dashboard_stats;
DROP TABLE IF EXISTS blog_posts;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS withdrawal_requests;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS fraud_events;
DROP TABLE IF EXISTS blacklist;
DROP TABLE IF EXISTS rtb_logs;
DROP TABLE IF EXISTS tracking_events;
DROP TABLE IF EXISTS statistics;
DROP TABLE IF EXISTS ssp_endpoints;
DROP TABLE IF EXISTS rtb_endpoints;
DROP TABLE IF EXISTS fallback_campaigns;
DROP TABLE IF EXISTS campaign_creatives;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS zones;
DROP TABLE IF EXISTS sites;
DROP TABLE IF EXISTS ad_formats;
DROP TABLE IF EXISTS banner_sizes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- USER MANAGEMENT TABLES
-- ============================================================================

-- Main users table for all user types (admin, publisher, advertiser)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    user_type ENUM('admin', 'publisher', 'advertiser') NOT NULL,
    status ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    phone VARCHAR(20),
    company_name VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50),
    postal_code VARCHAR(20),
    tax_id VARCHAR(50),
    payment_method ENUM('paypal', 'usdt', 'visa', 'bank_transfer') DEFAULT 'paypal',
    payment_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    api_key VARCHAR(64) UNIQUE,
    balance DECIMAL(15,4) DEFAULT 0.0000,
    INDEX idx_user_type (user_type),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_api_key (api_key)
);

-- User sessions for login management
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_expires (user_id, expires_at)
);

-- ============================================================================
-- CATEGORY AND FORMAT MANAGEMENT
-- ============================================================================

-- Ad categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_status (status)
);

-- Banner sizes
CREATE TABLE banner_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    description VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_size (width, height),
    INDEX idx_status (status)
);

-- Ad formats
CREATE TABLE ad_formats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- ============================================================================
-- SITE AND ZONE MANAGEMENT
-- ============================================================================

-- Publisher sites
CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    status ENUM('active', 'inactive', 'pending', 'rejected') DEFAULT 'pending',
    approval_notes TEXT,
    monthly_pageviews BIGINT DEFAULT 0,
    country VARCHAR(2),
    language VARCHAR(5) DEFAULT 'en',
    adult_content BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_category (category_id)
);

-- Ad zones
CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ad_format_id INT NOT NULL,
    banner_size_id INT,
    zone_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    floor_price DECIMAL(10,4) DEFAULT 0.0000,
    settings JSON,
    tag_code TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_format_id) REFERENCES ad_formats(id),
    FOREIGN KEY (banner_size_id) REFERENCES banner_sizes(id),
    INDEX idx_site (site_id),
    INDEX idx_format (ad_format_id),
    INDEX idx_type (zone_type),
    INDEX idx_status (status)
);

-- ============================================================================
-- CAMPAIGN MANAGEMENT
-- ============================================================================

-- Campaigns (for advertisers)
CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    campaign_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    status ENUM('active', 'paused', 'completed', 'pending') DEFAULT 'pending',
    budget_type ENUM('daily', 'total', 'unlimited') DEFAULT 'daily',
    budget_amount DECIMAL(15,4) DEFAULT 0.0000,
    spent_amount DECIMAL(15,4) DEFAULT 0.0000,
    bid_type ENUM('cpm', 'cpc', 'cpa') DEFAULT 'cpm',
    bid_amount DECIMAL(10,4) NOT NULL,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    target_countries JSON,
    target_categories JSON,
    target_devices JSON,
    target_os JSON,
    target_browsers JSON,
    frequency_cap INT DEFAULT 0,
    adult_content BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_type (campaign_type),
    INDEX idx_dates (start_date, end_date)
);

-- Campaign creatives
CREATE TABLE campaign_creatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    creative_type ENUM('image', 'html', 'video', 'script') NOT NULL,
    content_url VARCHAR(500),
    content_html TEXT,
    content_script TEXT,
    click_url VARCHAR(500) NOT NULL,
    alt_text VARCHAR(100),
    banner_size_id INT,
    file_size INT,
    duration INT,
    status ENUM('active', 'inactive', 'pending', 'rejected') DEFAULT 'pending',
    approval_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (banner_size_id) REFERENCES banner_sizes(id),
    INDEX idx_campaign (campaign_id),
    INDEX idx_status (status),
    INDEX idx_type (creative_type)
);

-- Fallback campaigns (when no RTB bids available)
CREATE TABLE fallback_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    campaign_type ENUM('banner', 'video', 'popunder', 'native') NOT NULL,
    priority INT DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    target_zones JSON,
    target_formats JSON,
    target_sizes JSON,
    creative_content TEXT,
    click_url VARCHAR(500),
    impression_cap INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_type (campaign_type),
    INDEX idx_priority (priority)
);

-- ============================================================================
-- RTB ENDPOINT MANAGEMENT
-- ============================================================================

-- RTB endpoints for buying traffic (SSP OUT)
CREATE TABLE rtb_endpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    endpoint_type ENUM('ssp_out', 'dsp_in') NOT NULL,
    url VARCHAR(500) NOT NULL,
    method ENUM('GET', 'POST') DEFAULT 'POST',
    protocol_version VARCHAR(10) DEFAULT '2.5',
    status ENUM('active', 'inactive', 'testing') DEFAULT 'inactive',
    timeout_ms INT DEFAULT 200,
    qps_limit INT DEFAULT 100,
    auth_type ENUM('none', 'api_key', 'basic', 'bearer') DEFAULT 'none',
    auth_credentials JSON,
    bid_floor DECIMAL(10,4) DEFAULT 0.0000,
    supported_formats JSON,
    supported_sizes JSON,
    country_targeting JSON,
    settings JSON,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    avg_response_time INT DEFAULT 0,
    last_request TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (endpoint_type),
    INDEX idx_status (status),
    INDEX idx_last_request (last_request)
);

-- SSP endpoints for selling traffic (internal endpoints)
CREATE TABLE ssp_endpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    endpoint_key VARCHAR(64) UNIQUE NOT NULL,
    endpoint_url VARCHAR(500) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    allowed_formats JSON,
    floor_price DECIMAL(10,4) DEFAULT 0.0000,
    timeout_ms INT DEFAULT 150,
    country_restrictions JSON,
    ip_whitelist JSON,
    qps_limit INT DEFAULT 1000,
    total_requests BIGINT DEFAULT 0,
    total_bids BIGINT DEFAULT 0,
    total_wins BIGINT DEFAULT 0,
    revenue DECIMAL(15,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_key (endpoint_key)
);

-- ============================================================================
-- STATISTICS AND TRACKING
-- ============================================================================

-- Statistics summary table (daily aggregates)
CREATE TABLE statistics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    user_id INT,
    site_id INT,
    zone_id INT,
    campaign_id INT,
    rtb_endpoint_id INT,
    country VARCHAR(2),
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    os VARCHAR(50),
    browser VARCHAR(50),
    ad_format VARCHAR(20),
    impressions BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    conversions BIGINT DEFAULT 0,
    revenue DECIMAL(15,4) DEFAULT 0.0000,
    cost DECIMAL(15,4) DEFAULT 0.0000,
    requests BIGINT DEFAULT 0,
    fills BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (rtb_endpoint_id) REFERENCES rtb_endpoints(id),
    UNIQUE KEY unique_stat (date, user_id, site_id, zone_id, campaign_id, rtb_endpoint_id, country, device_type),
    INDEX idx_date (date),
    INDEX idx_user_date (user_id, date),
    INDEX idx_site_date (site_id, date),
    INDEX idx_zone_date (zone_id, date),
    INDEX idx_campaign_date (campaign_id, date)
);

-- Real-time tracking (for immediate stats)
CREATE TABLE tracking_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('impression', 'click', 'conversion') NOT NULL,
    user_id INT,
    site_id INT,
    zone_id INT,
    campaign_id INT,
    creative_id INT,
    rtb_endpoint_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    country VARCHAR(2),
    region VARCHAR(50),
    city VARCHAR(50),
    device_type ENUM('desktop', 'mobile', 'tablet'),
    os VARCHAR(50),
    browser VARCHAR(50),
    revenue DECIMAL(10,4) DEFAULT 0.0000,
    cost DECIMAL(10,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (creative_id) REFERENCES campaign_creatives(id),
    FOREIGN KEY (rtb_endpoint_id) REFERENCES rtb_endpoints(id),
    INDEX idx_type_date (event_type, created_at),
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_site_date (site_id, created_at),
    INDEX idx_zone_date (zone_id, created_at),
    INDEX idx_campaign_date (campaign_id, created_at),
    INDEX idx_ip (ip_address)
);

-- RTB request/response logs
CREATE TABLE rtb_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(64) NOT NULL,
    endpoint_id INT,
    request_type ENUM('bid_request', 'bid_response', 'win_notice', 'loss_notice') NOT NULL,
    zone_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    country VARCHAR(2),
    device_type VARCHAR(20),
    floor_price DECIMAL(10,4),
    bid_price DECIMAL(10,4),
    win_price DECIMAL(10,4),
    response_time INT,
    status ENUM('success', 'timeout', 'error', 'no_bid') NOT NULL,
    request_data JSON,
    response_data JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (endpoint_id) REFERENCES rtb_endpoints(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    INDEX idx_request_id (request_id),
    INDEX idx_endpoint_date (endpoint_id, created_at),
    INDEX idx_status_date (status, created_at),
    INDEX idx_type_date (request_type, created_at)
);

-- ============================================================================
-- ANTI-FRAUD AND SECURITY
-- ============================================================================

-- Blacklisted IPs and fraud detection
CREATE TABLE blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('ip', 'ip_range', 'user_agent', 'referer', 'country') NOT NULL,
    value VARCHAR(255) NOT NULL,
    reason VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    auto_detected BOOLEAN DEFAULT FALSE,
    detection_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type_value (type, value),
    INDEX idx_status (status)
);

-- Fraud detection events
CREATE TABLE fraud_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referer VARCHAR(500),
    country VARCHAR(2),
    fraud_type ENUM('bot', 'vpn', 'proxy', 'tor', 'datacenter', 'suspicious_pattern') NOT NULL,
    confidence_score DECIMAL(3,2) DEFAULT 0.00,
    zone_id INT,
    site_id INT,
    blocked BOOLEAN DEFAULT TRUE,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    INDEX idx_ip_date (ip_address, created_at),
    INDEX idx_type_date (fraud_type, created_at),
    INDEX idx_zone_date (zone_id, created_at)
);

-- ============================================================================
-- FINANCIAL MANAGEMENT
-- ============================================================================

-- Financial transactions
CREATE TABLE transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'earning', 'spending', 'refund', 'commission') NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    balance_before DECIMAL(15,4) NOT NULL,
    balance_after DECIMAL(15,4) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    description TEXT,
    reference_id VARCHAR(100),
    reference_type ENUM('campaign', 'zone', 'site', 'manual', 'system'),
    payment_method ENUM('paypal', 'usdt', 'visa', 'bank_transfer'),
    payment_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, transaction_type),
    INDEX idx_status (payment_status),
    INDEX idx_date (created_at),
    INDEX idx_reference (reference_type, reference_id)
);

-- Withdrawal requests
CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    payment_method ENUM('paypal', 'usdt', 'visa', 'bank_transfer') NOT NULL,
    payment_details JSON NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT,
    transaction_id BIGINT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_status_date (status, requested_at)
);

-- ============================================================================
-- SYSTEM SETTINGS AND CONFIGURATION
-- ============================================================================

-- System settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_category (category),
    INDEX idx_public (is_public)
);

-- Activity logs
CREATE TABLE activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_action_date (action, created_at),
    INDEX idx_entity (entity_type, entity_id)
);

-- System notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500),
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_type_date (type, created_at)
);

-- ============================================================================
-- CONTENT MANAGEMENT (BLOG)
-- ============================================================================

-- Blog posts for admin content management
CREATE TABLE blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(500),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    meta_title VARCHAR(255),
    meta_description TEXT,
    tags JSON,
    view_count INT DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    author_id INT NOT NULL,
    FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_status_published (status, published_at),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id)
);

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default admin user (password is 'password')
INSERT INTO users (username, email, password, first_name, last_name, user_type, status, email_verified, api_key) 
VALUES ('admin', 'admin@adstart.click', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 'active', TRUE, 'admin_api_key_123456');

-- Insert default categories
INSERT INTO categories (name, slug, description) VALUES
('Technology', 'technology', 'Technology and IT related content'),
('Entertainment', 'entertainment', 'Entertainment and media content'),
('Business', 'business', 'Business and finance content'),
('Health', 'health', 'Health and wellness content'),
('Sports', 'sports', 'Sports and recreation content'),
('News', 'news', 'News and current events'),
('Education', 'education', 'Educational content'),
('Lifestyle', 'lifestyle', 'Lifestyle and fashion content');

-- Insert default banner sizes
INSERT INTO banner_sizes (name, width, height, description) VALUES
('Leaderboard', 728, 90, 'Standard leaderboard banner'),
('Rectangle', 300, 250, 'Medium rectangle banner'),
('Wide Skyscraper', 160, 600, 'Wide skyscraper banner'),
('Large Rectangle', 336, 280, 'Large rectangle banner'),
('Half Page', 300, 600, 'Half page banner'),
('Square', 250, 250, 'Square banner'),
('Small Square', 200, 200, 'Small square banner'),
('Button', 125, 125, 'Button size banner'),
('Mobile Banner', 320, 50, 'Mobile banner'),
('Mobile Large', 320, 100, 'Large mobile banner');

-- Insert default ad formats
INSERT INTO ad_formats (name, slug, description, settings) VALUES
('Banner Display', 'banner', 'Standard display banner advertisements', '{"allowHtml": true, "allowScripts": false}'),
('Video Preroll', 'video', 'Video advertisements with VAST support', '{"vastCompliant": true, "maxDuration": 30}'),
('Popunder', 'popunder', 'Popunder advertisement format', '{"frequency": 1, "delay": 0}'),
('Native Content', 'native', 'Native content advertisements', '{"allowImages": true, "allowHtml": true}');

-- Insert default system settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('site_name', 'AdStart AdServer', 'string', 'general', 'Site name', TRUE),
('site_description', 'Modern AdServer Platform with RTB capabilities', 'string', 'general', 'Site description', TRUE),
('admin_email', 'admin@adstart.click', 'string', 'general', 'Administrator email', FALSE),
('currency', 'USD', 'string', 'financial', 'Default currency', TRUE),
('min_payout', '50.00', 'decimal', 'financial', 'Minimum payout amount', TRUE),
('rtb_timeout', '200', 'integer', 'rtb', 'RTB timeout in milliseconds', FALSE),
('anti_fraud_enabled', '1', 'boolean', 'security', 'Enable anti-fraud protection', FALSE),
('max_qps', '1000', 'integer', 'performance', 'Maximum queries per second', FALSE),
('debug_mode', '0', 'boolean', 'system', 'Enable debug mode', FALSE);

-- Create additional indexes for performance optimization
CREATE INDEX idx_tracking_events_composite ON tracking_events(created_at, event_type, user_id, site_id);
CREATE INDEX idx_statistics_composite ON statistics(date, user_id, site_id, zone_id);
CREATE INDEX idx_rtb_logs_composite ON rtb_logs(created_at, endpoint_id, status);
CREATE INDEX idx_fraud_events_composite ON fraud_events(created_at, fraud_type, ip_address);

-- Create a view for real-time dashboard statistics
CREATE VIEW dashboard_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
    COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
    COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
    SUM(revenue) as revenue,
    SUM(cost) as cost,
    ROUND(COUNT(CASE WHEN event_type = 'click' THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN event_type = 'impression' THEN 1 END), 0), 2) as ctr,
    ROUND(SUM(revenue) * 1000.0 / NULLIF(COUNT(CASE WHEN event_type = 'impression' THEN 1 END), 0), 2) as rpm
FROM tracking_events 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- ============================================================================
-- TRIGGERS FOR AUTOMATED TASKS
-- ============================================================================

-- Trigger to update user balance on transaction insert
DELIMITER //
CREATE TRIGGER update_user_balance_after_transaction
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    UPDATE users 
    SET balance = NEW.balance_after 
    WHERE id = NEW.user_id;
END//
DELIMITER ;

-- Trigger to log user activity
DELIMITER //
CREATE TRIGGER log_user_login
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.last_login != OLD.last_login AND NEW.last_login IS NOT NULL THEN
        INSERT INTO activity_logs (user_id, action, details) 
        VALUES (NEW.id, 'user_login', JSON_OBJECT('login_time', NEW.last_login));
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- ============================================================================

-- Procedure to aggregate statistics daily
DELIMITER //
CREATE PROCEDURE AggregateStatistics(IN target_date DATE)
BEGIN
    INSERT INTO statistics (
        date, user_id, site_id, zone_id, campaign_id, country, device_type, os, browser, ad_format,
        impressions, clicks, conversions, revenue, cost, requests, fills
    )
    SELECT 
        DATE(created_at) as date,
        user_id, site_id, zone_id, campaign_id, country, device_type, os, browser, 'banner' as ad_format,
        COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
        COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
        SUM(revenue) as revenue,
        SUM(cost) as cost,
        COUNT(*) as requests,
        COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as fills
    FROM tracking_events 
    WHERE DATE(created_at) = target_date
    GROUP BY DATE(created_at), user_id, site_id, zone_id, campaign_id, country, device_type, os, browser
    ON DUPLICATE KEY UPDATE
        impressions = VALUES(impressions),
        clicks = VALUES(clicks),
        conversions = VALUES(conversions),
        revenue = VALUES(revenue),
        cost = VALUES(cost),
        requests = VALUES(requests),
        fills = VALUES(fills),
        updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- Procedure to clean old logs
DELIMITER //
CREATE PROCEDURE CleanOldLogs(IN days_to_keep INT)
BEGIN
    DELETE FROM tracking_events WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM rtb_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM fraud_events WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END//
DELIMITER ;

-- Show table creation summary
SELECT 'Database schema created successfully!' as status;