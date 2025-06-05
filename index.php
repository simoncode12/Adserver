<?php
/**
 * AdServer Platform Landing Page
 * Public facing homepage
 */

require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/helpers.php';

$pageTitle = 'AdStart AdServer - Modern RTB Platform';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .feature-card {
            transition: transform 0.3s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .stats-section {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark position-absolute w-100" style="z-index: 1000;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-ad me-2"></i>AdStart
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="#features">Features</a>
                    <a class="nav-link" href="#about">About</a>
                    <a class="nav-link" href="#contact">Contact</a>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Login</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin/login.php">Admin Panel</a></li>
                            <li><a class="dropdown-item" href="publisher/login.php">Publisher Panel</a></li>
                            <li><a class="dropdown-item" href="advertiser/login.php">Advertiser Panel</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Modern AdServer Platform</h1>
                    <p class="lead mb-4">
                        Complete SSP & DSP solution with Real-Time Bidding, Anti-Fraud protection, 
                        and comprehensive analytics. Maximize your revenue with our cutting-edge technology.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="publisher/login.php" class="btn btn-light btn-lg">
                            <i class="fas fa-globe me-2"></i>Publishers
                        </a>
                        <a href="advertiser/login.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-bullhorn me-2"></i>Advertisers
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-chart-line fa-10x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Platform Features</h2>
                <p class="lead text-muted">Everything you need for modern digital advertising</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-exchange-alt fa-3x text-primary mb-3"></i>
                            <h5>Real-Time Bidding</h5>
                            <p class="text-muted">OpenRTB 2.5 compliant RTB engine with millisecond response times</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-shield-virus fa-3x text-success mb-3"></i>
                            <h5>Anti-Fraud Protection</h5>
                            <p class="text-muted">Advanced bot detection and traffic filtering to ensure quality</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                            <h5>Real-Time Analytics</h5>
                            <p class="text-muted">Comprehensive statistics and reporting with live data updates</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-palette fa-3x text-warning mb-3"></i>
                            <h5>Multi-Format Ads</h5>
                            <p class="text-muted">Support for Banner, Video, Native, and Popunder formats</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-server fa-3x text-danger mb-3"></i>
                            <h5>SSP & DSP</h5>
                            <p class="text-muted">Complete supply and demand side platform in one solution</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                            <h5>Revenue Optimization</h5>
                            <p class="text-muted">Maximize revenue with intelligent bid optimization algorithms</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <h3 class="fw-bold text-primary">1M+</h3>
                    <p class="text-muted">Daily Impressions</p>
                </div>
                <div class="col-md-3">
                    <h3 class="fw-bold text-success">500+</h3>
                    <p class="text-muted">Active Publishers</p>
                </div>
                <div class="col-md-3">
                    <h3 class="fw-bold text-info">200+</h3>
                    <p class="text-muted">Advertisers</p>
                </div>
                <div class="col-md-3">
                    <h3 class="fw-bold text-warning">99.9%</h3>
                    <p class="text-muted">Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-ad me-2"></i>AdStart AdServer</h5>
                    <p class="text-muted">Modern advertising technology platform</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">&copy; 2024 AdStart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
