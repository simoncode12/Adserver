<?php
/**
 * RTB Handler
 * OpenRTB 2.5 Bid Request/Response Handler
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-openrtb-version');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/openrtb.php';
require_once '../includes/anti_fraud.php';

$db = Database::getInstance();
$antiFraud = new AntiFraud();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get raw input
    $input = file_get_contents('php://input');
    $bidRequest = json_decode($input, true);
    
    if (!$bidRequest) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Validate OpenRTB request
    if (!validateOpenRTBRequest($bidRequest)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid bid request format']);
        exit;
    }
    
    // Get endpoint info
    $endpointKey = $_GET['key'] ?? $_POST['key'] ?? '';
    if (!$endpointKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Endpoint key required']);
        exit;
    }
    
    $endpoint = $db->fetch(
        "SELECT * FROM ssp_endpoints WHERE endpoint_key = ? AND status = 'active'",
        [$endpointKey]
    );
    
    if (!$endpoint) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid endpoint key']);
        exit;
    }
    
    // Rate limiting check
    $clientIp = getClientIp();
    if (!checkRateLimit("rtb_{$clientIp}", $endpoint['qps_limit'], 1)) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
    
    // Anti-fraud check
    $fraudCheck = $antiFraud->checkTraffic([
        'ip' => $clientIp,
        'user_agent' => getUserAgent()
    ]);
    
    if ($fraudCheck['is_fraud']) {
        logMessage("RTB fraud detected: " . implode(', ', $fraudCheck['reasons']), 'WARNING');
        http_response_code(204);
        exit;
    }
    
    // Process bid request
    $seatBids = [];
    
    foreach ($bidRequest['imp'] as $imp) {
        $bid = processBidImpression($imp, $bidRequest, $endpoint);
        if ($bid) {
            $seatBids[] = [
                'bid' => [$bid],
                'seat' => 'adstart'
            ];
        }
    }
    
    // Generate bid response
    $bidResponse = generateBidResponse($bidRequest, $seatBids);
    
    // Log RTB request
    $db->insert('rtb_logs', [
        'request_id' => $bidRequest['id'],
        'endpoint_id' => null,
        'request_type' => RTB_REQUEST_BID_REQUEST,
        'ip_address' => $clientIp,
        'user_agent' => getUserAgent(),
        'country' => getCountryFromIp($clientIp),
        'device_type' => detectDeviceType(),
        'status' => empty($seatBids) ? RTB_STATUS_NO_BID : RTB_STATUS_SUCCESS,
        'request_data' => json_encode($bidRequest),
        'response_data' => json_encode($bidResponse)
    ]);
    
    // Update endpoint stats
    $db->query(
        "UPDATE ssp_endpoints SET 
         total_requests = total_requests + 1,
         total_bids = total_bids + ?,
         revenue = revenue + ?
         WHERE id = ?",
        [
            count($seatBids),
            array_sum(array_map(function($sb) { 
                return array_sum(array_map(function($b) { 
                    return $b['price']; 
                }, $sb['bid'])); 
            }, $seatBids)),
            $endpoint['id']
        ]
    );
    
    echo json_encode($bidResponse);
    
} catch (Exception $e) {
    logMessage("RTB handler error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Process individual impression for bidding
 */
function processBidImpression($imp, $bidRequest, $endpoint) {
    global $db;
    
    // Check if we have matching campaigns
    $campaigns = $db->fetchAll(
        "SELECT c.*, cc.* FROM campaigns c
         JOIN campaign_creatives cc ON c.id = cc.campaign_id
         WHERE c.status = 'active' 
         AND cc.status = 'active'
         AND c.bid_amount >= ?
         AND (c.budget_amount = 0 OR c.spent_amount < c.budget_amount)
         ORDER BY c.bid_amount DESC
         LIMIT 10",
        [$endpoint['floor_price']]
    );
    
    if (empty($campaigns)) {
        return null;
    }
    
    // Select best matching campaign
    $selectedCampaign = $campaigns[0];
    
    // Check targeting
    if (!checkTargeting($selectedCampaign, $bidRequest)) {
        return null;
    }
    
    // Generate bid
    $bidPrice = min($selectedCampaign['bid_amount'], $endpoint['floor_price'] * 1.5);
    
    $bid = [
        'id' => generateToken(16),
        'impid' => $imp['id'],
        'price' => $bidPrice,
        'adid' => $selectedCampaign['id'],
        'nurl' => AD_SERVER_URL . "/api/win_notice.php?campaign={$selectedCampaign['id']}&price=\${AUCTION_PRICE}",
        'adm' => $selectedCampaign['content_html'] ?: $selectedCampaign['content_url'],
        'adomain' => [parse_url($selectedCampaign['click_url'], PHP_URL_HOST)],
        'cid' => $selectedCampaign['campaign_id'],
        'crid' => $selectedCampaign['id'],
        'w' => $imp['banner']['w'] ?? 300,
        'h' => $imp['banner']['h'] ?? 250
    ];
    
    return $bid;
}

/**
 * Check campaign targeting
 */
function checkTargeting($campaign, $bidRequest) {
    // Country targeting
    if ($campaign['target_countries']) {
        $targetCountries = json_decode($campaign['target_countries'], true);
        $requestCountry = $bidRequest['device']['geo']['country'] ?? '';
        
        if (!empty($targetCountries) && !in_array($requestCountry, $targetCountries)) {
            return false;
        }
    }
    
    // Device targeting
    if ($campaign['target_devices']) {
        $targetDevices = json_decode($campaign['target_devices'], true);
        $deviceType = $bidRequest['device']['devicetype'] ?? 2;
        
        if (!empty($targetDevices) && !in_array($deviceType, $targetDevices)) {
            return false;
        }
    }
    
    return true;
}
?>
