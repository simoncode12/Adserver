<?php
// Get current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar -->
<nav class="sidebar offcanvas-md offcanvas-start" tabindex="-1" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-chart-line me-2"></i><span>AdServer</span></h4>
        <button type="button" class="sidebar-toggle btn btn-sm" title="Toggle Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <div class="sidebar-body">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'dashboard' || $currentPage == 'index') ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Campaigns Section -->
            <li class="nav-item">
                <h6 class="nav-section-header">
                    <span>CAMPAIGNS</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'rtb-sell' ? 'active' : ''; ?>" 
                   href="rtb-sell.php">
                    <i class="fas fa-exchange-alt"></i>
                    <span>RTB Campaigns</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'ron-campaign' ? 'active' : ''; ?>" 
                   href="ron-campaign.php">
                    <i class="fas fa-network-wired"></i>
                    <span>RON Campaigns</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'creative' ? 'active' : ''; ?>" 
                   href="creative.php">
                    <i class="fas fa-images"></i>
                    <span>Creatives</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'rtb-buy' ? 'active' : ''; ?>" 
                   href="rtb-buy.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>RTB Buy Traffic</span>
                </a>
            </li>
            
            <!-- Management Section -->
            <li class="nav-item">
                <h6 class="nav-section-header">
                    <span>MANAGEMENT</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'advertiser' ? 'active' : ''; ?>" 
                   href="advertiser.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Advertisers</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'publisher' ? 'active' : ''; ?>" 
                   href="publisher.php">
                    <i class="fas fa-users"></i>
                    <span>Publishers</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'website' ? 'active' : ''; ?>" 
                   href="website.php">
                    <i class="fas fa-globe"></i>
                    <span>Websites</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'zone' ? 'active' : ''; ?>" 
                   href="zone.php">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Ad Zones</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'category' ? 'active' : ''; ?>" 
                   href="category.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            
            <!-- System Section -->
            <li class="nav-item">
                <h6 class="nav-section-header">
                    <span>SYSTEM</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'rtb_endpoints' ? 'active' : ''; ?>" 
                   href="rtb_endpoints.php">
                    <i class="fas fa-plug"></i>
                    <span>RTB Endpoints</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'users' ? 'active' : ''; ?>" 
                   href="users.php">
                    <i class="fas fa-user-cog"></i>
                    <span>Users</span>
                </a>
            </li>
            
            <!-- Reports & Analytics -->
            <li class="nav-item">
                <h6 class="nav-section-header">
                    <span>ANALYTICS</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showComingSoon()">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showComingSoon()">
                    <i class="fas fa-chart-pie"></i>
                    <span>Analytics</span>
                </a>
            </li>
            
            <!-- Settings & Logout -->
            <li class="nav-item mt-auto">
                <hr class="sidebar-divider">
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showComingSoon()">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.nav-section-header {
    color: rgba(255,255,255,0.6);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 1rem 0.5rem 1rem;
    margin: 0;
}

.sidebar.collapsed .nav-section-header {
    display: none;
}

.sidebar-divider {
    border-color: rgba(255,255,255,0.2);
    margin: 1rem 0;
}

.sidebar.collapsed .sidebar-divider {
    margin: 0.5rem 1rem;
}

.sidebar .nav-item:last-child {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1040;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .offcanvas-backdrop {
        background-color: rgba(0,0,0,0.5);
    }
}
</style>

<script>
function showComingSoon() {
    Swal.fire({
        title: 'Coming Soon!',
        text: 'This feature is under development and will be available soon.',
        icon: 'info',
        confirmButtonText: 'OK'
    });
}
</script>