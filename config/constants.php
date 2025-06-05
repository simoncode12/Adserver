<?php
/**
 * Application Constants
 * AdServer Platform System Constants
 */

// User types
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_PUBLISHER', 'publisher');
define('USER_TYPE_ADVERTISER', 'advertiser');

// User statuses
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_PENDING', 'pending');
define('USER_STATUS_SUSPENDED', 'suspended');

// Campaign statuses
define('CAMPAIGN_STATUS_ACTIVE', 'active');
define('CAMPAIGN_STATUS_PAUSED', 'paused');
define('CAMPAIGN_STATUS_COMPLETED', 'completed');
define('CAMPAIGN_STATUS_PENDING', 'pending');

// Campaign types
define('CAMPAIGN_TYPE_BANNER', 'banner');
define('CAMPAIGN_TYPE_VIDEO', 'video');
define('CAMPAIGN_TYPE_POPUNDER', 'popunder');
define('CAMPAIGN_TYPE_NATIVE', 'native');

// Bid types
define('BID_TYPE_CPM', 'cpm');
define('BID_TYPE_CPC', 'cpc');
define('BID_TYPE_CPA', 'cpa');

// Device types
define('DEVICE_TYPE_DESKTOP', 'desktop');
define('DEVICE_TYPE_MOBILE', 'mobile');
define('DEVICE_TYPE_TABLET', 'tablet');

// Event types
define('EVENT_TYPE_IMPRESSION', 'impression');
define('EVENT_TYPE_CLICK', 'click');
define('EVENT_TYPE_CONVERSION', 'conversion');

// Transaction types
define('TRANSACTION_TYPE_DEPOSIT', 'deposit');
define('TRANSACTION_TYPE_WITHDRAWAL', 'withdrawal');
define('TRANSACTION_TYPE_EARNING', 'earning');
define('TRANSACTION_TYPE_SPENDING', 'spending');
define('TRANSACTION_TYPE_REFUND', 'refund');
define('TRANSACTION_TYPE_COMMISSION', 'commission');

// Payment methods
define('PAYMENT_METHOD_PAYPAL', 'paypal');
define('PAYMENT_METHOD_USDT', 'usdt');
define('PAYMENT_METHOD_VISA', 'visa');
define('PAYMENT_METHOD_BANK_TRANSFER', 'bank_transfer');

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_CANCELLED', 'cancelled');

// RTB endpoint types
define('RTB_ENDPOINT_SSP_OUT', 'ssp_out');
define('RTB_ENDPOINT_DSP_IN', 'dsp_in');

// RTB request types
define('RTB_REQUEST_BID_REQUEST', 'bid_request');
define('RTB_REQUEST_BID_RESPONSE', 'bid_response');
define('RTB_REQUEST_WIN_NOTICE', 'win_notice');
define('RTB_REQUEST_LOSS_NOTICE', 'loss_notice');

// RTB statuses
define('RTB_STATUS_SUCCESS', 'success');
define('RTB_STATUS_TIMEOUT', 'timeout');
define('RTB_STATUS_ERROR', 'error');
define('RTB_STATUS_NO_BID', 'no_bid');

// Fraud types
define('FRAUD_TYPE_BOT', 'bot');
define('FRAUD_TYPE_VPN', 'vpn');
define('FRAUD_TYPE_PROXY', 'proxy');
define('FRAUD_TYPE_TOR', 'tor');
define('FRAUD_TYPE_DATACENTER', 'datacenter');
define('FRAUD_TYPE_SUSPICIOUS_PATTERN', 'suspicious_pattern');

// Blacklist types
define('BLACKLIST_TYPE_IP', 'ip');
define('BLACKLIST_TYPE_IP_RANGE', 'ip_range');
define('BLACKLIST_TYPE_USER_AGENT', 'user_agent');
define('BLACKLIST_TYPE_REFERER', 'referer');
define('BLACKLIST_TYPE_COUNTRY', 'country');

// Creative types
define('CREATIVE_TYPE_IMAGE', 'image');
define('CREATIVE_TYPE_HTML', 'html');
define('CREATIVE_TYPE_VIDEO', 'video');
define('CREATIVE_TYPE_SCRIPT', 'script');

// Notification types
define('NOTIFICATION_TYPE_INFO', 'info');
define('NOTIFICATION_TYPE_WARNING', 'warning');
define('NOTIFICATION_TYPE_ERROR', 'error');
define('NOTIFICATION_TYPE_SUCCESS', 'success');

// Log levels
define('LOG_LEVEL_DEBUG', 'DEBUG');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_CRITICAL', 'CRITICAL');

// API response codes
define('API_SUCCESS', 200);
define('API_BAD_REQUEST', 400);
define('API_UNAUTHORIZED', 401);
define('API_FORBIDDEN', 403);
define('API_NOT_FOUND', 404);
define('API_INTERNAL_ERROR', 500);

// Cache keys
define('CACHE_KEY_SETTINGS', 'app_settings');
define('CACHE_KEY_CATEGORIES', 'categories');
define('CACHE_KEY_BANNER_SIZES', 'banner_sizes');
define('CACHE_KEY_AD_FORMATS', 'ad_formats');
define('CACHE_KEY_USER_STATS', 'user_stats_');
define('CACHE_KEY_SITE_STATS', 'site_stats_');
define('CACHE_KEY_CAMPAIGN_STATS', 'campaign_stats_');

// Directory paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('LOG_DIR', __DIR__ . '/../logs/');
define('TEMPLATE_DIR', __DIR__ . '/../templates/');

// File paths
define('ERROR_LOG_FILE', LOG_DIR . 'error.log');
define('ACCESS_LOG_FILE', LOG_DIR . 'access.log');
define('RTB_LOG_FILE', LOG_DIR . 'rtb.log');
define('FRAUD_LOG_FILE', LOG_DIR . 'fraud.log');

// Regular expressions
define('REGEX_EMAIL', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
define('REGEX_URL', '/^https?:\/\/.+/');
define('REGEX_IP', '/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/');
define('REGEX_IPV6', '/^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/');

// Default values
define('DEFAULT_PAGE_SIZE', 25);
define('DEFAULT_TIMEZONE', 'UTC');
define('DEFAULT_LANGUAGE', 'en');
define('DEFAULT_CURRENCY_SYMBOL', '$');

// File size limits
define('MAX_CREATIVE_SIZE', 5242880); // 5MB
define('MAX_VIDEO_SIZE', 52428800); // 50MB
define('MAX_LOG_SIZE', 104857600); // 100MB

// Rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Feature flags
define('FEATURE_VIDEO_ADS', true);
define('FEATURE_NATIVE_ADS', true);
define('FEATURE_POPUNDER_ADS', true);
define('FEATURE_RTB', true);
define('FEATURE_ANTI_FRAUD', true);
define('FEATURE_REAL_TIME_STATS', true);
define('FEATURE_EMAIL_NOTIFICATIONS', true);
?>
