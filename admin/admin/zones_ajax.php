<?php
require_once '../config/init.php';
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth(USER_TYPE_ADMIN);

$db = Database::getInstance();

// Get real-time statistics
$stats = [
    'total_zones' => 0,
    'active_zones' => 0,
    'inactive_zones' => 0,
    'pending_zones' => 0,
    'total_impressions_today' => 0,
    'total_revenue_today' => 0
];

try {
    $stats = $db->fetch(
        "SELECT 
            COUNT(*) as total_zones,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_zones,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_zones,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_zones,
            (SELECT COUNT(*) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_impressions_today,
            (SELECT SUM(te.revenue) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_revenue_today
         FROM zones"
    );
} catch (Exception $e) {
    error_log("Zone stats error: " . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
