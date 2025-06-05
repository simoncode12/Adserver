<?php
/**
 * Application Configuration
 * AdServer Platform Main Configuration
 */

// Include PHP runtime settings first
require_once __DIR__ . '/php_settings.php';

// Define application constants
define('APP_NAME', 'AdStart AdServer');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://dasbord.adstart.click');
define('AD_SERVER_URL', 'https://ads.adstart.click');

// Environment settings
define('DEBUG_MODE', false);
define('LOG_LEVEL', 'INFO');

// Security settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// RTB settings
define('RTB_TIMEOUT', 200); // milliseconds
define('RTB_MAX_QPS', 1000);
define('RTB_PROTOCOL_VERSION', '2.5');

// Anti-fraud settings
define('FRAUD_DETECTION_ENABLED', true);
define('FRAUD_CONFIDENCE_THRESHOLD', 0.75);
define('BOT_DETECTION_ENABLED', true);

// File upload settings
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg']);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 300); // 5 minutes

// Email settings (configure as needed)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@adstart.click');
define('FROM_NAME', 'AdStart AdServer');

// Payment settings
define('SUPPORTED_CURRENCIES', ['USD', 'EUR', 'GBP']);
define('DEFAULT_CURRENCY', 'USD');
define('MIN_PAYOUT', 50.00);
define('COMMISSION_RATE', 0.10); // 10%

// Timezone
date_default_timezone_set('UTC');

// Now that DEBUG_MODE is defined, set proper error reporting
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Update session lifetime now that it's defined
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
