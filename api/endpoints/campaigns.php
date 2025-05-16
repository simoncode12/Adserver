<?php
// api/endpoints/campaigns.php - Endpoint untuk manajemen kampanye (lanjutan)

class CampaignsController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function process($method, $id, $data, $current_user) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getCampaign($id, $current_user);
                } else {
                    $this->getAllCampaigns($current_user);
                }
                break;
                
            case 'POST':
                $this->createCampaign($data, $current_user);
                break;
                
            case 'PUT':
                $this->updateCampaign($id, $data, $current_user);
                break;
                
            case 'DELETE':
                $this->deleteCampaign($id, $current_user);
                break;
                
            default:
                header("HTTP/1.1 405 Method Not Allowed");
                echo json_encode(['error' => 'Method not allowed']);
                break;
        }
    }
    
    private function getCampaign($id, $current_user) {
        try {
            // Query untuk mendapatkan detail kampanye
            $query = "SELECT c.*, a.user_id, a.company_name
                     FROM campaigns c
                     JOIN advertisers a ON c.advertiser_id = a.id
                     WHERE c.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Campaign not found']);
                return;
            }
            
            $campaign = $stmt->fetch();
            
            // Cek izin akses
            if ($current_user['role'] === 'advertiser' && $campaign['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Decode JSON targeting criteria
            if ($campaign['targeting_criteria'] !== null) {
                $campaign['targeting_criteria'] = json_decode($campaign['targeting_criteria'], true);
            }
            
            // Tambahkan daftar iklan di kampanye ini
            $ads_query = "SELECT id, ad_type, ad_name, status, created_at, updated_at
                         FROM ads
                         WHERE campaign_id = :campaign_id";
            
            $ads_stmt = $this->db->prepare($ads_query);
            $ads_stmt->bindParam(':campaign_id', $id);
            $ads_stmt->execute();
            
            $campaign['ads'] = $ads_stmt->fetchAll();
            
            // Tambahkan statistik untuk kampanye ini
            if ($current_user['role'] === 'admin' || ($current_user['role'] === 'advertiser' && $campaign['user_id'] == $current_user['id'])) {
                // Get recent statistics
                $stats_query = "SELECT 
                                DATE(s.date) as date,
                                SUM(s.impressions) as impressions,
                                SUM(s.clicks) as clicks,
                                SUM(s.conversions) as conversions,
                                SUM(s.cost) as cost,
                                CASE WHEN SUM(s.impressions) > 0 THEN SUM(s.clicks)/SUM(s.impressions) ELSE 0 END as ctr,
                                CASE WHEN SUM(s.impressions) > 0 THEN (SUM(s.cost)*1000)/SUM(s.impressions) ELSE 0 END as ecpm,
                                CASE WHEN SUM(s.clicks) > 0 THEN SUM(s.cost)/SUM(s.clicks) ELSE 0 END as cpc
                                FROM daily_statistics s
                                WHERE s.campaign_id = :campaign_id
                                AND s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                                GROUP BY DATE(s.date)
                                ORDER BY s.date DESC";
                
                $stats_stmt = $this->db->prepare($stats_query);
                $stats_stmt->bindParam(':campaign_id', $id);
                $stats_stmt->execute();
                
                $campaign['recent_stats'] = $stats_stmt->fetchAll();
            }
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $campaign
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve campaign details: ' . $e->getMessage()]);
        }
    }
    
    private function getAllCampaigns($current_user) {
        try {
            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Filtering
            $where_clause = "";
            $params = [];
            
            // Advertiser hanya bisa melihat kampanye mereka sendiri
            if ($current_user['role'] === 'advertiser') {
                // Get advertiser ID for this user
                $advertiser_query = "SELECT id FROM advertisers WHERE user_id = :user_id";
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $current_user['id']);
                $advertiser_stmt->execute();
                
                if ($advertiser_stmt->rowCount() === 0) {
                    header("HTTP/1.1 404 Not Found");
                    echo json_encode(['error' => 'Advertiser not found']);
                    return;
                }
                
                $advertiser_id = $advertiser_stmt->fetch()['id'];
                
                $where_clause = " WHERE c.advertiser_id = :advertiser_id";
                $params[':advertiser_id'] = $advertiser_id;
            }
            
            // Filter by status
            if (isset($_GET['status'])) {
                if (empty($where_clause)) {
                    $where_clause = " WHERE c.status = :status";
                } else {
                    $where_clause .= " AND c.status = :status";
                }
                $params[':status'] = $_GET['status'];
            }
            
            // Filter by campaign type
            if (isset($_GET['campaign_type'])) {
                if (empty($where_clause)) {
                    $where_clause = " WHERE c.campaign_type = :campaign_type";
                } else {
                    $where_clause .= " AND c.campaign_type = :campaign_type";
                }
                $params[':campaign_type'] = $_GET['campaign_type'];
            }
            
            // Count total for pagination
            $count_query = "SELECT COUNT(*) as total FROM campaigns c" . $where_clause;
            $count_stmt = $this->db->prepare($count_query);
            
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];
            
            // Query untuk mendapatkan daftar kampanye
            $query = "SELECT c.id, c.advertiser_id, c.campaign_name, c.campaign_type, 
                     c.start_date, c.end_date, c.daily_budget, c.total_budget, 
                     c.bid_type, c.bid_amount, c.status, c.created_at, c.updated_at,
                     a.company_name
                     FROM campaigns c
                     JOIN advertisers a ON c.advertiser_id = a.id" . 
                     $where_clause . 
                     " ORDER BY c.created_at DESC
                     LIMIT :offset, :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $campaigns = $stmt->fetchAll();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $campaigns,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve campaigns: ' . $e->getMessage()]);
        }
    }
    
    private function createCampaign($data, $current_user) {
        try {
            // Validasi input
            $required_fields = ['campaign_name', 'campaign_type', 'start_date', 'bid_type', 'bid_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required field: ' . $field]);
                    return;
                }
            }
            
            // Validasi campaign type
            $allowed_types = ['banner', 'video', 'popunder', 'native'];
            if (!in_array($data['campaign_type'], $allowed_types)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid campaign type. Allowed types: banner, video, popunder, native']);
                return;
            }
            
            // Validasi bid type
            $allowed_bid_types = ['cpm', 'cpc', 'cpa'];
            if (!in_array($data['bid_type'], $allowed_bid_types)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid bid type. Allowed types: cpm, cpc, cpa']);
                return;
            }
            
            // Dapatkan advertiser_id dari user_id
            if ($current_user['role'] === 'advertiser') {
                $advertiser_query = "SELECT id FROM advertisers WHERE user_id = :user_id";
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $current_user['id']);
                $advertiser_stmt->execute();
                
                if ($advertiser_stmt->rowCount() === 0) {
                    header("HTTP/1.1 404 Not Found");
                    echo json_encode(['error' => 'Advertiser not found']);
                    return;
                }
                
                $advertiser_id = $advertiser_stmt->fetch()['id'];
            } elseif ($current_user['role'] === 'admin') {
                if (!isset($data['advertiser_id'])) {
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required field: advertiser_id']);
                    return;
                }
                $advertiser_id = $data['advertiser_id'];
            } else {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Persiapkan targeting criteria sebagai JSON
            $targeting_criteria = null;
            if (isset($data['targeting_criteria'])) {
                $targeting_criteria = json_encode($data['targeting_criteria']);
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Insert campaign
            $query = "INSERT INTO campaigns 
                     (advertiser_id, campaign_name, campaign_type, targeting_criteria, 
                     targeting_geos, targeting_devices, targeting_browsers, targeting_os, targeting_languages, targeting_hours,
                     start_date, end_date, daily_budget, total_budget, bid_type, bid_amount, 
                     frequency_cap, frequency_interval, pacing, status) 
                     VALUES 
                     (:advertiser_id, :campaign_name, :campaign_type, :targeting_criteria,
                     :targeting_geos, :targeting_devices, :targeting_browsers, :targeting_os, :targeting_languages, :targeting_hours,
                     :start_date, :end_date, :daily_budget, :total_budget, :bid_type, :bid_amount,
                     :frequency_cap, :frequency_interval, :pacing, :status)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':advertiser_id', $advertiser_id);
            $stmt->bindParam(':campaign_name', $data['campaign_name']);
            $stmt->bindParam(':campaign_type', $data['campaign_type']);
            $stmt->bindParam(':targeting_criteria', $targeting_criteria);
            $stmt->bindParam(':targeting_geos', $data['targeting_geos'] ?? null);
            $stmt->bindParam(':targeting_devices', $data['targeting_devices'] ?? null);
            $stmt->bindParam(':targeting_browsers', $data['targeting_browsers'] ?? null);
            $stmt->bindParam(':targeting_os', $data['targeting_os'] ?? null);
            $stmt->bindParam(':targeting_languages', $data['targeting_languages'] ?? null);
            $stmt->bindParam(':targeting_hours', $data['targeting_hours'] ?? null);
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date'] ?? null);
            $stmt->bindParam(':daily_budget', $data['daily_budget'] ?? null);
            $stmt->bindParam(':total_budget', $data['total_budget'] ?? null);
            $stmt->bindParam(':bid_type', $data['bid_type']);
            $stmt->bindParam(':bid_amount', $data['bid_amount']);
            $frequency_cap = isset($data['frequency_cap']) ? $data['frequency_cap'] : 0;
            $stmt->bindParam(':frequency_cap', $frequency_cap);
            $frequency_interval = isset($data['frequency_interval']) ? $data['frequency_interval'] : 86400;
            $stmt->bindParam(':frequency_interval', $frequency_interval);
            $pacing = isset($data['pacing']) ? $data['pacing'] : 'standard';
            $stmt->bindParam(':pacing', $pacing);
            $status = isset($data['status']) ? $data['status'] : 'pending';
            $stmt->bindParam(':status', $status);
            
            $stmt->execute();
            $campaign_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            // Response
            header("HTTP/1.1 201 Created");
            echo json_encode([
                'message' => 'Campaign created successfully',
                'campaign_id' => $campaign_id
            ]);
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to create campaign: ' . $e->getMessage()]);
        }
    }
    
    private function updateCampaign($id, $data, $current_user) {
        try {
            // Check if campaign exists and get current data
            $check_query = "SELECT c.*, a.user_id 
                          FROM campaigns c 
                          JOIN advertisers a ON c.advertiser_id = a.id 
                          WHERE c.id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Campaign not found']);
                return;
            }
            
            $campaign = $check_stmt->fetch();
            
            // Check permissions
            if ($current_user['role'] === 'advertiser' && $campaign['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [':id' => $id];
            
            // Fields that can be updated
            $allowedFields = [
                'campaign_name', 'campaign_type', 'start_date', 'end_date', 
                'daily_budget', 'total_budget', 'bid_type', 'bid_amount', 
                'frequency_cap', 'frequency_interval', 'pacing', 'status',
                'targeting_geos', 'targeting_devices', 'targeting_browsers', 
                'targeting_os', 'targeting_languages', 'targeting_hours'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Special case for targeting_criteria (JSON field)
            if (isset($data['targeting_criteria'])) {
                $updateFields[] = "targeting_criteria = :targeting_criteria";
                $params[':targeting_criteria'] = json_encode($data['targeting_criteria']);
            }
            
            // If nothing to update
            if (empty($updateFields)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            // Execute update
            $query = "UPDATE campaigns SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'Campaign updated successfully',
                'campaign_id' => $id
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to update campaign: ' . $e->getMessage()]);
        }
    }
    
    private function deleteCampaign($id, $current_user) {
        try {
            // Check if campaign exists and get current data
            $check_query = "SELECT c.*, a.user_id 
                          FROM campaigns c 
                          JOIN advertisers a ON c.advertiser_id = a.id 
                          WHERE c.id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Campaign not found']);
                return;
            }
            
            $campaign = $check_stmt->fetch();
            
            // Check permissions
            if ($current_user['role'] === 'advertiser' && $campaign['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Instead of actually deleting, set status to 'deleted'
            $query = "UPDATE campaigns SET status = 'deleted', updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'Campaign deleted successfully',
                'campaign_id' => $id
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to delete campaign: ' . $e->getMessage()]);
        }
    }
}
