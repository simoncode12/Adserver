<?php
/**
 * Helper Functions
 * AdServer Platform Common Helper Functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate API key
 */
function generateApiKey() {
    return 'ak_' . generateToken(32);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'USD') {
    $symbol = '$';
    switch ($currency) {
        case 'EUR': $symbol = '€'; break;
        case 'GBP': $symbol = '£'; break;
    }
    return $symbol . number_format($amount, 2);
}

/**
 * Format percentage
 */
function formatPercentage($value, $decimals = 2) {
    return number_format($value, $decimals) . '%';
}

/**
 * Format number with abbreviations
 */
function formatNumber($number) {
    if ($number >= 1000000000) {
        return number_format($number / 1000000000, 1) . 'B';
    } elseif ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

/**
 * Get client IP address
 */
function getClientIp() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Get referer
 */
function getReferer() {
    return $_SERVER['HTTP_REFERER'] ?? '';
}

/**
 * Detect device type
 */
function detectDeviceType($userAgent = null) {
    if (!$userAgent) {
        $userAgent = getUserAgent();
    }
    
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone', 'BlackBerry'];
    $tabletKeywords = ['iPad', 'Android.*Tablet', 'Kindle', 'Silk', 'PlayBook'];
    
    foreach ($tabletKeywords as $keyword) {
        if (preg_match("/$keyword/i", $userAgent)) {
            return DEVICE_TYPE_TABLET;
        }
    }
    
    foreach ($mobileKeywords as $keyword) {
        if (preg_match("/$keyword/i", $userAgent)) {
            return DEVICE_TYPE_MOBILE;
        }
    }
    
    return DEVICE_TYPE_DESKTOP;
}

/**
 * Get country from IP
 */
function getCountryFromIp($ip) {
    // Basic country detection - can be enhanced with GeoIP database
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['countryCode'])) {
            return $data['countryCode'];
        }
    }
    
    return 'US'; // Default to US
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'INFO', $file = null) {
    if (!$file) {
        $file = ERROR_LOG_FILE;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    
    file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Clean old files from directory
 */
function cleanOldFiles($directory, $maxAge = 86400) {
    if (!is_dir($directory)) {
        return;
    }
    
    $files = glob($directory . '/*');
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = getFileExtension($originalName);
    $uniqueName = uniqid() . '_' . time();
    return $extension ? "{$uniqueName}.{$extension}" : $uniqueName;
}

/**
 * Calculate CTR (Click-Through Rate)
 */
function calculateCTR($clicks, $impressions) {
    return $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
}

/**
 * Calculate RPM (Revenue Per Mille)
 */
function calculateRPM($revenue, $impressions) {
    return $impressions > 0 ? ($revenue / $impressions) * 1000 : 0;
}

/**
 * Calculate eCPM (Effective Cost Per Mille)
 */
function calculateECPM($cost, $impressions) {
    return $impressions > 0 ? ($cost / $impressions) * 1000 : 0;
}

/**
 * Validate JSON
 */
function isValidJSON($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Convert array to XML
 */
function arrayToXml($array, $rootElement = 'root', $xml = null) {
    if ($xml === null) {
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
    }
    
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            arrayToXml($value, $key, $xml->addChild($key));
        } else {
            $xml->addChild($key, htmlspecialchars($value));
        }
    }
    
    return $xml->asXML();
}

/**
 * Validate OpenRTB bid request
 */
function validateOpenRTBRequest($request) {
    if (!isset($request['id']) || !isset($request['imp'])) {
        return false;
    }
    
    if (!is_array($request['imp']) || empty($request['imp'])) {
        return false;
    }
    
    foreach ($request['imp'] as $imp) {
        if (!isset($imp['id'])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Generate bid response
 */
function generateBidResponse($bidRequest, $seatBid = []) {
    return [
        'id' => $bidRequest['id'],
        'seatbid' => $seatBid,
        'bidid' => generateToken(16),
        'cur' => ['USD'],
        'nbr' => empty($seatBid) ? 0 : null
    ];
}

/**
 * Rate limiting check
 */
function checkRateLimit($identifier, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW) {
    $key = "rate_limit_{$identifier}";
    $current = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];
    
    if (time() - $current['start'] > $window) {
        $current = ['count' => 1, 'start' => time()];
    } else {
        $current['count']++;
    }
    
    $_SESSION[$key] = $current;
    
    return $current['count'] <= $limit;
}

/**
 * Validate creative content
 */
function validateCreative($content, $type) {
    switch ($type) {
        case CREATIVE_TYPE_HTML:
            return !preg_match('/<script|javascript:|vbscript:/i', $content);
        case CREATIVE_TYPE_IMAGE:
            return validateUrl($content);
        case CREATIVE_TYPE_VIDEO:
            return validateUrl($content);
        default:
            return true;
    }
}

/**
 * Clean HTML content
 */
function cleanHtml($html) {
    $allowed_tags = '<a><img><div><span><p><br><b><i><u><strong><em>';
    return strip_tags($html, $allowed_tags);
}

/**
 * Generate ad tag
 */
function generateAdTag($zoneId, $format = 'javascript') {
    $adServerUrl = AD_SERVER_URL;
    
    switch ($format) {
        case 'javascript':
            return "<script src='{$adServerUrl}/api/serve.php?zone={$zoneId}&format=js'></script>";
        case 'iframe':
            return "<iframe src='{$adServerUrl}/api/serve.php?zone={$zoneId}&format=iframe' width='300' height='250' frameborder='0'></iframe>";
        case 'async':
            return "<div id='ad-zone-{$zoneId}'></div><script>
                (function() {
                    var script = document.createElement('script');
                    script.src = '{$adServerUrl}/api/serve.php?zone={$zoneId}&format=async';
                    script.async = true;
                    document.head.appendChild(script);
                })();
            </script>";
        default:
            return "<script src='{$adServerUrl}/api/serve.php?zone={$zoneId}'></script>";
    }
}
?>
