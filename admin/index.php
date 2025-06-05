<?php
$pageTitle = 'Dashboard';
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth(USER_TYPE_ADMIN);

$db = Database::getInstance();

// Get real-time statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$thisMonth = date('Y-m-01');

// Today's stats
$todayStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
        COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
        SUM(revenue) as revenue,
        SUM(cost) as cost
     FROM tracking_events 
     WHERE DATE(created_at) = ?",
    [$today]
);

// Yesterday's stats for comparison
$yesterdayStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
        SUM(revenue) as revenue
     FROM tracking_events 
     WHERE DATE(created_at) = ?",
    [$yesterday]
);

// Monthly stats
$monthlyStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
        SUM(revenue) as revenue,
        SUM(cost) as cost
     FROM tracking_events 
     WHERE DATE(created_at) >= ?",
    [$thisMonth]
);

// Active entities count
$activeCounts = $db->fetch(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active' AND user_type = 'publisher') as active_publishers,
        (SELECT COUNT(*) FROM users WHERE status = 'active' AND user_type = 'advertiser') as active_advertisers,
        (SELECT COUNT(*) FROM sites WHERE status = 'active') as active_sites,
        (SELECT COUNT(*) FROM zones WHERE status = 'active') as active_zones,
        (SELECT COUNT(*) FROM campaigns WHERE status = 'active') as active_campaigns,
        (SELECT COUNT(*) FROM rtb_endpoints WHERE status = 'active') as active_rtb_endpoints"
);

// Recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.username 
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Top performing zones
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

// RTB performance
$rtbStats = $db->fetch(
    "SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_requests,
        AVG(response_time) as avg_response_time
     FROM rtb_logs 
     WHERE DATE(created_at) = ?",
    [$today]
);

// Fraud detection stats
$fraudStats = $db->fetch(
    "SELECT 
        COUNT(*) as total_events,
        COUNT(CASE WHEN fraud_type = 'bot' THEN 1 END) as bot_events,
        COUNT(CASE WHEN fraud_type = 'proxy' THEN 1 END) as proxy_events
     FROM fraud_events 
     WHERE DATE(created_at) = ?",
    [$today]
);

// Calculate percentage changes
$impressionChange = $yesterdayStats['impressions'] > 0 
    ? (($todayStats['impressions'] - $yesterdayStats['impressions']) / $yesterdayStats['impressions']) * 100 
    : 0;

$revenueChange = $yesterdayStats['revenue'] > 0 
    ? (($todayStats['revenue'] - $yesterdayStats['revenue']) / $yesterdayStats['revenue']) * 100 
    : 0;

$ctr = $todayStats['impressions'] > 0 
    ? ($todayStats['clicks'] / $todayStats['impressions']) * 100 
    : 0;

include 'templates/header.php';
?>

<div id="alerts-container"></div>

<!-- Real-time Stats Cards -->
<div id="live-stats">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-eye fa-2x mb-2"></i>
                    <h4><?php echo formatNumber($todayStats['impressions'] ?? 0); ?></h4>
                    <p class="mb-1">Today's Impressions</p>
                    <small>
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
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card-success">
                <div class="card-body text-center">
                    <i class="fas fa-mouse-pointer fa-2x mb-2"></i>
                    <h4><?php echo formatNumber($todayStats['clicks'] ?? 0); ?></h4>
                    <p class="mb-1">Today's Clicks</p>
                    <small>
                        CTR: <?php echo number_format($ctr, 2); ?>%
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card-warning">
                <div class="card-body text-center">
                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                    <h4><?php echo formatCurrency($todayStats['revenue'] ?? 0); ?></h4>
                    <p class="mb-1">Today's Revenue</p>
                    <small>
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
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card-info">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <h4><?php echo formatNumber($todayStats['conversions'] ?? 0); ?></h4>
                    <p class="mb-1">Today's Conversions</p>
                    <small>
                        RPM: $<?php echo number_format(calculateRPM($todayStats['revenue'] ?? 0, $todayStats['impressions'] ?? 0), 2); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Overview -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-primary"><?php echo $activeCounts['active_publishers'] ?? 0; ?></h4>
                        <small>Active Publishers</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-success"><?php echo $activeCounts['active_advertisers'] ?? 0; ?></h4>
                        <small>Active Advertisers</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-info"><?php echo $activeCounts['active_sites'] ?? 0; ?></h4>
                        <small>Active Sites</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-warning"><?php echo $activeCounts['active_zones'] ?? 0; ?></h4>
                        <small>Active Zones</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-purple"><?php echo $activeCounts['active_campaigns'] ?? 0; ?></h4>
                        <small>Active Campaigns</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-dark"><?php echo $activeCounts['active_rtb_endpoints'] ?? 0; ?></h4>
                        <small>RTB Endpoints</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security & Performance</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
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
                    <div class="progress">
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
                        <span class="fw-bold text-danger"><?php echo formatNumber($fraudStats['total_events'] ?? 0); ?></span>
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
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Revenue Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Zones Today</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($topZones)): ?>
                    <?php foreach ($topZones as $zone): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($zone['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($zone['site_name']); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?php echo formatCurrency($zone['revenue']); ?></div>
                                <small class="text-muted"><?php echo formatNumber($zone['impressions']); ?> imp</small>
                            </div>
                        </div>
                        <hr class="my-2">
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">No data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($activity['ip_address']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
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
                
                $dailyRevenue = $db->fetch(
                    "SELECT SUM(revenue) as revenue FROM tracking_events WHERE DATE(created_at) = ?",
                    [$date]
                );
                $revenues[] = floatval($dailyRevenue['revenue'] ?? 0);
            }
            echo json_encode($dates);
        ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?php echo json_encode($revenues); ?>,
            borderColor: 'rgb(102, 126, 234)',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.1,
            fill: true
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
