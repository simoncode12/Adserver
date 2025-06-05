<?php
/**
 * Ad Serving API
 * Handles ad requests and serves ads via RTB or fallback
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/openrtb.php';
require_once '../includes/anti_fraud.php';

$db = Database::getInstance();
$rtb = new OpenRTB();
$antiFraud = new AntiFraud();

// Get parameters
$zoneId = (int)($_GET['zone'] ?? $_POST['zone'] ?? 0);
$format = sanitize($_GET['format'] ?? $_POST['format'] ?? 'json');
$callback = sanitize($_GET['callback'] ?? '');

// Validate zone
if (!$zoneId) {
    http_response_code(400);
    echo json_encode(['error' => 'Zone ID required']);
    exit;
}

try {
    // Get zone info
    $zone = $db->fetch(
        "SELECT z.*, s.url as site_url, s.user_id, af.slug as format_slug
         FROM zones z
         JOIN sites s ON z.site_id = s.id
         JOIN ad_formats af ON z.ad_format_id = af.id
         WHERE z.id = ? AND z.status = 'active' AND s.status = 'active'",
        [$zoneId]
    );
    
    if (!$zone) {
        http_response_code(404);
        echo json_encode(['error' => 'Zone not found or inactive']);
        exit;
    }
    
    // Collect request data
    $requestData = [
        'zone_id' => $zoneId,
        'site_id' => $zone['site_id'],
        'ip' => getClientIp(),
        'user_agent' => getUserAgent(),
        'referer' => getReferer(),
        'country' => getCountryFromIp(getClientIp()),
        'device_type' => detectDeviceType(),
        'format' => $zone['format_slug']
    ];
    
    // Handle RTB request
    $adResponse = $rtb->handleBidRequest($zoneId, $requestData);
    
    if ($adResponse['success']) {
        // Log impression
        $eventData = [
            'event_type' => EVENT_TYPE_IMPRESSION,
            'zone_id' => $zoneId,
            'site_id' => $zone['site_id'],
            'user_id' => $zone['user_id'],
            'ip_address' => $requestData['ip'],
            'user_agent' => $requestData['user_agent'],
            'referer' => $requestData['referer'],
            'country' => $requestData['country'],
            'device_type' => $requestData['device_type'],
            'revenue' => isset($adResponse['is_fallback']) ? 0 : $adResponse['price'] * 0.8, // 80% to publisher
            'cost' => isset($adResponse['is_fallback']) ? 0 : $adResponse['price']
        ];
        
        $db->insert('tracking_events', $eventData);
        
        // Format response based on requested format
        switch ($format) {
            case 'js':
            case 'javascript':
                header('Content-Type: application/javascript');
                echo generateJavaScriptAd($adResponse, $zoneId);
                break;
                
            case 'iframe':
                header('Content-Type: text/html');
                echo generateIframeAd($adResponse, $zoneId);
                break;
                
            case 'async':
                header('Content-Type: application/javascript');
                echo generateAsyncAd($adResponse, $zoneId);
                break;
                
            case 'jsonp':
                header('Content-Type: application/javascript');
                $jsonResponse = json_encode($adResponse);
                echo $callback ? "{$callback}({$jsonResponse});" : $jsonResponse;
                break;
                
            default:
                header('Content-Type: application/json');
                echo json_encode($adResponse);
                break;
        }
    } else {
        // No ad available
        switch ($format) {
            case 'js':
            case 'javascript':
                header('Content-Type: application/javascript');
                echo "// No ad available";
                break;
                
            case 'iframe':
                header('Content-Type: text/html');
                echo "<!-- No ad available -->";
                break;
                
            case 'async':
                header('Content-Type: application/javascript');
                echo "// No ad available";
                break;
                
            default:
                http_response_code(204);
                echo json_encode(['error' => 'No ads available']);
                break;
        }
    }
    
} catch (Exception $e) {
    logMessage("Ad serving error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Generate JavaScript ad code
 */
function generateJavaScriptAd($adResponse, $zoneId) {
    $adContent = addslashes($adResponse['ad_content']);
    $clickUrl = addslashes($adResponse['click_url']);
    $impressionUrl = AD_SERVER_URL . "/api/log.php?event=impression&zone={$zoneId}";
    $clickTrackUrl = AD_SERVER_URL . "/api/log.php?event=click&zone={$zoneId}";
    
    return "
(function() {
    var adContainer = document.createElement('div');
    adContainer.innerHTML = '{$adContent}';
    adContainer.style.cursor = 'pointer';
    
    // Track impression
    var impPixel = new Image();
    impPixel.src = '{$impressionUrl}&t=' + Date.now();
    
    // Add click tracking
    adContainer.onclick = function() {
        var clickPixel = new Image();
        clickPixel.src = '{$clickTrackUrl}&t=' + Date.now();
        setTimeout(function() {
            window.open('{$clickUrl}', '_blank');
        }, 100);
    };
    
    document.write(adContainer.outerHTML);
})();
";
}

/**
 * Generate iframe ad code
 */
function generateIframeAd($adResponse, $zoneId) {
    $adContent = $adResponse['ad_content'];
    $clickUrl = htmlspecialchars($adResponse['click_url']);
    $width = $adResponse['width'] ?? 300;
    $height = $adResponse['height'] ?? 250;
    
    return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        .ad-container { width: {$width}px; height: {$height}px; cursor: pointer; }
    </style>
</head>
<body>
    <div class='ad-container' onclick='window.open(\"{$clickUrl}\", \"_blank\");'>
        {$adContent}
    </div>
    <script>
        // Track impression
        var impPixel = new Image();
        impPixel.src = '" . AD_SERVER_URL . "/api/log.php?event=impression&zone={$zoneId}&t=' + Date.now();
    </script>
</body>
</html>";
}

/**
 * Generate async ad code
 */
function generateAsyncAd($adResponse, $zoneId) {
    $adContent = addslashes($adResponse['ad_content']);
    $clickUrl = addslashes($adResponse['click_url']);
    
    return "
(function() {
    var targetEl = document.getElementById('ad-zone-{$zoneId}');
    if (targetEl) {
        targetEl.innerHTML = '{$adContent}';
        targetEl.style.cursor = 'pointer';
        targetEl.onclick = function() {
            window.open('{$clickUrl}', '_blank');
        };
        
        // Track impression
        var impPixel = new Image();
        impPixel.src = '" . AD_SERVER_URL . "/api/log.php?event=impression&zone={$zoneId}&t=' + Date.now();
    }
})();
";
}
?>
