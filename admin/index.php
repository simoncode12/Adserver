<?php
$pageTitle = 'Dashboard';

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
    $auth->requireAuth(USER_TYPE_ADMIN);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AdStart AdServer</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stats-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stats-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0">
                <div class="sidebar">
                    <div class="p-3 text-center">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-ad text-warning"></i>
                            AdStart
                        </h4>
                        <small class="text-white-50">Admin Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                        <a class="nav-link" href="sites.php">
                            <i class="fas fa-globe me-2"></i>Sites
                        </a>
                        <a class="nav-link" href="campaigns.php">
                            <i class="fas fa-bullhorn me-2"></i>Campaigns
                        </a>
                        <a class="nav-link" href="statistics.php">
                            <i class="fas fa-chart-bar me-2"></i>Statistics
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cogs me-2"></i>Settings
                        </a>
                        
                        <hr class="text-white-50 mx-3">
                        
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 px-0">
                <div class="main-content">
                    <!-- Top Navigation -->
                    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                        <div class="container-fluid">
                            <div class="d-flex align-items-center">
                                <h5 class="mb-0"><?php echo $pageTitle; ?></h5>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo $_SESSION['username'] ?? 'Admin'; ?>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- Page Content -->
                    <div class="p-4">
                        <!-- Real-time Stats Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stats-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-eye fa-2x mb-2"></i>
                                        <h4><?php echo number_format($todayStats['impressions'] ?? 0); ?></h4>
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
                                        <h4><?php echo number_format($todayStats['clicks'] ?? 0); ?></h4>
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
                                        <h4>$<?php echo number_format($todayStats['revenue'] ?? 0, 2); ?></h4>
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
                                        <h4><?php echo number_format($todayStats['conversions'] ?? 0); ?></h4>
                                        <p class="mb-1">Today's Conversions</p>
                                        <small>
                                            RPM: $<?php 
                                            $rpm = $todayStats['impressions'] > 0 ? ($todayStats['revenue'] / $todayStats['impressions']) * 1000 : 0;
                                            echo number_format($rpm, 2); 
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Overview -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Overview</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-2">
                                                <h4 class="text-primary"><?php echo $activeCounts['active_publishers']; ?></h4>
                                                <small>Active Publishers</small>
                                            </div>
                                            <div class="col-md-2">
                                                <h4 class="text-success"><?php echo $activeCounts['active_advertisers']; ?></h4>
                                                <small>Active Advertisers</small>
                                            </div>
                                            <div class="col-md-2">
                                                <h4 class="text-info"><?php echo $activeCounts['active_sites']; ?></h4>
                                                <small>Active Sites</small>
                                            </div>
                                            <div class="col-md-2">
                                                <h4 class="text-warning"><?php echo $activeCounts['active_zones']; ?></h4>
                                                <small>Active Zones</small>
                                            </div>
                                            <div class="col-md-2">
                                                <h4 class="text-danger"><?php echo $activeCounts['active_campaigns']; ?></h4>
                                                <small>Active Campaigns</small>
                                            </div>
                                            <div class="col-md-2">
                                                <h4 class="text-dark"><?php echo $activeCounts['active_rtb_endpoints']; ?></h4>
                                                <small>RTB Endpoints</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <a href="users.php" class="btn btn-outline-primary w-100 mb-2">
                                                    <i class="fas fa-users me-2"></i>Manage Users
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="sites.php" class="btn btn-outline-success w-100 mb-2">
                                                    <i class="fas fa-globe me-2"></i>Review Sites
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="campaigns.php" class="btn btn-outline-warning w-100 mb-2">
                                                    <i class="fas fa-bullhorn me-2"></i>View Campaigns
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="statistics.php" class="btn btn-outline-info w-100 mb-2">
                                                    <i class="fas fa-chart-bar me-2"></i>View Statistics
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
