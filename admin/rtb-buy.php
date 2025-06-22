<?php
$pageTitle = 'RTB Traffic Purchase';
$breadcrumb = [
    ['text' => 'RTB Traffic Purchase']
];

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Get server URL for endpoint generation
$serverUrl = 'https://' . $_SERVER['HTTP_HOST'];
$endpointBase = $serverUrl . '/api/rtb_handler.php';

// Get zones for endpoint generation
try {
    $zones = $db->fetchAll("
        SELECT z.*, w.name as website_name, w.url as website_url
        FROM zones z
        JOIN websites w ON z.website_id = w.id
        WHERE z.status = 'active'
        ORDER BY w.name, z.name
    ");
} catch (Exception $e) {
    $zones = [];
}

// Get active campaigns for filtering
try {
    $rtbCampaigns = $db->fetchAll("
        SELECT id, name, bid_amount, status
        FROM rtb_campaigns 
        WHERE status = 'active'
        ORDER BY name
    ");
    
    $ronCampaigns = $db->fetchAll("
        SELECT id, name, bid_amount, status
        FROM ron_campaigns 
        WHERE status = 'active'
        ORDER BY name
    ");
} catch (Exception $e) {
    $rtbCampaigns = $ronCampaigns = [];
}

// Get banner sizes for endpoint configuration
try {
    $bannerSizes = $db->fetchAll("
        SELECT * FROM banner_sizes 
        WHERE status = 'active' 
        ORDER BY width, height
    ");
} catch (Exception $e) {
    $bannerSizes = [];
}

// Get countries for targeting
try {
    $countries = $db->fetchAll("
        SELECT * FROM countries 
        WHERE status = 'active' 
        ORDER BY name
    ");
} catch (Exception $e) {
    $countries = [];
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">RTB Traffic Purchase</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">RTB Traffic Purchase</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateEndpointModal">
                    <i class="fas fa-plus me-2"></i>Generate Endpoint
                </button>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper">
        <!-- Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h6>Buy RTB Traffic</h6>
                        <small class="text-muted">Generate purchase endpoints</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-exchange-alt fa-2x text-success mb-2"></i>
                        <h6>Real-time Bidding</h6>
                        <small class="text-muted">Instant traffic acquisition</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                        <h6>Competitive Pricing</h6>
                        <small class="text-muted">Bid against RON campaigns</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-globe fa-2x text-info mb-2"></i>
                        <h6>Global Reach</h6>
                        <small class="text-muted">Worldwide traffic sources</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RTB Traffic Purchase Info -->
        <div class="alert alert-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fas fa-info-circle fa-2x text-info"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-1">How RTB Traffic Purchase Works</h5>
                    <p class="mb-0">
                        Generate endpoint URLs to purchase traffic from external RTB sources. These endpoints compete 
                        with your RON campaigns in real-time auctions. Higher bids increase your chances of winning 
                        impressions. Configure targeting, formats, and pricing to optimize your traffic acquisition.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Endpoint Generator -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-link me-2"></i>Endpoint URL Generator
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Quick Generate</h6>
                        
                        <div class="mb-3">
                            <label for="quick_zone" class="form-label">Select Zone</label>
                            <select class="form-select" id="quick_zone">
                                <option value="">Select a zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>" 
                                            data-width="<?php echo $zone['width']; ?>" 
                                            data-height="<?php echo $zone['height']; ?>">
                                        <?php echo htmlspecialchars($zone['website_name'] . ' - ' . $zone['name']); ?>
                                        (<?php echo $zone['width']; ?>×<?php echo $zone['height']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quick_format" class="form-label">Format</label>
                                    <select class="form-select" id="quick_format">
                                        <option value="banner">Banner</option>
                                        <option value="native">Native</option>
                                        <option value="video">Video</option>
                                        <option value="popunder">Popunder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quick_bidfloor" class="form-label">Bid Floor ($)</label>
                                    <input type="number" class="form-control" id="quick_bidfloor" 
                                           value="0.0001" step="0.0001" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="generateQuickEndpoint()">
                            <i class="fas fa-magic me-2"></i>Generate Endpoint
                        </button>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Generated Endpoint</h6>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Endpoint URL</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control font-monospace" 
                                               id="generated_endpoint" readonly 
                                               placeholder="Select a zone and click generate">
                                        <button class="btn btn-outline-secondary copy-to-clipboard" 
                                                type="button" data-text="" title="Copy to clipboard">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Test URL</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control font-monospace" 
                                               id="test_endpoint" readonly>
                                        <button class="btn btn-outline-primary" type="button" onclick="testEndpoint()">
                                            <i class="fas fa-play me-1"></i>Test
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="endpoint_info" class="d-none">
                                    <h6>Integration Instructions:</h6>
                                    <ol class="small">
                                        <li>Use this endpoint URL in your RTB platform</li>
                                        <li>Configure targeting parameters as needed</li>
                                        <li>Set appropriate bid amounts based on competition</li>
                                        <li>Monitor performance in the dashboard</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active RTB Sources -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Campaign Performance Overview
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Active RTB Campaigns</h6>
                        <?php if (empty($rtbCampaigns)): ?>
                            <p class="text-muted">No active RTB campaigns</p>
                            <a href="rtb-sell.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>Create RTB Campaign
                            </a>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Campaign</th>
                                            <th>Bid Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rtbCampaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td>$<?php echo number_format($campaign['bid_amount'], 4); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-success">Competing RON Campaigns</h6>
                        <?php if (empty($ronCampaigns)): ?>
                            <p class="text-muted">No active RON campaigns</p>
                            <a href="ron-campaign.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i>Create RON Campaign
                            </a>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Campaign</th>
                                            <th>Bid Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ronCampaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td>$<?php echo number_format($campaign['bid_amount'], 4); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Available Banner Formats -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-images me-2"></i>Supported Banner Formats
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($bannerSizes as $size): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($size['name']); ?></h6>
                                    <p class="card-text">
                                        <span class="badge bg-primary"><?php echo $size['width']; ?>×<?php echo $size['height']; ?></span>
                                        <?php if ($size['iab_standard']): ?>
                                            <br><small class="text-success">IAB Standard</small>
                                        <?php endif; ?>
                                    </p>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="generateFormatEndpoint('<?php echo $size['width']; ?>', '<?php echo $size['height']; ?>')">
                                        Generate Endpoint
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Endpoint Generator Modal -->
<div class="modal fade" id="generateEndpointModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>Advanced Endpoint Generator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Configuration</h6>
                        
                        <div class="mb-3">
                            <label for="modal_zone" class="form-label">Target Zone</label>
                            <select class="form-select" id="modal_zone">
                                <option value="">All Zones</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>">
                                        <?php echo htmlspecialchars($zone['website_name'] . ' - ' . $zone['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_width" class="form-label">Width</label>
                                    <input type="number" class="form-control" id="modal_width" placeholder="300">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_height" class="form-label">Height</label>
                                    <input type="number" class="form-control" id="modal_height" placeholder="250">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_format" class="form-label">Ad Format</label>
                            <select class="form-select" id="modal_format">
                                <option value="banner">Banner</option>
                                <option value="native">Native</option>
                                <option value="video">Video</option>
                                <option value="popunder">Popunder</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_bidfloor" class="form-label">Bid Floor ($)</label>
                                    <input type="number" class="form-control" id="modal_bidfloor" 
                                           value="0.0001" step="0.0001" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_currency" class="form-label">Currency</label>
                                    <select class="form-select" id="modal_currency">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Targeting (Optional)</h6>
                        
                        <div class="mb-3">
                            <label for="modal_countries" class="form-label">Target Countries</label>
                            <select class="form-select select2" id="modal_countries" multiple>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['code']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?> (<?php echo $country['code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_devices" class="form-label">Device Types</label>
                            <select class="form-select select2" id="modal_devices" multiple>
                                <option value="mobile">Mobile</option>
                                <option value="desktop">Desktop</option>
                                <option value="tablet">Tablet</option>
                                <option value="smart_tv">Smart TV</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_categories" class="form-label">Content Categories</label>
                            <select class="form-select select2" id="modal_categories" multiple>
                                <option value="mainstream">Mainstream</option>
                                <option value="adult">Adult</option>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal_ssl" checked>
                            <label class="form-check-label" for="modal_ssl">
                                Require SSL
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateAdvancedEndpoint()">
                    <i class="fas fa-magic me-2"></i>Generate Endpoint
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Update copy button when endpoint changes
    $('#generated_endpoint').on('input', function() {
        const url = $(this).val();
        $('.copy-to-clipboard').attr('data-text', url);
    });
});

function generateQuickEndpoint() {
    const zoneId = $('#quick_zone').val();
    const format = $('#quick_format').val();
    const bidfloor = $('#quick_bidfloor').val();
    
    if (!zoneId) {
        showError('Please select a zone');
        return;
    }
    
    const zone = $('#quick_zone option:selected');
    const width = zone.data('width');
    const height = zone.data('height');
    
    const params = new URLSearchParams({
        zone_id: zoneId,
        format: format,
        bidfloor: bidfloor,
        w: width,
        h: height
    });
    
    const endpoint = `<?php echo $endpointBase; ?>?${params.toString()}`;
    const testUrl = `${endpoint}&test=1`;
    
    $('#generated_endpoint').val(endpoint);
    $('#test_endpoint').val(testUrl);
    $('#endpoint_info').removeClass('d-none');
    
    // Update copy button
    $('.copy-to-clipboard').attr('data-text', endpoint);
    
    showSuccess('Endpoint generated successfully!');
}

function generateAdvancedEndpoint() {
    const params = new URLSearchParams();
    
    // Basic parameters
    const zoneId = $('#modal_zone').val();
    const width = $('#modal_width').val();
    const height = $('#modal_height').val();
    const format = $('#modal_format').val();
    const bidfloor = $('#modal_bidfloor').val();
    const currency = $('#modal_currency').val();
    
    if (zoneId) params.append('zone_id', zoneId);
    if (width) params.append('w', width);
    if (height) params.append('h', height);
    params.append('format', format);
    params.append('bidfloor', bidfloor);
    params.append('currency', currency);
    
    // Targeting parameters
    const countries = $('#modal_countries').val();
    const devices = $('#modal_devices').val();
    const categories = $('#modal_categories').val();
    const ssl = $('#modal_ssl').is(':checked');
    
    if (countries && countries.length > 0) {
        params.append('countries', countries.join(','));
    }
    if (devices && devices.length > 0) {
        params.append('devices', devices.join(','));
    }
    if (categories && categories.length > 0) {
        params.append('categories', categories.join(','));
    }
    if (ssl) {
        params.append('ssl', '1');
    }
    
    const endpoint = `<?php echo $endpointBase; ?>?${params.toString()}`;
    
    // Show result in main form
    $('#generated_endpoint').val(endpoint);
    $('#test_endpoint').val(endpoint + '&test=1');
    $('#endpoint_info').removeClass('d-none');
    
    // Update copy button
    $('.copy-to-clipboard').attr('data-text', endpoint);
    
    // Close modal
    $('#generateEndpointModal').modal('hide');
    
    showSuccess('Advanced endpoint generated successfully!');
}

function generateFormatEndpoint(width, height) {
    const params = new URLSearchParams({
        format: 'banner',
        w: width,
        h: height,
        bidfloor: '0.0001'
    });
    
    const endpoint = `<?php echo $endpointBase; ?>?${params.toString()}`;
    
    $('#generated_endpoint').val(endpoint);
    $('#test_endpoint').val(endpoint + '&test=1');
    $('#endpoint_info').removeClass('d-none');
    
    // Update copy button
    $('.copy-to-clipboard').attr('data-text', endpoint);
    
    showSuccess(`Endpoint generated for ${width}×${height} format!`);
}

function testEndpoint() {
    const testUrl = $('#test_endpoint').val();
    
    if (!testUrl) {
        showError('No test URL available');
        return;
    }
    
    // Open test URL in new window
    window.open(testUrl, '_blank', 'width=800,height=600');
}
</script>

<?php include 'includes/footer.php'; ?>