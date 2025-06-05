<?php
/**
 * Anti-Fraud System
 * AdServer Platform Fraud Detection and Prevention
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class AntiFraud {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = [
            'enabled' => FRAUD_DETECTION_ENABLED,
            'confidence_threshold' => FRAUD_CONFIDENCE_THRESHOLD,
            'bot_detection' => BOT_DETECTION_ENABLED
        ];
    }
    
    /**
     * Check if traffic is fraudulent
     */
    public function checkTraffic($request) {
        if (!$this->config['enabled']) {
            return ['is_fraud' => false, 'confidence' => 0, 'reasons' => []];
        }
        
        $ip = $request['ip'] ?? getClientIp();
        $userAgent = $request['user_agent'] ?? getUserAgent();
        $referer = $request['referer'] ?? getReferer();
        
        $checks = [
            'blacklist' => $this->checkBlacklist($ip, $userAgent, $referer),
            'bot' => $this->checkBot($userAgent),
            'proxy' => $this->checkProxy($ip),
            'pattern' => $this->checkSuspiciousPattern($ip, $userAgent),
            'frequency' => $this->checkFrequency($ip)
        ];
        
        $confidence = 0;
        $reasons = [];
        $fraudTypes = [];
        
        foreach ($checks as $type => $result) {
            if ($result['is_fraud']) {
                $confidence = max($confidence, $result['confidence']);
                $reasons[] = $result['reason'];
                $fraudTypes[] = $type;
            }
        }
        
        $isFraud = $confidence >= $this->config['confidence_threshold'];
        
        if ($isFraud) {
            $this->logFraudEvent($ip, $userAgent, $referer, $fraudTypes, $confidence, $request);
        }
        
        return [
            'is_fraud' => $isFraud,
            'confidence' => $confidence,
            'reasons' => $reasons,
            'types' => $fraudTypes
        ];
    }
    
    /**
     * Check blacklist
     */
    private function checkBlacklist($ip, $userAgent, $referer) {
        $blacklisted = $this->db->fetchAll(
            "SELECT type, value, reason FROM blacklist WHERE status = 'active'"
        );
        
        foreach ($blacklisted as $item) {
            $match = false;
            
            switch ($item['type']) {
                case BLACKLIST_TYPE_IP:
                    $match = ($ip === $item['value']);
                    break;
                case BLACKLIST_TYPE_IP_RANGE:
                    $match = $this->ipInRange($ip, $item['value']);
                    break;
                case BLACKLIST_TYPE_USER_AGENT:
                    $match = (stripos($userAgent, $item['value']) !== false);
                    break;
                case BLACKLIST_TYPE_REFERER:
                    $match = (stripos($referer, $item['value']) !== false);
                    break;
            }
            
            if ($match) {
                return [
                    'is_fraud' => true,
                    'confidence' => 1.0,
                    'reason' => "Blacklisted {$item['type']}: {$item['reason']}"
                ];
            }
        }
        
        return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
    }
    
    /**
     * Check for bot traffic
     */
    private function checkBot($userAgent) {
        if (!$this->config['bot_detection']) {
            return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
        }
        
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'wget', 'curl',
            'facebook', 'twitter', 'linkedin', 'google', 'bing',
            'yahoo', 'baidu', 'yandex', 'duckduckgo'
        ];
        
        $userAgentLower = strtolower($userAgent);
        
        foreach ($botPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return [
                    'is_fraud' => true,
                    'confidence' => 0.9,
                    'reason' => "Bot detected: {$pattern}"
                ];
            }
        }
        
        // Check for suspicious user agent patterns
        if (empty($userAgent) || strlen($userAgent) < 10) {
            return [
                'is_fraud' => true,
                'confidence' => 0.8,
                'reason' => 'Suspicious user agent'
            ];
        }
        
        return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
    }
    
    /**
     * Check for proxy/VPN
     */
    private function checkProxy($ip) {
        // Basic proxy detection - can be enhanced with commercial services
        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'VIA',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
            'HTTP_PROXY_CONNECTION'
        ];
        
        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                return [
                    'is_fraud' => true,
                    'confidence' => 0.7,
                    'reason' => 'Proxy detected'
                ];
            }
        }
        
        // Check datacenter IP ranges (simplified)
        $datacenterRanges = [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16'
        ];
        
        foreach ($datacenterRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return [
                    'is_fraud' => true,
                    'confidence' => 0.6,
                    'reason' => 'Datacenter IP'
                ];
            }
        }
        
        return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
    }
    
    /**
     * Check suspicious patterns
     */
    private function checkSuspiciousPattern($ip, $userAgent) {
        // Check for too many requests from same IP
        $recentRequests = $this->db->fetch(
            "SELECT COUNT(*) as count FROM tracking_events 
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$ip]
        );
        
        if ($recentRequests['count'] > 100) {
            return [
                'is_fraud' => true,
                'confidence' => 0.9,
                'reason' => 'Too many requests per minute'
            ];
        }
        
        // Check for suspicious user agent patterns
        if (preg_match('/^[a-zA-Z]{1,3}$/', $userAgent)) {
            return [
                'is_fraud' => true,
                'confidence' => 0.8,
                'reason' => 'Suspicious user agent pattern'
            ];
        }
        
        return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
    }
    
    /**
     * Check request frequency
     */
    private function checkFrequency($ip) {
        $hourlyRequests = $this->db->fetch(
            "SELECT COUNT(*) as count FROM tracking_events 
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        
        if ($hourlyRequests['count'] > 1000) {
            return [
                'is_fraud' => true,
                'confidence' => 0.8,
                'reason' => 'Excessive request frequency'
            ];
        }
        
        return ['is_fraud' => false, 'confidence' => 0, 'reason' => ''];
    }
    
    /**
     * Log fraud event
     */
    private function logFraudEvent($ip, $userAgent, $referer, $fraudTypes, $confidence, $request) {
        $country = getCountryFromIp($ip);
        
        foreach ($fraudTypes as $type) {
            $this->db->insert('fraud_events', [
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'referer' => $referer,
                'country' => $country,
                'fraud_type' => $type,
                'confidence_score' => $confidence,
                'zone_id' => $request['zone_id'] ?? null,
                'site_id' => $request['site_id'] ?? null,
                'blocked' => true,
                'details' => json_encode($request)
            ]);
        }
        
        logMessage("Fraud detected: IP={$ip}, Types=" . implode(',', $fraudTypes) . ", Confidence={$confidence}", 'WARNING', FRAUD_LOG_FILE);
    }
    
    /**
     * Add to blacklist
     */
    public function addToBlacklist($type, $value, $reason = '') {
        try {
            $this->db->insert('blacklist', [
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'auto_detected' => true,
                'created_by' => $_SESSION['user_id'] ?? null
            ]);
            
            return true;
        } catch (Exception $e) {
            logMessage("Failed to add to blacklist: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Remove from blacklist
     */
    public function removeFromBlacklist($id) {
        try {
            $this->db->update('blacklist', 
                ['status' => 'inactive'], 
                'id = ?', 
                [$id]
            );
            
            return true;
        } catch (Exception $e) {
            logMessage("Failed to remove from blacklist: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Get fraud statistics
     */
    public function getFraudStats($dateFrom = null, $dateTo = null) {
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-30 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        return $this->db->fetchAll(
            "SELECT fraud_type, COUNT(*) as count, AVG(confidence_score) as avg_confidence
             FROM fraud_events 
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY fraud_type
             ORDER BY count DESC",
            [$dateFrom, $dateTo]
        );
    }
    
    /**
     * Check if IP is in range
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) === $subnet;
    }
}
?>
