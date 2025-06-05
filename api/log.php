<?php
/**
 * Event Logging API
 * Handles impression, click, and conversion tracking
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
require_once '../includes/anti_fraud.php';

$db = Database::getInstance();
$antiFraud = new AntiFraud();

// Get parameters
$event = sanitize($_GET['event'] ?? $_POST['event'] ?? '');
$zoneId = (int)($_GET['zone'] ?? $_POST['zone'] ?? 0);
$campaignId = (int)($_GET['campaign'] ?? $_POST['campaign'] ?? 0);
$creativeId = (int)($_GET['creative'] ?? $_POST['creative'] ?? 0);
$price = (float)($_GET['price'] ?? $_POST['price'] ?? 0);

// Validate parameters
if (!in_array($event, ['impression', 'click', 'conversion'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event type']);
    exit;
}

if (!$zoneId && !$campaignId) {
    http_response_code(400);
    echo json_encode(['error' => 'Zone ID or Campaign ID required']);
    exit;
}

try {
    // Collect request data
    $requestData = [
        'event_type' => $event,
        'zone_id' => $zoneId ?: null,
        'campaign_id' => $campaignId ?: null,
        'creative_id' => $creativeId ?: null,
        'ip_address' => getClientIp(),
        'user_agent' => getUserAgent(),
        'referer' => getReferer(),
        'country' => getCountryFromIp(getClientIp()),
        'device_type' => detectDeviceType(),
        'revenue' => 0,
        'cost' => 0
    ];
    
    // Get zone/campaign info
    if ($zoneId) {
        $zone = $db->fetch(
            "SELECT z.*, s.user_id FROM zones z 
             JOIN sites s ON z.site_id = s.id 
             WHERE z.id = ?",
            [$zoneId]
        );
        
        if ($zone) {
            $requestData['site_id'] = $zone['site_id'];
            $requestData['user_id'] = $zone['user_id'];
        }
    } elseif ($campaignId) {
        $campaign = $db->fetch(
            "SELECT user_id FROM campaigns WHERE id = ?",
            [$campaignId]
        );
        
        if ($campaign) {
            $requestData['user_id'] = $campaign['user_id'];
        }
    }
    
    // Anti-fraud check for clicks and conversions
    if ($event !== 'impression') {
        $fraudCheck = $antiFraud->checkTraffic($requestData);
        
        if ($fraudCheck['is_fraud']) {
            logMessage("Fraud detected for {$event}: " . implode(', ', $fraudCheck['reasons']), 'WARNING');
            
            // Return success but don't log the event
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
    }
    
    // Calculate revenue/cost based on event type
    switch ($event) {
        case 'click':
            if ($price > 0) {
                $requestData['cost'] = $price;
                $requestData['revenue'] = $price * 0.8; // 80% to publisher
            }
            break;
            
        case 'conversion':
            if ($price > 0) {
                $requestData['cost'] = $price;
                $requestData['revenue'] = $price * 0.8;
            }
            break;
    }
    
    // Log the event
    $db->insert('tracking_events', $requestData);
    
    // Update user balances if there's revenue/cost
    if ($requestData['revenue'] > 0 && $requestData['user_id']) {
        // Publisher earning
        $db->query(
            "UPDATE users SET balance = balance + ? WHERE id = ?",
            [$requestData['revenue'], $requestData['user_id']]
        );
        
        // Log transaction
        $publisherBalance = $db->fetch(
            "SELECT balance FROM users WHERE id = ?",
            [$requestData['user_id']]
        );
        
        $db->insert('transactions', [
            'user_id' => $requestData['user_id'],
            'transaction_type' => TRANSACTION_TYPE_EARNING,
            'amount' => $requestData['revenue'],
            'balance_before' => $publisherBalance['balance'] - $requestData['revenue'],
            'balance_after' => $publisherBalance['balance'],
            'description' => ucfirst($event) . ' revenue',
            'reference_type' => 'zone',
            'reference_id' => $zoneId
        ]);
    }
    
    if ($requestData['cost'] > 0 && $campaignId) {
        // Advertiser spending
        $campaign = $db->fetch("SELECT user_id FROM campaigns WHERE id = ?", [$campaignId]);
        
        if ($campaign) {
            $db->query(
                "UPDATE users SET balance = balance - ? WHERE id = ?",
                [$requestData['
