<?php
$pageTitle = 'Dashboard';
include 'includes/header.php';

// Get statistics
$stats = [
    'total_rtb_campaigns' => $db->fetch("SELECT COUNT(*) as count FROM rtb_campaigns")['count'] ?? 0,
    'total_ron_campaigns' => $db->fetch("SELECT COUNT(*) as count FROM ron_campaigns")['count'] ?? 0,
    'active_rtb_campaigns' => $db->fetch("SELECT COUNT(*) as count FROM rtb_campaigns WHERE status = 'active'")['count'] ?? 0,
    'active_ron_campaigns' => $db->fetch("SELECT COUNT(*) as count FROM ron_campaigns WHERE status = 'active'")['count'] ?? 0,
    'total_advertisers' => $db->fetch("SELECT COUNT(*) as count FROM advertisers")['count'] ?? 0,
    'total_publishers' => $db->fetch("SELECT COUNT(*) as count FROM publishers")['count'] ?? 0,
    'total_creatives' => $db->fetch("SELECT COUNT(*) as count FROM creatives")['count'] ?? 0,
    'total_zones' => $db->fetch("SELECT COUNT(*) as count FROM zones")['count'] ?? 0
];

// Get recent campaigns
$recent_rtb_campaigns = $db->fetchAll("SELECT id, name, status, created_at FROM rtb_campaigns ORDER BY created_at DESC LIMIT 5");
$recent_ron_campaigns = $db->fetchAll("SELECT id, name, status, created_at FROM ron_campaigns ORDER BY created_at DESC LIMIT 5");
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card stats-card-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?php echo $stats['total_rtb_campaigns']; ?></h3>
                        <p class="mb-0">RTB Campaigns</p>
                    </div>
                    <i class="fas fa-chart-line fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card stats-card-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?php echo $stats['total_ron_campaigns']; ?></h3>
                        <p class="mb-0">RON Campaigns</p>
                    </div>
                    <i class="fas fa-globe-americas fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card stats-card-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?php echo $stats['total_advertisers']; ?></h3>
                        <p class="mb-0">Advertisers</p>
                    </div>
                    <i class="fas fa-ad fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?php echo $stats['total_publishers']; ?></h3>
                        <p class="mb-0">Publishers</p>
                    </div>
                    <i class="fas fa-newspaper fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Stats -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo $stats['active_rtb_campaigns']; ?></h4>
                <p class="mb-0 text-muted">Active RTB</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo $stats['active_ron_campaigns']; ?></h4>
                <p class="mb-0 text-muted">Active RON</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $stats['total_creatives']; ?></h4>
                <p class="mb-0 text-muted">Creatives</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-info"><?php echo $stats['total_zones']; ?></h4>
                <p class="mb-0 text-muted">Zones</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="rtb-sell.php" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line"></i> Create RTB Campaign
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="ron-campaign.php" class="btn btn-warning w-100">
                            <i class="fas fa-globe-americas"></i> Create RON Campaign
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="advertiser.php" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> Add Advertiser
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="creative.php" class="btn btn-info w-100">
                            <i class="fas fa-paint-brush"></i> Create Creative
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Campaigns -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Recent RTB Campaigns</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_rtb_campaigns)): ?>
                    <p class="text-muted text-center">No RTB campaigns yet.</p>
                    <div class="text-center">
                        <a href="rtb-sell.php" class="btn btn-primary">Create First RTB Campaign</a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_rtb_campaigns as $campaign): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></small>
                                    </div>
                                    <span class="badge badge-status-<?php echo $campaign['status']; ?>">
                                        <?php echo ucfirst($campaign['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="rtb-sell.php" class="btn btn-outline-primary">View All RTB Campaigns</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-globe-americas"></i> Recent RON Campaigns</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_ron_campaigns)): ?>
                    <p class="text-muted text-center">No RON campaigns yet.</p>
                    <div class="text-center">
                        <a href="ron-campaign.php" class="btn btn-warning">Create First RON Campaign</a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_ron_campaigns as $campaign): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></small>
                                    </div>
                                    <span class="badge badge-status-<?php echo $campaign['status']; ?>">
                                        <?php echo ucfirst($campaign['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="ron-campaign.php" class="btn btn-outline-warning">View All RON Campaigns</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>