<?php
// api/endpoints/ad_zones.php - Endpoint untuk manajemen zona iklan

class AdZonesController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function process($method, $id, $data, $current_user) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getAdZone($id, $current_user);
                } else {
                    $this->getAllAdZones($current_user);
                }
                break;
                
            case 'POST':
                $this->createAdZone($data, $current_user);
                break;
                
            case 'PUT':
                $this->updateAdZone($id, $data, $current_user);
                break;
                
            case 'DELETE':
                $this->deleteAdZone($id, $current_user);
                break;
                
            default:
                header("HTTP/1.1 405 Method Not Allowed");
                echo json_encode(['error' => 'Method not allowed']);
                break;
        }
    }
    
    private function getAdZone($id, $current_user) {
        try {
            // Pastikan ID zona iklan valid
            if (!is_numeric($id)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid zone ID']);
                return;
            }
            
            // Query untuk mendapatkan detail zona iklan
            $query = "SELECT z.*, p.website_url, p.website_name, p.user_id
                     FROM ad_zones z
                     JOIN publishers p ON z.publisher_id = p.id
                     WHERE z.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Ad zone not found']);
                return;
            }
            
            $zone = $stmt->fetch();
            
            // Cek izin akses
            if ($current_user['role'] === 'publisher' && $zone['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Tambahkan statistik untuk zona ini
            if ($current_user['role'] === 'admin' || ($current_user['role'] === 'publisher' && $zone['user_id'] == $current_user['id'])) {
                // Get recent statistics
                $stats_query = "SELECT 
                                DATE(s.date) as date,
                                SUM(s.impressions) as impressions,
                                SUM(s.clicks) as clicks,
                                SUM(s.revenue) as revenue,
                                CASE WHEN SUM(s.impressions) > 0 THEN SUM(s.clicks)/SUM(s.impressions) ELSE 0 END as ctr,
                                CASE WHEN SUM(s.impressions) > 0 THEN (SUM(s.revenue)*1000)/SUM(s.impressions) ELSE 0 END as ecpm
                                FROM daily_statistics s
                                WHERE s.ad_zone_id = :zone_id
                                AND s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                                GROUP BY DATE(s.date)
                                ORDER BY s.date DESC";
                
                $stats_stmt = $this->db->prepare($stats_query);
                $stats_stmt->bindParam(':zone_id', $id);
                $stats_stmt->execute();
                
                $zone['recent_stats'] = $stats_stmt->fetchAll();
            }
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $zone
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve ad zone details: ' . $e->getMessage()]);
        }
    }
    
    private function getAllAdZones($current_user) {
        try {
            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Filtering
            $where_clause = "";
            $params = [];
            
            // Publishers hanya bisa melihat zona mereka sendiri
            if ($current_user['role'] === 'publisher') {
                // Get publisher ID for this user
                $publisher_query = "SELECT id FROM publishers WHERE user_id = :user_id";
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $current_user['id']);
                $publisher_stmt->execute();
                
                if ($publisher_stmt->rowCount() === 0) {
                    header("HTTP/1.1 404 Not Found");
                    echo json_encode(['error' => 'Publisher not found']);
                    return;
                }
                
                $publisher_id = $publisher_stmt->fetch()['id'];
                
                $where_clause = " WHERE z.publisher_id = :publisher_id";
                $params[':publisher_id'] = $publisher_id;
            }
            
            // Filter by status
            if (isset($_GET['status'])) {
                if (empty($where_clause)) {
                    $where_clause = " WHERE z.status = :status";
                } else {
                    $where_clause .= " AND z.status = :status";
                }
                $params[':status'] = $_GET['status'];
            }
            
            // Filter by zone type
            if (isset($_GET['zone_type'])) {
                if (empty($where_clause)) {
                    $where_clause = " WHERE z.zone_type = :zone_type";
                } else {
                    $where_clause .= " AND z.zone_type = :zone_type";
                }
                $params[':zone_type'] = $_GET['zone_type'];
            }
            
            // Count total for pagination
            $count_query = "SELECT COUNT(*) as total FROM ad_zones z" . $where_clause;
            $count_stmt = $this->db->prepare($count_query);
            
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];
            
            // Query untuk mendapatkan daftar zona
            $query = "SELECT z.*, p.website_url, p.website_name
                     FROM ad_zones z
                     JOIN publishers p ON z.publisher_id = p.id" . 
                     $where_clause . 
                     " ORDER BY z.created_at DESC
                     LIMIT :offset, :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $zones = $stmt->fetchAll();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $zones,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve ad zones: ' . $e->getMessage()]);
        }
    }
    
    private function createAdZone($data, $current_user) {
        try {
            // Validasi input
            if (!isset($data['zone_name']) || !isset($data['zone_type'])) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Missing required fields: zone_name, zone_type']);
                return;
            }
            
            // Validasi tipe zona
            $allowed_types = ['banner', 'video', 'popunder', 'native'];
            if (!in_array($data['zone_type'], $allowed_types)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid zone type. Allowed types: banner, video, popunder, native']);
                return;
            }
            
            // Dapatkan publisher_id dari user_id
            if ($current_user['role'] === 'publisher') {
                $publisher_query = "SELECT id FROM publishers WHERE user_id = :user_id";
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $current_user['id']);
                $publisher_stmt->execute();
                
                if ($publisher_stmt->rowCount() === 0) {
                    header("HTTP/1.1 404 Not Found");
                    echo json_encode(['error' => 'Publisher not found']);
                    return;
                }
                
                $publisher_id = $publisher_stmt->fetch()['id'];
            } elseif ($current_user['role'] === 'admin') {
                if (!isset($data['publisher_id'])) {
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required field: publisher_id']);
                    return;
                }
                $publisher_id = $data['publisher_id'];
            } else {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Validasi ukuran untuk zone type banner
            if ($data['zone_type'] === 'banner' && (!isset($data['width']) || !isset($data['height']))) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Missing required fields for banner: width, height']);
                return;
            }
            
            // Generate RTB URL dan embed code
            $rtb_url = $this->generateRtbUrl();
            $embed_code = $this->generateEmbedCode($data, $rtb_url);
            
            // Insert zona baru
            $query = "INSERT INTO ad_zones 
                     (publisher_id, zone_name, zone_type, width, height, rtb_url, rtb_enabled, 
                     embed_code, fallback_ad, fallback_url, floor_price, status) 
                     VALUES 
                     (:publisher_id, :zone_name, :zone_type, :width, :height, :rtb_url, :rtb_enabled,
                     :embed_code, :fallback_ad, :fallback_url, :floor_price, :status)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':publisher_id', $publisher_id);
            $stmt->bindParam(':zone_name', $data['zone_name']);
            $stmt->bindParam(':zone_type', $data['zone_type']);
            $stmt->bindParam(':width', $data['width'] ?? null);
            $stmt->bindParam(':height', $data['height'] ?? null);
            $stmt->bindParam(':rtb_url', $rtb_url);
            $rtb_enabled = isset($data['rtb_enabled']) ? $data['rtb_enabled'] : 1;
            $stmt->bindParam(':rtb_enabled', $rtb_enabled);
            $stmt->bindParam(':embed_code', $embed_code);
            $stmt->bindParam(':fallback_ad', $data['fallback_ad'] ?? null);
            $stmt->bindParam(':fallback_url', $data['fallback_url'] ?? null);
            $floor_price = isset($data['floor_price']) ? $data['floor_price'] : 0.00;
            $stmt->bindParam(':floor_price', $floor_price);
            $status = isset($data['status']) ? $data['status'] : 'active';
            $stmt->bindParam(':status', $status);
            
            $stmt->execute();
            $zone_id = $this->db->lastInsertId();
            
            // Response
            header("HTTP/1.1 201 Created");
            echo json_encode([
                'message' => 'Ad zone created successfully',
                'zone_id' => $zone_id,
                'rtb_url' => $rtb_url,
                'embed_code' => $embed_code
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to create ad zone: ' . $e->getMessage()]);
        }
    }
    
    private function updateAdZone($id, $data, $current_user) {
        try {
            // Check if zone exists and get current data
            $check_query = "SELECT z.*, p.user_id 
                          FROM ad_zones z 
                          JOIN publishers p ON z.publisher_id = p.id 
                          WHERE z.id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Ad zone not found']);
                return;
            }
            
            $zone = $check_stmt->fetch();
            
            // Check permissions
            if ($current_user['role'] === 'publisher' && $zone['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [':id' => $id];
            
            // Fields that can be updated
            $allowedFields = [
                'zone_name', 'zone_type', 'width', 'height', 'rtb_enabled',
                'fallback_ad', 'fallback_url', 'floor_price', 'max_refresh_rate', 'status'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // If nothing to update
            if (empty($updateFields)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            // If zone_type changed, regenerate embed code
            if (isset($data['zone_type']) && $data['zone_type'] !== $zone['zone_type']) {
                $rtb_url = $zone['rtb_url'];
                $embed_code = $this->generateEmbedCode($data, $rtb_url);
                $updateFields[] = "embed_code = :embed_code";
                $params[':embed_code'] = $embed_code;
            }
            
            // Execute update
            $query = "UPDATE ad_zones SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'Ad zone updated successfully',
                'zone_id' => $id
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to update ad zone: ' . $e->getMessage()]);
        }
    }
    
    private function deleteAdZone($id, $current_user) {
        try {
            // Check if zone exists and get current data
            $check_query = "SELECT z.*, p.user_id 
                          FROM ad_zones z 
                          JOIN publishers p ON z.publisher_id = p.id 
                          WHERE z.id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Ad zone not found']);
                return;
            }
            
            $zone = $check_stmt->fetch();
            
            // Check permissions
            if ($current_user['role'] === 'publisher' && $zone['user_id'] != $current_user['id']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Instead of actually deleting, set status to 'deleted'
            $query = "UPDATE ad_zones SET status = 'deleted', updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'Ad zone deleted successfully',
                'zone_id' => $id
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to delete ad zone: ' . $e->getMessage()]);
        }
    }
    
    // Helper method untuk generate RTB URL
    private function generateRtbUrl() {
        $base_url = 'https://rtb.example.com/bid/';
        $unique_id = bin2hex(random_bytes(8));
        return $base_url . $unique_id;
    }
    
    // Helper method untuk generate embed code
    private function generateEmbedCode($data, $rtb_url) {
        $zone_type = $data['zone_type'];
        $width = $data['width'] ?? 0;
        $height = $data['height'] ?? 0;
        
        switch ($zone_type) {
            case 'banner':
                return '<script src="https://adserver.example.com/ad.js" data-rtb="' . $rtb_url . '" data-width="' . $width . '" data-height="' . $height . '"></script>';
                
            case 'video':
                return '<div class="video-ad" data-rtb="' . $rtb_url . '"></div><script src="https://adserver.example.com/vast.js"></script>';
                
            case 'popunder':
                return '<script src="https://adserver.example.com/popunder.js" data-rtb="' . $rtb_url . '"></script>';
                
            case 'native':
                return '<div class="native-ad" data-rtb="' . $rtb_url . '"></div><script src="https://adserver.example.com/native.js"></script>';
                
            default:
                return '';
        }
    }
}
