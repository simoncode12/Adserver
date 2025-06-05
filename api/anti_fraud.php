<?php
/**
 * Anti-Fraud API
 * Real-time fraud detection service
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/anti_fraud.php';

$antiFraud = new AntiFraud();

// Get parameters
$ip = sanitize($_GET['ip'] ?? $_POST['ip'] ?? getClientIp());
$userAgent = sanitize($_GET['user_agent'] ?? $_POST['user_agent'] ?? getUserAgent());
$referer = sanitize($_GET['referer'] ?? $_POST['referer'] ?? getReferer());
$zoneId = (int)($_GET['zone'] ?? $_POST['zone'] ?? 0);
$siteId = (int)($_GET['site'] ?? $_POST['site'] ?? 0);

try {
    // Perform fraud check
    $result = $antiFraud->checkTraffic([
        'ip' => $ip,
        'user_agent' => $userAgent,
        'referer' => $referer,
        'zone_id' => $zoneId,
        'site_id' => $siteId
    ]);
    
    // Add additional information
    $result['ip'] = $ip;
    $result['country'] = getCountryFromIp($ip);
    $result['device_type'] = detectDeviceType($userAgent);
    $result['timestamp'] = time();
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logMessage("Anti-fraud API error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Service unavailable']);
}
?>
