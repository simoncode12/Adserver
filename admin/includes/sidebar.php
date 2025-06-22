<?php
/**
 * Admin Sidebar Navigation
 * RTB and RON Campaign Platform
 */
?>
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