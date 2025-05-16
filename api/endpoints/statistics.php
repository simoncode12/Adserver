<?php
// api/endpoints/statistics.php - Endpoint untuk mendapatkan statistik

class StatisticsController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function process($method, $data, $current_user) {
        if ($method !== 'GET') {
            header("HTTP/1.1 405 Method Not Allowed");
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $this->getStatistics($current_user);
    }
    
    private function getStatistics($current_user) {
        try {
            // Validasi request parameters
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'date';
            $interval = isset($_GET['interval']) ? $_GET['interval'] : 'daily'; // daily, hourly
            
            // Tambahan parameter filtering
            $campaign_id = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;
            $ad_zone_id = isset($_GET['ad_zone_id']) ? $_GET['ad_zone_id'] : null;
            $ad_id = isset($_GET['ad_id']) ? $_GET['ad_id'] : null;
            $country = isset($_GET['country']) ? $_GET['country'] : null;
            $device_type = isset($_GET['device_type']) ? $_GET['device_type'] : null;
            
            // Validasi date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
                return;
            }
            
            // Validasi interval
            if (!in_array($interval, ['daily', 'hourly'])) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid interval. Use daily or hourly']);
                return;
            }
            
            // Validasi group_by
            $allowed_group_by = ['date', 'campaign', 'ad_zone', 'ad', 'country', 'device_type', 'browser', 'os'];
            if (!in_array($group_by, $allowed_group_by)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid group_by parameter']);
                return;
            }
            
