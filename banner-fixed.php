<?php
/**
 * Fixed RTB Banner Serving - Matching Working Request Format
 */

require_once '../includes/database.php';

$zone_code = $_GET['zone'] ?? '';
$format = $_GET['format'] ?? 'html';
$debug = $_GET['debug'] ?? false;

if (!$zone_code) {
    http_response_code(400);
    exit('Zone required');
}

$db = Database::getInstance();

// Get zone
$zone = $db->fetch("SELECT * FROM ad_zones WHERE zone_code = ? AND zone_type = 'banner' AND status = 'active'", [$zone_code]);
if (!$zone) {
    exit('Zone not found');
}

// Fixed RTB Client
$rtbClient = new FixedRTBClient($db, $debug);
$ad = $rtbClient->getAd($zone, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER'] ?? '');

if (!$ad) {
    if ($debug) {
        echo $rtbClient->getDebugInfo();
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'No RTB ads available',
            'debug' => $rtbClient->getDebugInfo(),
            'zone' => $zone_code,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo '<div style="width:' . $zone['width'] . 'px;height:' . $zone['height'] . 'px;background:#f8d7da;text-align:center;line-height:' . $zone['height'] . 'px;color:#721c24;border:1px solid #f5c6cb;font-size:12px;">No RTB ads</div>';
    }
    exit;
}

// Record impression and render
$rtbClient->recordImpression($ad, $zone);

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'ad' => $ad,
        'zone' => $zone_code,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => $debug ? $rtbClient->getDebugInfo() : null
    ]);
} else {
    header('Content-Type: text/html');
    echo $rtbClient->renderAd($ad, $zone);
}

class FixedRTBClient {
    private $db;
    private $debug;
    private $debugInfo = [];
    
    public function __construct($db, $debug = false) {
        $this->db = $db;
        $this->debug = $debug;
    }
    
