<?php
/**
 * OpenRTB Implementation (Continued)
 * AdServer Platform Real-Time Bidding Protocol
 */

    /**
     * Send bid requests to multiple endpoints (continued)
     */
    private function sendBidRequests($bidRequest, $endpoints, $requestId) {
        $responses = [];
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        
        foreach ($endpoints as $endpoint) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($bidRequest),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => $endpoint['timeout_ms'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-openrtb-version: ' . RTB_PROTOCOL_VERSION
                ]
            ]);
            
            // Add authentication if required
            if ($endpoint['auth_type'] !== 'none' && $endpoint['auth_credentials']) {
                $auth = json_decode($endpoint['auth_credentials'], true);
                switch ($endpoint['auth_type']) {
                    case 'api_key':
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                            curl_getopt($ch, CURLOPT_HTTPHEADER),
                            ['X-API-Key: ' . $auth['api_key']]
                        ));
                        break;
                    case 'basic':
                        curl_setopt($ch, CURLOPT_USERPWD, $auth['username'] . ':' . $auth['password']);
                        break;
                    case 'bearer':
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                            curl_getopt($ch, CURLOPT_HTTPHEADER),
                            ['Authorization: Bearer ' . $auth['token']]
                        ));
                        break;
                }
            }
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$endpoint['id']] = $ch;
        }
        
        // Execute all requests
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);
        
        // Collect responses
        foreach ($endpoints as $endpoint) {
            $ch = $curlHandles[$endpoint['id']];
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
            
            if ($httpCode === 200 && $response) {
                $bidResponse = json_decode($response, true);
                if ($this->validateBidResponse($bidResponse, $bidRequest['id'])) {
                    $responses[] = [
                        'endpoint_id' => $endpoint['id'],
                        'response' => $bidResponse,
                        'response_time' => $responseTime
                    ];
                    
                    $this->logRTB($requestId, $endpoint['id'], RTB_REQUEST_BID_RESPONSE, null, RTB_STATUS_SUCCESS, null, null, null, $responseTime);
                } else {
                    $this->logRTB($requestId, $endpoint['id'], RTB_REQUEST_BID_RESPONSE, null, RTB_STATUS_ERROR, null, null, 'Invalid response format', $responseTime);
                }
            } else {
                $error = $httpCode === 0 ? 'Timeout' : "HTTP {$httpCode}";
                $this->logRTB($requestId, $endpoint['id'], RTB_REQUEST_BID_RESPONSE, null, RTB_STATUS_TIMEOUT, null, null, $error, $responseTime);
            }
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        return $responses;
    }
    
    /**
     * Validate bid response
     */
    private function validateBidResponse($response, $requestId) {
        return isset($response['id']) && 
               $response['id'] === $requestId &&
               isset($response['seatbid']) &&
               is_array($response['seatbid']);
    }
    
    /**
     * Select winning bid
     */
    private function selectWinningBid($responses, $floorPrice) {
        $winner = null;
        $highestBid = $floorPrice;
        
        foreach ($responses as $response) {
            foreach ($response['response']['seatbid'] as $seatBid) {
                foreach ($seatBid['bid'] as $bid) {
                    if ($bid['price'] > $highestBid) {
                        $highestBid = $bid['price'];
                        $winner = [
                            'endpoint_id' => $response['endpoint_id'],
                            'bid_price' => $bid['price'],
                            'creative' => $bid,
                            'response_time' => $response['response_time']
                        ];
                    }
                }
            }
        }
        
        return $winner;
    }
    
    /**
     * Send win notice
     */
    private function sendWinNotice($winner, $requestId) {
        if (isset($winner['creative']['nurl'])) {
            $winUrl = str_replace('${AUCTION_PRICE}', $winner['bid_price'], $winner['creative']['nurl']);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $winUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            curl_exec($ch);
            curl_close($ch);
            
            $this->logRTB($requestId, $winner['endpoint_id'], RTB_REQUEST_WIN_NOTICE, null, RTB_STATUS_SUCCESS, $winner['bid_price']);
        }
    }
    
    /**
     * Create ad response
     */
    private function createAdResponse($winner, $zone) {
        $creative = $winner['creative'];
        
        return [
            'success' => true,
            'ad_content' => $creative['adm'] ?? '',
            'click_url' => $creative['click_url'] ?? '',
            'impression_url' => $creative['iurl'] ?? '',
            'width' => $creative['w'] ?? $zone['width'],
            'height' => $creative['h'] ?? $zone['height'],
            'price' => $winner['bid_price'],
            'currency' => 'USD'
        ];
    }
    
    /**
     * Get fallback ad
     */
    private function getFallbackAd($zone) {
        $fallback = $this->db->fetch(
            "SELECT * FROM fallback_campaigns 
             WHERE status = 'active' 
             AND campaign_type = ? 
             AND (impression_cap = 0 OR impression_cap > (
                 SELECT COUNT(*) FROM tracking_events 
                 WHERE DATE(created_at) = CURDATE()
             ))
             ORDER BY priority DESC 
             LIMIT 1",
            [$zone['zone_type']]
        );
        
        if ($fallback) {
            return [
                'success' => true,
                'ad_content' => $fallback['creative_content'],
                'click_url' => $fallback['click_url'],
                'width' => $zone['width'],
                'height' => $zone['height'],
                'price' => 0,
                'is_fallback' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'No ads available'
        ];
    }
    
    /**
     * Log RTB activity
     */
    private function logRTB($requestId, $endpointId, $requestType, $zoneId, $status, $bidPrice = null, $winPrice = null, $errorMessage = null, $responseTime = null) {
        $this->db->insert('rtb_logs', [
            'request_id' => $requestId,
            'endpoint_id' => $endpointId,
            'request_type' => $requestType,
            'zone_id' => $zoneId,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent(),
            'country' => getCountryFromIp(getClientIp()),
            'device_type' => detectDeviceType(),
            'bid_price' => $bidPrice,
            'win_price' => $winPrice,
            'response_time' => $responseTime,
            'status' => $status,
            'error_message' => $errorMessage
        ]);
    }
    
    /**
     * Get device type code for OpenRTB
     */
    private function getDeviceTypeCode($deviceType) {
        switch ($deviceType) {
            case DEVICE_TYPE_MOBILE: return 1;
            case DEVICE_TYPE_TABLET: return 5;
            default: return 2; // Desktop
        }
    }
    
    /**
     * Generate anonymous user ID
     */
    private function generateUserId($ip, $userAgent) {
        return hash('sha256', $ip . $userAgent . date('Y-m-d'));
    }
    
    /**
     * Create error response
     */
    private function createErrorResponse($message) {
        return [
            'success' => false,
            'message' => $message
        ];
    }
}
?>
