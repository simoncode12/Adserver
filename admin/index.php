<?php
$pageTitle = 'Dashboard';
$breadcrumb = []; // No breadcrumb for main dashboard

// Error handling and debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once '../config/init.php';
    require_once '../config/database.php';
    require_once '../config/constants.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    $auth = new Auth();
    $db = Database::getInstance();

    // Get basic statistics with error handling
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $thisMonth = date('Y-m-01');

    // Initialize with default values
    $todayStats = [
        'impressions' => 0,
        'clicks' => 0,
        'conversions' => 0,
        'revenue' => 0,
        'cost' => 0
    ];

    $yesterdayStats = [
        'impressions' => 0,
        'revenue' => 0
    ];

    $monthlyStats = [
        'impressions' => 0,
        'clicks' => 0,
        'revenue' => 0,
        'cost' => 0
    ];

    $activeCounts = [
        'active_publishers' => 0,
        'active_advertisers' => 0,
        'active_sites' => 0,
        'active_zones' => 0,
        'active_campaigns' => 0,
        'active_rtb_endpoints' => 0
    ];

    // Try to get real data, but continue if queries fail
    try {
        // Today's stats
        $result = $db->fetch(
            "SELECT 
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
                COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
                COALESCE(SUM(revenue), 0) as revenue,
                COALESCE(SUM(cost), 0) as cost
             FROM tracking_events 
             WHERE DATE(created_at) = ?",
            [$today]
        );
        if ($result) $todayStats = $result;
    } catch (Exception $e) {
        error_log("Today stats error: " . $e->getMessage());
    }

    try {
        // Yesterday's stats
        $result = $db->fetch(
            "SELECT 
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COALESCE(SUM(revenue), 0) as revenue
             FROM tracking_events 
             WHERE DATE(created_at) = ?",
            [$yesterday]
        );
        if ($result) $yesterdayStats = $result;
    } catch (Exception $e) {
        error_log("Yesterday stats error: " . $e->getMessage());
    }

    try {
        // Monthly stats
        $result = $db->fetch(
            "SELECT 
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
                COALESCE(SUM(revenue), 0) as revenue,
                COALESCE(SUM(cost), 0) as cost
             FROM tracking_events 
             WHERE DATE(created_at) >= ?",
            [$thisMonth]
        );
        if ($result) $monthlyStats = $result;
    } catch (Exception $e) {
        error_log("Monthly stats error: " . $e->getMessage());
    }

    try {
        // Active entities count
        $result = $db->fetch(
            "SELECT 
                (SELECT COUNT(*) FROM users WHERE status = 'active' AND user_type = 'publisher') as active_publishers,
                (SELECT COUNT(*) FROM users WHERE status = 'active' AND user_type = 'advertiser') as active_advertisers,
                (SELECT COUNT(*) FROM sites WHERE status = 'active') as active_sites,
                (SELECT COUNT(*) FROM zones WHERE status = 'active') as active_zones,
                (SELECT COUNT(*) FROM campaigns WHERE status = 'active') as active_campaigns,
                (SELECT COUNT(*) FROM rtb_endpoints WHERE status = 'active') as active_rtb_endpoints"
        );
        if ($result) $activeCounts = $result;
    } catch (Exception $e) {
        error_log("Active counts error: " . $e->getMessage());
    }

    // Recent activities
    $recentActivities = [];
    try {
        $recentActivities = $db->fetchAll(
            "SELECT al.*, u.username 
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC 
             LIMIT 10"
        );
    } catch (Exception $e) {
        error_log("Recent activities error: " . $e->getMessage());
    }

    // Top performing zones
    $topZones = [];
    try {
        $topZones = $db->fetchAll(
            "SELECT z.name, s.name as site_name, 
                    COUNT(te.id) as impressions,
                    SUM(te.revenue) as revenue
             FROM zones z
             JOIN sites s ON z.site_id = s.id
             LEFT JOIN tracking_events te ON z.id = te.zone_id AND DATE(te.created_at) = ?
             GROUP BY z.id
             ORDER BY revenue DESC
             LIMIT 5",
            [$today]
        );
    } catch (Exception $e) {
        error_log("Top zones error: " . $e->getMessage());
    }

    // RTB performance
    $rtbStats = ['total_requests' => 0, 'successful_requests' => 0, 'avg_response_time' => 0];
    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_requests,
                AVG(response_time) as avg_response_time
             FROM rtb_logs 
             WHERE DATE(created_at) = ?",
            [$today]
        );
        if ($result) $rtbStats = $result;
    } catch (Exception $e) {
        error_log("RTB stats error: " . $e->getMessage());
    }

    // Fraud detection stats
    $fraudStats = ['total_events' => 0, 'bot_events' => 0, 'proxy_events' => 0];
    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_events,
                COUNT(CASE WHEN fraud_type = 'bot' THEN 1 END) as bot_events,
                COUNT(CASE WHEN fraud_type = 'proxy' THEN 1 END) as proxy_events
             FROM fraud_events 
             WHERE DATE(created_at) = ?",
            [$today]
        );
        if ($result) $fraudStats = $result;
    } catch (Exception $e) {
        error_log("Fraud stats error: " . $e->getMessage());
    }

    // Calculate metrics
    $impressionChange = $yesterdayStats['impressions'] > 0 
        ? (($todayStats['impressions'] - $yesterdayStats['impressions']) / $yesterdayStats['impressions']) * 100 
        : 0;

    $revenueChange = $yesterdayStats['revenue'] > 0 
        ? (($todayStats['revenue'] - $yesterdayStats['revenue']) / $yesterdayStats['revenue']) * 100 
        : 0;

    $ctr = $todayStats['impressions'] > 0 
        ? ($todayStats['clicks'] / $todayStats['impressions']) * 100 
        : 0;

} catch (Exception $e) {
    // Log the error and show a user-friendly message
    error_log("Admin dashboard error: " . $e->getMessage());
    die("
    <div style='padding: 50px; text-align: center; font-family: Arial, sans-serif;'>
        <h2>Dashboard Temporarily Unavailable</h2>
        <p>There's an issue loading the dashboard. Please check the logs.</p>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <a href='../test.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run System Test</a>
    </div>
    ");
}

include 'templates/header.php';
?>

<div id="alerts-container"></div>

<!-- Real-time Stats Cards -->
<div id="live-stats">
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1"><?php echo number_format($todayStats['impressions'] ?? 0); ?></h3>
                            <p class="mb-0">Today's Impressions</p>
                            <small class="d-block mt-1">
                                <?php if ($impressionChange > 0): ?>
                                    <i class="fas fa-arrow-up"></i> +<?php echo number_format($impressionChange, 1); ?>%
                                <?php elseif ($impressionChange < 0): ?>
                                    <i class="fas fa-arrow-down"></i> <?php echo number_format($impressionChange, 1); ?>%
                                <?php else: ?>
                                    <i class="fas fa-minus"></i> 0%
                                <?php endif; ?>
                                vs yesterday
                            </small>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-eye fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1"><?php echo number_format($todayStats['clicks'] ?? 0); ?></h3>
                            <p class="mb-0">Today's Clicks</p>
                            <small class="d-block mt-1">
                                CTR: <?php echo number_format($ctr, 2); ?>%
                            </small>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-mouse-pointer fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1">$<?php echo number_format($todayStats['revenue'] ?? 0, 2); ?></h3>
                            <p class="mb-0">Today's Revenue</p>
                            <small class="d-block mt-1">
                                <?php if ($revenueChange > 0): ?>
                                    <i class="fas fa-arrow-up"></i> +<?php echo number_format($revenueChange, 1); ?>%
                                <?php elseif ($revenueChange < 0): ?>
                                    <i class="fas fa-arrow-down"></i> <?php echo number_format($revenueChange, 1); ?>%
                                <?php else: ?>
                                    <i class="fas fa-minus"></i> 0%
                                <?php endif; ?>
                                vs yesterday
                            </small>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1"><?php echo number_format($todayStats['conversions'] ?? 0); ?></h3>
                            <p class="mb-0">Today's Conversions</p>
                            <small class="d-block mt-1">
                                RPM: $<?php 
                                $rpm = $todayStats['impressions'] > 0 ? ($todayStats['revenue'] / $todayStats['impressions']) * 1000 : 0;
                                echo number_format($rpm, 2); 
                                ?>
                            </small>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Overview -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-primary mb-1"><?php echo $activeCounts['active_publishers'] ?? 0; ?></h4>
                        <small class="text-muted">Active Publishers</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-success mb-1"><?php echo $activeCounts['active_advertisers'] ?? 0; ?></h4>
                        <small class="text-muted">Active Advertisers</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-info mb-1"><?php echo $activeCounts['active_sites'] ?? 0; ?></h4>
                        <small class="text-muted">Active Sites</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-warning mb-1"><?php echo $activeCounts['active_zones'] ?? 0; ?></h4>
                        <small class="text-muted">Active Zones</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-danger mb-1"><?php echo $activeCounts['active_campaigns'] ?? 0; ?></h4>
                        <small class="text-muted">Active Campaigns</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-dark mb-1"><?php echo $activeCounts['active_rtb_endpoints'] ?? 0; ?></h4>
                        <small class="text-muted">RTB Endpoints</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security & Performance</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>RTB Success Rate</span>
                        <span class="fw-bold">
                            <?php 
                            $successRate = $rtbStats['total_requests'] > 0 
                                ? ($rtbStats['successful_requests'] / $rtbStats['total_requests']) * 100 
                                : 0;
                            echo number_format($successRate, 1) . '%';
                            ?>
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $successRate; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Avg RTB Response Time</span>
                        <span class="fw-bold"><?php echo number_format($rtbStats['avg_response_time'] ?? 0, 0); ?>ms</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Fraud Events Today</span>
                        <span class="fw-bold text-danger"><?php echo number_format($fraudStats['total_events'] ?? 0); ?></span>
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <small class="text-muted">Bot Blocks: <?php echo $fraudStats['bot_events'] ?? 0; ?></small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Proxy Blocks: <?php echo $fraudStats['proxy_events'] ?? 0; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Revenue Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Zones Today</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($topZones)): ?>
                    <?php foreach ($topZones as $zone): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($zone['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($zone['site_name']); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">$<?php echo number_format($zone['revenue'] ?? 0, 2); ?></div>
                                <small class="text-muted"><?php echo number_format($zone['impressions'] ?? 0); ?> imp</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-bar fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                <a href="activity_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentActivities)): ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                        </td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($activity['ip_address']); ?></small></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($activity['details'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent activities</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue Chart
const ctx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php
            $dates = [];
            $revenues = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dates[] = date('M j', strtotime($date));
                
                try {
                    $dailyRevenue = $db->fetch(
                        "SELECT SUM(revenue) as revenue FROM tracking_events WHERE DATE(created_at) = ?",
                        [$date]
                    );
                    $revenues[] = floatval($dailyRevenue['revenue'] ?? 0);
                } catch (Exception $e) {
                    $revenues[] = 0;
                }
            }
            echo json_encode($dates);
        ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?php echo json_encode($revenues); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toFixed(2);
                    }
                }
            }
        }
    }
});
</script>

<?php include 'templates/footer.php'; ?>
