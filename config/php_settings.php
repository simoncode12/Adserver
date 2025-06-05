<?php
/**
 * PHP Runtime Configuration
 * Settings that would normally be in .htaccess php_value directives
 */

// Set PHP configuration at runtime
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '30');

// Default error reporting (will be overridden later if DEBUG_MODE is defined)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 7200); // Default 2 hours, will be overridden

// File upload security
ini_set('file_uploads', 1);
ini_set('upload_tmp_dir', sys_get_temp_dir());

// Other security settings
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
?>