    public function getAd($zone, $ip, $user_agent, $referrer) {
        $this->log("=== Fixed RTB Client ===");
        $this->log("Zone: {$zone['zone_code']} ({$zone['width']}x{$zone['height']})");
        $this->log("User IP: $ip");
        $this->log("Time: " . date('Y-m-d H:i:s') . " UTC");
        
        // Get RTB campaigns
        $rtb_campaigns = $this->db->fetchAll("
            SELECT * FROM rtb_campaigns 
            WHERE status = 'active' 
            AND format_type = 'banner'
            AND rtb_url IS NOT NULL 
            AND rtb_url != ''
            ORDER BY rate DESC
        ");
        
        $this->log("Found " . count($rtb_campaigns) . " RTB campaigns");
        
        if (empty($rtb_campaigns)) {
            $this->log("❌ No RTB campaigns found");
            return null;
        }
        
        // Try each campaign
        foreach ($rtb_campaigns as $campaign) {
            $this->log("--- Trying: {$campaign['name']} ---");
            $this->log("RTB URL: {$campaign['rtb_url']}");
            
            // Check targeting
            if (!$this->matchesTargeting($campaign, $ip, $user_agent)) {
                $this->log("❌ Targeting failed");
                continue;
            }
            
            // Make bid request with correct format
            $bid_response = $this->makeBidRequest($campaign, $zone, $ip, $user_agent, $referrer);
            
            if ($bid_response) {
                $this->log("✅ Got winning bid!");
                return $bid_response;
            }
        }
        
        $this->log("❌ No winning bids");
        return null;
    }
    
    private function makeBidRequest($campaign, $zone, $ip, $user_agent, $referrer) {
        $this->log("Making bid request...");
        
        // Generate unique IDs matching the working format
        $request_id = $this->generateRequestId();
        $imp_id = (string)rand(100000, 999999);
        $tagid = (string)rand(100000, 999999);
        
        // Create bid request matching the WORKING format from adserver.online
        $bid_request = [
            'id' => $request_id,
            'imp' => [
                [
                    'id' => $imp_id,
                    'banner' => [
                        'w' => (int)$zone['width'],
                        'h' => (int)$zone['height'],
                        'format' => [
                            [
                                'w' => (int)$zone['width'],
                                'h' => (int)$zone['height']
                            ]
                        ]
                    ],
                    'tagid' => $tagid
                ]
            ],
            'site' => [
                'id' => (string)rand(10000, 99999),
                'domain' => parse_url($referrer, PHP_URL_HOST) ?: 'adstart.click',
                'cat' => ['IAB25-3'],
                'page' => $referrer ?: 'https://adstart.click/?page',
                'ref' => $referrer ?: 'https://adstart.click/?referrer',
                'publisher' => [
                    'id' => (string)rand(10000, 99999)
                ]
            ],
            'device' => [
                'dnt' => 0,
                'ua' => $user_agent,
                'ip' => $ip,
                'geo' => [
                    'country' => 'IDN', // Use 3-letter code like working example
                    'region' => 'JK',
                    'city' => 'Jakarta'
                ],
                'language' => 'en',
                'devicetype' => 2, // Desktop
                'lmt' => 0
            ],
            'user' => [
                'id' => hash('sha256', $ip . $user_agent . date('Y-m-d'))
            ],
            'at' => 1,
            'tmax' => 500,
            'cur' => ['USD']
        ];
        
        $this->log("Request ID: {$request_id}");
        $this->log("Imp ID: {$imp_id}");
        $this->log("Tag ID: {$tagid}");
        
        // Make HTTP request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $campaign['rtb_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($bid_request),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: AdStart-RTB-Client/1.0',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->log("HTTP Response: $http_code | Time: {$response_time}ms");
        $this->log("Response length: " . strlen($response) . " bytes");
        
        if ($error) {
            $this->log("❌ cURL Error: $error");
            return null;
        }
        
        if ($http_code === 204) {
            $this->log("❌ No bid (HTTP 204)");
            return null;
        }
        
        if ($http_code !== 200) {
            $this->log("❌ HTTP Error: $http_code");
            return null;
        }
        
        if (empty($response)) {
            $this->log("❌ Empty response");
            return null;
        }
        
        // Parse response
        $bid_response = json_decode($response, true);
        if (!$bid_response) {
            $this->log("❌ Invalid JSON response");
            $this->log("Raw response: " . substr($response, 0, 200) . "...");
            return null;
        }
        
        $this->log("✅ Valid JSON response received");
        
        // Check for winning bid
        if (!isset($bid_response['seatbid']) || empty($bid_response['seatbid'])) {
            $this->log("❌ No seatbid in response");
            return null;
        }
        
        $seatbid = $bid_response['seatbid'][0];
        if (!isset($seatbid['bid']) || empty($seatbid['bid'])) {
            $this->log("❌ No bids in seatbid");
            return null;
        }
        
        $winning_bid = $seatbid['bid'][0];
        
        $this->log("✅ Winning bid details:");
        $this->log("- Bid ID: " . ($winning_bid['id'] ?? 'N/A'));
        $this->log("- Price: $" . ($winning_bid['price'] ?? 0));
        $this->log("- Creative ID: " . ($winning_bid['crid'] ?? 'N/A'));
        $this->log("- Ad markup length: " . strlen($winning_bid['adm'] ?? ''));
        
        // Store bid for tracking
        $this->storeBid([
            'bid_id' => $bid_response['id'],
            'campaign_id' => $campaign['id'],
            'bid_price' => $winning_bid['price'],
            'win_price' => $winning_bid['price'],
            'ip_address' => $ip,
            'user_agent' => $user_agent,
            'country' => 'ID',
            'status' => 'won'
        ]);
        
        return [
            'id' => $winning_bid['id'],
            'campaign_id' => $campaign['id'],
            'campaign_name' => $campaign['name'],
            'type' => 'rtb_external',
            'creative_type' => 'html',
            'content' => $winning_bid['adm'],
            'landing_url' => '',
            'width' => $zone['width'],
            'height' => $zone['height'],
            'rate' => $winning_bid['price'],
            'bid_id' => $bid_response['id'],
            'rtb_url' => $campaign['rtb_url'],
            'nurl' => $winning_bid['nurl'] ?? '',
            'response_time' => $response_time,
            'crid' => $winning_bid['crid'] ?? '',
            'adid' => $winning_bid['adid'] ?? ''
        ];
    }
    
    private function generateRequestId() {
        // Generate ID similar to working format: 4b2d8876f43b012f1bf37f8167766c08-145855-290131
        $hash = substr(md5(uniqid() . microtime()), 0, 32);
        $num1 = rand(100000, 999999);
        $num2 = rand(100000, 999999);
        return $hash . '-' . $num1 . '-' . $num2;
    }
    
    public function renderAd($ad, $zone) {
        if ($ad['type'] === 'rtb_external') {
            return '<div style="position:relative; border:2px solid #28a745; background:#d4edda; padding:5px;">' .
                   $ad['content'] .
                   '<div style="position:absolute; bottom:2px; left:2px; font-size:10px; color:#155724; background:rgba(255,255,255,0.8); padding:2px 5px; border-radius:3px;">' .
                   '🌐 RTB: ' . htmlspecialchars($ad['campaign_name']) . ' | $' . number_format($ad['rate'], 4) . ' | ' . $ad['response_time'] . 'ms' .
                   '</div>' .
                   '</div>';
        }
        
        return $ad['content'];
    }
    
    public function recordImpression($ad, $zone) {
        try {
            // Record local stats
            $this->db->query("
                INSERT INTO statistics (campaign_id, campaign_type, impressions, date_stat)
                VALUES (?, ?, 1, CURDATE())
                ON DUPLICATE KEY UPDATE impressions = impressions + 1
            ", [$ad['campaign_id'], $ad['type']]);
            
            $this->log("✅ Recorded impression for campaign {$ad['campaign_id']}");
            
            // Call win notice URL
            if (!empty($ad['nurl'])) {
                $this->log("Calling win notice: " . substr($ad['nurl'], 0, 100) . "...");
                $this->callWinNotice($ad['nurl'], $ad['rate']);
            }
            
        } catch (Exception $e) {
            $this->log("❌ Error recording impression: " . $e->getMessage());
        }
    }
    
    private function callWinNotice($nurl, $price) {
        // Replace price macros
        $nurl = str_replace(['${AUCTION_PRICE}', '$%7BAUCTION_PRICE%7D'], $price, $nurl);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $nurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'AdStart-WinNotice/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->log("Win notice response: HTTP $http_code");
    }
    
    private function storeBid($bid_data) {
        try {
            $this->db->query("
                INSERT INTO bids (bid_id, campaign_id, bid_price, win_price, ip_address, user_agent, country, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $bid_data['bid_id'],
                $bid_data['campaign_id'],
                $bid_data['bid_price'],
                $bid_data['win_price'],
                $bid_data['ip_address'],
                $bid_data['user_agent'],
                $bid_data['country'],
                $bid_data['status']
            ]);
        } catch (Exception $e) {
            $this->log("Error storing bid: " . $e->getMessage());
        }
    }
    
    private function matchesTargeting($campaign, $ip, $user_agent) {
        // Simple targeting for now
        return true;
    }
    
    private function log($message) {
        $this->debugInfo[] = "[" . date('H:i:s') . "] $message";
        if ($this->debug) {
            error_log("Fixed RTB: $message");
        }
    }
    
    public function getDebugInfo() {
        return "<pre style='background:#f8f9fa;padding:15px;font-family:monospace;font-size:11px;border:1px solid #dee2e6; max-height:500px; overflow-y:auto;'>" . 
               "<h4>Fixed RTB Client Debug:</h4>" .
               implode("\n", $this->debugInfo) . "</pre>";
    }
}
?>