            // Filter berdasarkan user role
            $role_filter = "";
            $params = [
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ];
            
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
                $role_filter = " AND s.publisher_id = :publisher_id";
                $params[':publisher_id'] = $publisher_id;
            } 
            elseif ($current_user['role'] === 'advertiser') {
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
                $role_filter = " AND s.advertiser_id = :advertiser_id";
                $params[':advertiser_id'] = $advertiser_id;
            }
            
            // Additional filters
            $additional_filters = "";
            
            if ($campaign_id) {
                $additional_filters .= " AND s.campaign_id = :campaign_id";
                $params[':campaign_id'] = $campaign_id;
            }
            
            if ($ad_zone_id) {
                $additional_filters .= " AND s.ad_zone_id = :ad_zone_id";
                $params[':ad_zone_id'] = $ad_zone_id;
            }
            
            if ($ad_id) {
                $additional_filters .= " AND s.ad_id = :ad_id";
                $params[':ad_id'] = $ad_id;
            }
            
            if ($country) {
                $additional_filters .= " AND s.country = :country";
                $params[':country'] = $country;
            }
            
            if ($device_type) {
                $additional_filters .= " AND s.device_type = :device_type";
                $params[':device_type'] = $device_type;
            }
            
            // Choose table based on interval
            $table = ($interval === 'daily') ? 'daily_statistics' : 'statistics';
            
            // Construct group by clause and select based on group_by parameter
            $group_by_clause = '';
            $select_fields = '';
            
            switch ($group_by) {
                case 'date':
                    $group_by_clause = 'GROUP BY s.date';
                    $select_fields = 's.date';
                    break;
                    
                case 'campaign':
                    $group_by_clause = 'GROUP BY s.campaign_id';
                    $select_fields = 's.campaign_id, c.campaign_name, c.campaign_type';
                    break;
                    
                case 'ad_zone':
                    $group_by_clause = 'GROUP BY s.ad_zone_id';
                    $select_fields = 's.ad_zone_id, z.zone_name, z.zone_type';
                    break;
                    
                case 'ad':
                    $group_by_clause = 'GROUP BY s.ad_id';
                    $select_fields = 's.ad_id, a.ad_name, a.ad_type';
                    break;
                    
                case 'country':
                    $group_by_clause = 'GROUP BY s.country';
                    $select_fields = 's.country';
                    break;
                    
                case 'device_type':
                    $group_by_clause = 'GROUP BY s.device_type';
                    $select_fields = 's.device_type';
                    break;
                    
                case 'browser':
                    $group_by_clause = 'GROUP BY s.browser';
                    $select_fields = 's.browser';
                    break;
                    
                case 'os':
                    $group_by_clause = 'GROUP BY s.operating_system';
                    $select_fields = 's.operating_system';
                    break;
            }
            
            // Add hour grouping for hourly interval
            if ($interval === 'hourly') {
                if ($group_by === 'date') {
                    $group_by_clause = 'GROUP BY s.date, s.hour';
                    $select_fields = 's.date, s.hour';
                } else {
                    $group_by_clause .= ', s.date, s.hour';
                    $select_fields .= ', s.date, s.hour';
                }
            }
            
            // Construct joins based on group_by
            $joins = '';
            
            if ($group_by === 'campaign' || $campaign_id) {
                $joins .= ' LEFT JOIN campaigns c ON s.campaign_id = c.id';
            }
            
            if ($group_by === 'ad_zone' || $ad_zone_id) {
                $joins .= ' LEFT JOIN ad_zones z ON s.ad_zone_id = z.id';
            }
            
            if ($group_by === 'ad' || $ad_id) {
                $joins .= ' LEFT JOIN ads a ON s.ad_id = a.id';
            }
            
            // Construct query
            $query = "SELECT $select_fields,
                     SUM(s.requests) as requests,
                     SUM(s.impressions) as impressions,
                     SUM(s.clicks) as clicks,
                     SUM(s.conversions) as conversions,
                     SUM(s.revenue) as revenue,
                     SUM(s.cost) as cost,
                     SUM(s.profit) as profit,
                     CASE WHEN SUM(s.impressions) > 0 THEN SUM(s.clicks)/SUM(s.impressions) ELSE 0 END as ctr,
                     CASE WHEN SUM(s.clicks) > 0 THEN SUM(s.conversions)/SUM(s.clicks) ELSE 0 END as cvr,
                     CASE WHEN SUM(s.impressions) > 0 THEN (SUM(s.revenue)*1000)/SUM(s.impressions) ELSE 0 END as ecpm,
                     CASE WHEN SUM(s.clicks) > 0 THEN SUM(s.revenue)/SUM(s.clicks) ELSE 0 END as ecpc
                     FROM $table s
                     $joins
                     WHERE s.date BETWEEN :start_date AND :end_date
                     $role_filter
                     $additional_filters
                     $group_by_clause
                     ORDER BY ";
            
            // Order by based on grouping
            if ($group_by === 'date') {
                $query .= "s.date";
                if ($interval === 'hourly') {
                    $query .= ", s.hour";
                }
            } else {
                $query .= "impressions DESC";
            }
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $stats = $stmt->fetchAll();
            
            // Calculate totals
            $totals = [
                'requests' => 0,
                'impressions' => 0,
                'clicks' => 0,
                'conversions' => 0,
                'revenue' => 0,
                'cost' => 0,
                'profit' => 0
            ];
            
            foreach ($stats as $row) {
                $totals['requests'] += $row['requests'];
                $totals['impressions'] += $row['impressions'];
                $totals['clicks'] += $row['clicks'];
                $totals['conversions'] += $row['conversions'];
                $totals['revenue'] += $row['revenue'];
                $totals['cost'] += $row['cost'];
                $totals['profit'] += $row['profit'];
            }
            
            // Calculate aggregated metrics
            if ($totals['impressions'] > 0) {
                $totals['ctr'] = $totals['clicks'] / $totals['impressions'];
                $totals['ecpm'] = ($totals['revenue'] * 1000) / $totals['impressions'];
            } else {
                $totals['ctr'] = 0;
                $totals['ecpm'] = 0;
            }
            
            if ($totals['clicks'] > 0) {
                $totals['cvr'] = $totals['conversions'] / $totals['clicks'];
                $totals['ecpc'] = $totals['revenue'] / $totals['clicks'];
            } else {
                $totals['cvr'] = 0;
                $totals['ecpc'] = 0;
            }
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $stats,
                'totals' => $totals,
                'meta' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'interval' => $interval,
                    'group_by' => $group_by
                ]
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve statistics: ' . $e->getMessage()]);
        }
    }
}
