<?php
$pageTitle = 'Publisher Dashboard';
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth(USER_TYPE_PUBLISHER);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get today's statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$thisMonth = date('Y-m-01');

// Today's stats
$todayStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN te.event_type = 'click' THEN 1 END) as clicks,
        COUNT(CASE WHEN te.event_type = 'conversion' THEN 1 END) as conversions,
        SUM(te.revenue) as revenue
     FROM tracking_events te
     JOIN zones z ON te.zone_id = z.id
     JOIN sites s ON z.site_id = s.id
     WHERE s.user_id = ? AND DATE(te.created_at) = ?",
    [$userId, $today]
);

// Yesterday's stats for comparison
$yesterdayStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
        SUM(te.revenue) as revenue
     FROM tracking_events te
     JOIN zones z ON te.zone_id = z.id
     JOIN sites s ON z.site_id = s.id
     WHERE s.user_id = ? AND DATE(te.created_at) = ?",
    [$userId, $yesterday]
);

// Monthly stats
$monthlyStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
        COUNT(CASE WHEN te.event_type = 'click' THEN 1 END) as clicks,
        SUM(te.revenue) as revenue
     FROM tracking_events te
     JOIN zones z ON te.zone_id = z.id
     JOIN sites s ON z.site_id = s.id
     WHERE s.user_id = ? AND DATE(te.created_at) >= ?",
    [$userId, $thisMonth]
);

// Site and zone counts
$counts = $db->fetch(
    "SELECT 
        (SELECT COUNT(*) FROM sites WHERE user_id = ? AND status = 'active') as active_sites,
        (SELECT COUNT(*) FROM sites WHERE user_id = ? AND status = 'pending') as pending_sites,
        (SELECT COUNT(*) FROM zones z JOIN sites s ON z.site_id = s.id WHERE s.user_id = ? AND z.status = 'active') as active_zones,
        (SELECT COUNT(*) FROM zones z JOIN sites s ON z.site_id = s.id WHERE s.user_id = ?) as total_zones",
    [$userId, $userId, $userId, $userId]
);

// Top performing zones
$topZones = $db->fetchAll(
    "SELECT z.name, s.name as site_name, 
            COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN te.event_type = 'click' THEN 1 END) as clicks,
            SUM(te.revenue) as revenue
     FROM zones z
     JOIN sites s ON z.site_id = s.id
     LEFT JOIN tracking_events te ON z.id = te.zone_id AND DATE(te.created_at) = ?
     WHERE s.user_id = ?
     GROUP BY z.id
     ORDER BY revenue DESC
     LIMIT 5",
    [$today, $userId]
);

// Recent transactions
$recentTransactions = $db->fetchAll(
    "SELECT * FROM transactions 
     WHERE user_id = ? AND transaction_type IN ('earning', 'withdrawal')
     ORDER BY created_at DESC 
     LIMIT 10",
    [$userId]
);

// Calculate metrics
$ctr = $todayStats['impressions'] > 0 ? ($todayStats['clicks'] / $todayStats['impressions']) * 100 : 0;
$rpm = calculateRPM($todayStats['revenue'] ?? 0, $todayStats['impressions'] ?? 0);

$impressionChange = $yesterdayStats['impressions'] > 0 
    ? (($todayStats['impressions'] - $yesterdayStats['impressions']) / $yesterdayStats['impressions']) * 100 
    : 0;

$revenueChange = $yesterdayStats['revenue'] > 0 
    ? (($todayStats['revenue'] - $yesterdayStats['revenue']) / $yesterdayStats['revenue']) * 100 
    : 0;

include 'templates/header.php';
?>

<div id="alerts-container"></div>

<!-- Real-time Stats Cards -->
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
                <h4>$<?php echo number_format($rpm, 2); ?></h4>
                <p class="mb-1">RPM</p>
                <small>
                    Revenue per 1000 impressions
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Overview Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $counts['active_sites']; ?></h4>
                        <small>Active Sites</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo $counts['pending_sites']; ?></h4>
                        <small>Pending Sites</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary"><?php echo $counts['active_zones']; ?></h4>
                        <small>Active Zones</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-info"><?php echo $counts['total_zones']; ?></h4>
                        <small>Total Zones</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Monthly Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Impressions:</span>
                            <span class="fw-bold"><?php echo formatNumber($monthlyStats['impressions'] ?? 0); ?></span>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Clicks:</span>
                            <span class="fw-bold"><?php echo formatNumber($monthlyStats['clicks'] ?? 0); ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <span>Revenue:</span>
                            <span class="fw-bold text-success"><?php echo formatCurrency($monthlyStats['revenue'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables Row -->
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
                                <div class="fw-bold text-success"><?php echo formatCurrency($zone['revenue'] ?? 0); ?></div>
                                <small class="text-muted"><?php echo formatNumber($zone['impressions'] ?? 0); ?> imp</small>
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

<!-- Recent Transactions -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                <a href="finance.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentTransactions)): ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('M j, H:i', strtotime($transaction['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['transaction_type'] === 'earning' ? 'success' : 'primary'; ?>">
                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td class="text-success fw-bold">
                                            <?php echo formatCurrency($transaction['amount']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($transaction['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No transactions yet</td>
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
                
                $dailyRevenue = $db->fetch(
                    "SELECT SUM(te.revenue) as revenue 
                     FROM tracking_events te
                     JOIN zones z ON te.zone_id = z.id
                     JOIN sites s ON z.site_id = s.id
                     WHERE s.user_id = ? AND DATE(te.created_at) = ?",
                    [$userId, $date]
                );
                $revenues[] = floatval($dailyRevenue['revenue'] ?? 0);
            }
            echo json_encode($dates);
        ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?php echo json_encode($revenues); ?>,
            borderColor: 'rgb(17, 153, 142)',
            backgroundColor: 'rgba(17, 153, 142, 0.1)',
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
