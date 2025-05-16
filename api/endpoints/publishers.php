<?php
// api/endpoints/publishers.php - Endpoint untuk manajemen publisher

class PublishersController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function process($method, $id, $data, $current_user) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getPublisher($id, $current_user);
                } else {
                    $this->getAllPublishers($current_user);
                }
                break;
                
            default:
                header("HTTP/1.1 405 Method Not Allowed");
                echo json_encode(['error' => 'Method not allowed']);
                break;
        }
    }
    
    private function getAllPublishers($current_user) {
        try {
            // Pastikan hanya admin yang bisa mengakses daftar publisher
            if ($current_user['role'] !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Only admin can access this endpoint']);
                return;
            }
            
            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Filtering
            $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
            $where_clause = "";
            $params = [];
            
            if ($status_filter) {
                $where_clause = " WHERE p.status = :status";
                $params[':status'] = $status_filter;
            }
            
            // Get total count for pagination
            $count_query = "SELECT COUNT(*) as total FROM publishers p" . $where_clause;
            $count_stmt = $this->db->prepare($count_query);
            
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];
            
            // Query untuk mendapatkan daftar publisher
            $query = "SELECT p.id, p.user_id, p.website_url, p.website_name, p.website_category, 
                     p.status, p.verification_status, p.created_at, 
                     u.username, u.email, u.last_login
                     FROM publishers p
                     JOIN users u ON p.user_id = u.id" . 
                     $where_clause . 
                     " ORDER BY p.created_at DESC
                     LIMIT :offset, :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $publishers = $stmt->fetchAll();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $publishers,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve publishers: ' . $e->getMessage()]);
        }
    }
    
    private function getPublisher($id, $current_user) {
        try {
            // Admin dapat mengakses semua publisher
            // Publisher hanya dapat mengakses datanya sendiri
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
                
                if ($publisher_id != $id) {
                    header("HTTP/1.1 403 Forbidden");
                    echo json_encode(['error' => 'Permission denied']);
                    return;
                }
            }
            
            // Query detail publisher
            $query = "SELECT p.*, u.username, u.email, u.status as user_status
                     FROM publishers p
                     JOIN users u ON p.user_id = u.id
                     WHERE p.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Publisher not found']);
                return;
            }
            
            $publisher = $stmt->fetch();
            
            // Tambahkan statistik untuk publisher ini
            if ($current_user['role'] === 'admin' || $current_user['role'] === 'publisher') {
                // Get recent statistics
                $stats_query = "SELECT 
                                DATE(s.date) as date,
                                SUM(s.impressions) as impressions,
                                SUM(s.clicks) as clicks,
                                SUM(s.revenue) as revenue,
                                CASE WHEN SUM(s.impressions) > 0 THEN SUM(s.clicks)/SUM(s.impressions) ELSE 0 END as ctr,
                                CASE WHEN SUM(s.impressions) > 0 THEN (SUM(s.revenue)*1000)/SUM(s.impressions) ELSE 0 END as ecpm
                                FROM daily_statistics s
                                WHERE s.publisher_id = :publisher_id
                                AND s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                                GROUP BY DATE(s.date)
                                ORDER BY s.date DESC";
                
                $stats_stmt = $this->db->prepare($stats_query);
                $stats_stmt->bindParam(':publisher_id', $id);
                $stats_stmt->execute();
                
                $publisher['recent_stats'] = $stats_stmt->fetchAll();
                
                // Get zone counts
                $zones_query = "SELECT COUNT(*) as total_zones, 
                               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_zones
                               FROM ad_zones
                               WHERE publisher_id = :publisher_id";
                
                $zones_stmt = $this->db->prepare($zones_query);
                $zones_stmt->bindParam(':publisher_id', $id);
                $zones_stmt->execute();
                
                $publisher['zones'] = $zones_stmt->fetch();
            }
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $publisher
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve publisher details: ' . $e->getMessage()]);
        }
    }
}
