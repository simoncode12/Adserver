<?php
$pageTitle = 'Dashboard';
$breadcrumb = [
    ['text' => 'Dashboard']
];

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Get dashboard statistics
try {
    // Campaign stats
    $campaignStats = $db->fetch("
        SELECT 
            (SELECT COUNT(*) FROM rtb_campaigns WHERE status = 'active') as active_rtb,
            (SELECT COUNT(*) FROM ron_campaigns WHERE status = 'active') as active_ron,
            (SELECT COUNT(*) FROM rtb_campaigns WHERE status = 'paused') as paused_rtb,
            (SELECT COUNT(*) FROM ron_campaigns WHERE status = 'paused') as paused_ron
    ");
    
    // Today's statistics
    $today = date('Y-m-d');
    $todayStats = $db->fetch("
        SELECT 
            COALESCE(SUM(impressions), 0) as impressions,
            COALESCE(SUM(clicks), 0) as clicks,
            COALESCE(SUM(spend), 0) as spend,
            COALESCE(SUM(revenue), 0) as revenue
        FROM campaign_stats 
        WHERE date = ?
    ", [$today]);
    
    // Publisher and advertiser counts
    $userStats = $db->fetch("
        SELECT 
            (SELECT COUNT(*) FROM advertisers WHERE status = 'active') as advertisers,
            (SELECT COUNT(*) FROM publishers WHERE status = 'active') as publishers,
            (SELECT COUNT(*) FROM websites WHERE status = 'active') as websites,
            (SELECT COUNT(*) FROM zones WHERE status = 'active') as zones
    ");
    
    // Recent bid requests
    $recentBids = $db->fetchAll("
        SELECT br.*, z.name as zone_name 
        FROM bid_requests br
        LEFT JOIN zones z ON br.zone_id = z.id
        ORDER BY br.created_at DESC 
        LIMIT 10
    ");
    
    // Top performing campaigns (last 7 days)
    $topCampaigns = $db->fetchAll("
        SELECT 
            'rtb' as type,
            rc.name,
            rc.bid_amount,
            SUM(cs.impressions) as impressions,
            SUM(cs.clicks) as clicks,
            SUM(cs.spend) as spend,
            (SUM(cs.clicks) / NULLIF(SUM(cs.impressions), 0) * 100) as ctr
        FROM rtb_campaigns rc
        LEFT JOIN campaign_stats cs ON rc.id = cs.campaign_id AND cs.campaign_type = 'rtb'
        WHERE cs.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY rc.id, rc.name, rc.bid_amount
        
        UNION ALL
        
        SELECT 
            'ron' as type,
            roc.name,
            roc.bid_amount,
            SUM(cs.impressions) as impressions,
            SUM(cs.clicks) as clicks,
            SUM(cs.spend) as spend,
            (SUM(cs.clicks) / NULLIF(SUM(cs.impressions), 0) * 100) as ctr
        FROM ron_campaigns roc
        LEFT JOIN campaign_stats cs ON roc.id = cs.campaign_id AND cs.campaign_type = 'ron'
        WHERE cs.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY roc.id, roc.name, roc.bid_amount
        
        ORDER BY impressions DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $campaignStats = ['active_rtb' => 0, 'active_ron' => 0, 'paused_rtb' => 0, 'paused_ron' => 0];
    $todayStats = ['impressions' => 0, 'clicks' => 0, 'spend' => 0, 'revenue' => 0];
    $userStats = ['advertisers' => 0, 'publishers' => 0, 'websites' => 0, 'zones' => 0];
    $recentBids = [];
    $topCampaigns = [];
}

// Calculate derived metrics
$ctr = $todayStats['impressions'] > 0 ? ($todayStats['clicks'] / $todayStats['impressions']) * 100 : 0;
$profit = $todayStats['revenue'] - $todayStats['spend'];
$totalCampaigns = $campaignStats['active_rtb'] + $campaignStats['active_ron'];

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>
            </div>
            <div>
                <span class="badge bg-success">
                    <i class="fas fa-circle me-1"></i>System Online
                </span>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="stat-number"><?php echo number_format($todayStats['impressions']); ?></h3>
                    <p class="stat-label">Today's Impressions</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-mouse-pointer"></i>
                    </div>
                    <h3 class="stat-number"><?php echo number_format($todayStats['clicks']); ?></h3>
                    <p class="stat-label">Today's Clicks</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 class="stat-number">$<?php echo number_format($todayStats['spend'], 2); ?></h3>
                    <p class="stat-label">Today's Spend</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="stat-number">$<?php echo number_format($profit, 2); ?></h3>
                    <p class="stat-label">Today's Profit</p>
                </div>
            </div>
        </div>
        
        <!-- Campaign Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Campaign Performance (Last 7 Days)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Type</th>
                                        <th>Bid</th>
                                        <th>Impressions</th>
                                        <th>Clicks</th>
                                        <th>CTR</th>
                                        <th>Spend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($topCampaigns)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                No campaign data available
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($topCampaigns as $campaign): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $campaign['type'] == 'rtb' ? 'primary' : 'success'; ?>">
                                                        <?php echo strtoupper($campaign['type']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($campaign['bid_amount'], 4); ?></td>
                                                <td><?php echo number_format($campaign['impressions']); ?></td>
                                                <td><?php echo number_format($campaign['clicks']); ?></td>
                                                <td><?php echo number_format($campaign['ctr'], 2); ?>%</td>
                                                <td>$<?php echo number_format($campaign['spend'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>System Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $totalCampaigns; ?></h4>
                                <small class="text-muted">Active Campaigns</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?php echo $userStats['advertisers']; ?></h4>
                                <small class="text-muted">Advertisers</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-warning"><?php echo $userStats['publishers']; ?></h4>
                                <small class="text-muted">Publishers</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-info"><?php echo $userStats['zones']; ?></h4>
                                <small class="text-muted">Active Zones</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>RTB Campaigns</span>
                                <span><?php echo $campaignStats['active_rtb']; ?> active</span>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar bg-primary" style="width: 75%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>RON Campaigns</span>
                                <span><?php echo $campaignStats['active_ron']; ?> active</span>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar bg-success" style="width: 60%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between">
                                <span>Click-through Rate</span>
                                <span><?php echo number_format($ctr, 2); ?>%</span>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar bg-warning" style="width: <?php echo min($ctr * 10, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Bid Requests
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Zone</th>
                                        <th>Campaign Type</th>
                                        <th>Country</th>
                                        <th>Device</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentBids)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                No recent bid requests
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentBids as $bid): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo substr($bid['request_id'], 0, 8); ?>...</code>
                                                </td>
                                                <td><?php echo htmlspecialchars($bid['zone_name'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <?php if ($bid['campaign_type']): ?>
                                                        <span class="badge bg-<?php echo $bid['campaign_type'] == 'rtb' ? 'primary' : 'success'; ?>">
                                                            <?php echo strtoupper($bid['campaign_type']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($bid['country'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($bid['device_type'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($bid['status']) {
                                                            'win' => 'success',
                                                            'loss' => 'danger',
                                                            'response' => 'warning',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($bid['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('H:i:s', strtotime($bid['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="rtb-sell.php" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Create RTB Campaign
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="ron-campaign.php" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-2"></i>Create RON Campaign
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="advertiser.php" class="btn btn-info w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add Advertiser
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="publisher.php" class="btn btn-warning w-100">
                                    <i class="fas fa-users me-2"></i>Add Publisher
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh stats every 30 seconds
function refreshStats() {
    $.get('dashboard.php?ajax=1', function(data) {
        if (data.success) {
            // Update stat cards
            $('.stat-number').each(function(index) {
                const values = [data.impressions, data.clicks, data.spend, data.profit];
                $(this).text(formatNumber(values[index]));
            });
        }
    }).fail(function() {
        console.log('Failed to refresh stats');
    });
}

// Handle AJAX requests
if (location.search.includes('ajax=1')) {
    const response = {
        success: true,
        impressions: <?php echo $todayStats['impressions']; ?>,
        clicks: <?php echo $todayStats['clicks']; ?>,
        spend: <?php echo $todayStats['spend']; ?>,
        profit: <?php echo $profit; ?>
    };
    
    document.body.innerHTML = JSON.stringify(response);
}
</script>

<?php include 'includes/footer.php'; ?>