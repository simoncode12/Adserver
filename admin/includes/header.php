<?php
/**
 * Admin Header Include
 * RTB and RON Campaign Platform
 */

// Include necessary files
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Initialize database and auth
$db = Database::getInstance();
$auth = new Auth();

// Check if user is authenticated as admin
if (!$auth->isAuthenticated('admin')) {
    redirect('../admin/login.php');
}

// Get current user info
$currentUser = $auth->getCurrentUser();

// Set page title if not already set
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard';
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RTB & RON Campaign Platform</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../asset/css/style.css" rel="stylesheet">
    
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
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-md-2 px-0">
                <div class="sidebar">
                    <div class="p-3 text-center">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-ad text-warning"></i>
                            RTB & RON
                        </h4>
                        <small class="text-white-50">Campaign Platform</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rtb-sell.php' ? 'active' : ''; ?>" href="rtb-sell.php">
                            <i class="fas fa-chart-line"></i> RTB Sell
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ron-campaign.php' ? 'active' : ''; ?>" href="ron-campaign.php">
                            <i class="fas fa-globe-americas"></i> RON Campaign
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rtb-buy.php' ? 'active' : ''; ?>" href="rtb-buy.php">
                            <i class="fas fa-shopping-cart"></i> RTB Buy
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'creative.php' ? 'active' : ''; ?>" href="creative.php">
                            <i class="fas fa-paint-brush"></i> Creatives
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'zone.php' ? 'active' : ''; ?>" href="zone.php">
                            <i class="fas fa-th-large"></i> Zones
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'website.php' ? 'active' : ''; ?>" href="website.php">
                            <i class="fas fa-globe"></i> Websites
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'advertiser.php' ? 'active' : ''; ?>" href="advertiser.php">
                            <i class="fas fa-ad"></i> Advertisers
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'publisher.php' ? 'active' : ''; ?>" href="publisher.php">
                            <i class="fas fa-newspaper"></i> Publishers
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'category.php' ? 'active' : ''; ?>" href="category.php">
                            <i class="fas fa-tags"></i> Categories
                        </a>
                        
                        <hr class="text-white-50 mx-3">
                        
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 px-0">
                <div class="main-content">
                    <!-- Top Navigation -->
                    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                        <div class="container-fluid">
                            <h5 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h5>
                            
                            <div class="d-flex align-items-center">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($currentUser['username'] ?? 'Admin'); ?>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- Page Content -->
                    <div class="p-4">