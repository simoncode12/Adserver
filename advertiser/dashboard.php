<?php
$pageTitle = 'Advertiser Dashboard';
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth(USER_TYPE_ADVERTISER);

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
        SUM(te.cost) as cost
     FROM tracking_events te
     JOIN campaigns c ON te.campaign_id = c.id
     WHERE c.user_id = ? AND DATE(te.created_at) = ?",
    [$userId, $today]
);

// Yesterday's stats for comparison
$yesterdayStats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
        SUM(te.cost) as cost
     FROM tracking_events te
     JOIN campaigns c ON te.campaign_id = c.id
     WHERE c.user_id = ? AND DATE(te.created_at) = ?",
    [$userId, $yesterday]
);

// Campaign counts
$campaignCounts = $db->fetch(
    "SELECT 
        COUNT(*) as total_campaigns,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
        COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_campaigns,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_campaigns,
        SUM(budget_amount) as total_budget,
        SUM(spent_amount) as total_spent
     FROM campaigns 
     WHERE user_id = ?",
    [$userId]
);

// Top performing campaigns
$topCampaigns = $db->fetchAll(
    "SELECT c.name, 
            COUNT(CASE WHEN te.event_type = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN te.event_type = 'click' THEN 1 END) as clicks,
            SUM(te.cost) as cost
     FROM campaigns c
     LEFT JOIN tracking_events te ON c.id = te.campaign_id AND DATE(te.created_at) = ?
     WHERE c.user_id = ?
     GROUP BY c.id
     ORDER BY cost DESC
     LIMIT 5",
    [$today, $userId]
);

// Calculate metrics
$ctr = $todayStats['impressions'] > 0 ? ($todayStats['clicks'] / $todayStats['impressions']) * 100 : 0;
$ecpm = calculateECPM($todayStats['cost'] ?? 0, $todayStats['impressions'] ?? 0);

$impressionChange = $yesterdayStats['impressions'] > 0 
    ? (($todayStats['impressions'] - $yesterdayStats['impressions']) / $yesterdayStats['impressions']) * 100 
    : 0;

include '../advertiser/templates/header.php';
?>

<!-- Similar dashboard structure for advertisers with spend/campaign metrics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-eye fa-2x mb-2"></i>
                <h4><?php echo formatNumber($todayStats['impressions'] ?? 0); ?></h4>
                <p class="mb-1">Today's Impressions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-success">
            <div class="card-body text-center">
                <i class="fas fa-mouse-pointer fa-2x mb-2"></i>
                <h4><?php echo formatNumber($todayStats['clicks'] ?? 0); ?></h4>
                <p class="mb-1">Today's Clicks</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4><?php echo formatCurrency($todayStats['cost'] ?? 0); ?></h4>
                <p class="mb-1">Today's Spend</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-info">
            <div class="card-body text-center">
                <i class="fas fa-bullhorn fa-2x mb-2"></i>
                <h4><?php echo $campaignCounts['active_campaigns']; ?></h4>
                <p class="mb-1">Active Campaigns</p>
            </div>
        </div>
    </div>
</div>

<?php include '../advertiser/templates/footer.php'; ?>
