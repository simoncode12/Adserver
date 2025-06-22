<?php
$pageTitle = 'RTB Buy - Endpoint URLs';
include 'includes/header.php';

$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'create_endpoint':
                    $name = sanitize($_POST['name']);
                    $category_id = (int)$_POST['category_id'];
                    $banner_sizes = isset($_POST['banner_sizes']) ? json_encode($_POST['banner_sizes']) : '[]';
                    $floor_price = (float)$_POST['floor_price'];
                    
                    // Generate the RTB endpoint URL
                    $base_url = "http://rtb.exoclick.com/rtb.php";
                    $zone_id = EXOCLICK_DEFAULT_ZONE;
                    $fid = EXOCLICK_DEFAULT_FID;
                    $url = $base_url . "?idzone=" . $zone_id . "&fid=" . $fid;
                    
                    $db->insert('rtb_endpoints', [
                        'name' => $name,
                        'url' => $url,
                        'category_id' => $category_id,
                        'banner_sizes' => $banner_sizes,
                        'floor_price' => $floor_price,
                        'status' => 'active'
                    ]);
                    
                    $success = 'RTB endpoint created successfully!';
                    break;
                    
                case 'update_status':
                    $endpoint_id = (int)$_POST['endpoint_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('rtb_endpoints', 
                        ['status' => $status], 
                        'id = ?', 
                        [$endpoint_id]
                    );
                    
                    $success = 'Endpoint status updated successfully!';
                    break;
                    
                case 'delete':
                    $endpoint_id = (int)$_POST['endpoint_id'];
                    $db->delete('rtb_endpoints', 'id = ?', [$endpoint_id]);
                    $success = 'Endpoint deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get endpoints with category info
$endpoints = $db->fetchAll("
    SELECT e.*, c.name as category_name, c.type as category_type
    FROM rtb_endpoints e 
    LEFT JOIN categories c ON e.category_id = c.id 
    ORDER BY e.created_at DESC
");

// Get data for forms
$categories = $db->fetchAll("SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY name");
$banner_sizes = $db->fetchAll("SELECT name FROM banner_sizes WHERE status = 'active' ORDER BY name");

// Generate sample endpoint URLs for different formats
$sample_urls = [
    '300x250' => "http://rtb.exoclick.com/rtb.php?idzone=5128252&fid=e573a1c2a656509b0112f7213359757be76929c7&size=300x250",
    '300x100' => "http://rtb.exoclick.com/rtb.php?idzone=5128252&fid=e573a1c2a656509b0112f7213359757be76929c7&size=300x100",
    '728x90' => "http://rtb.exoclick.com/rtb.php?idzone=5128252&fid=e573a1c2a656509b0112f7213359757be76929c7&size=728x90",
    '160x600' => "http://rtb.exoclick.com/rtb.php?idzone=5128252&fid=e573a1c2a656509b0112f7213359757be76929c7&size=160x600",
];
?>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- RTB Buy Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-info text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-shopping-cart"></i> RTB Buy - Traffic Acquisition
                        </h5>
                        <p class="card-text mb-0">
                            Generate endpoint URLs to buy traffic from external RTB sources like Exoclick.
                            These endpoints compete with your RTB sell campaigns for the best inventory.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-exchange-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sample Exoclick URLs -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link"></i> Sample Exoclick RTB URLs</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Here are sample RTB endpoint URLs for different banner formats. These URLs connect to Exoclick's RTB system.
                </p>
                
                <div class="row">
                    <?php foreach ($sample_urls as $size => $url): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-secondary">
                                <div class="card-header bg-light">
                                    <strong><?php echo $size; ?> Banner</strong>
                                </div>
                                <div class="card-body">
                                    <div class="zone-code">
<?php echo htmlspecialchars($url); ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary mt-2" 
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($url); ?>')">
                                        <i class="fas fa-copy"></i> Copy URL
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

<!-- Create New Endpoint -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Create New RTB Endpoint</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create_endpoint">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Endpoint Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Descriptive name for this traffic source</div>
                            <div class="invalid-feedback">Please provide an endpoint name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?> 
                                        (<?php echo ucfirst($category['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="banner_sizes" class="form-label">Supported Banner Sizes</label>
                            <select class="form-select" id="banner_sizes" name="banner_sizes[]" multiple>
                                <?php foreach ($banner_sizes as $size): ?>
                                    <option value="<?php echo $size['name']; ?>" 
                                            <?php echo in_array($size['name'], ['300x250', '300x100', '300x50', '300x500', '900x250', '728x90', '160x600']) ? 'selected' : ''; ?>>
                                        <?php echo $size['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Exoclick standard sizes are pre-selected</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="floor_price" class="form-label">Floor Price <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="floor_price" name="floor_price" 
                                       step="0.0001" min="0.0001" value="0.0010" required>
                            </div>
                            <div class="form-text">Minimum bid price for this endpoint</div>
                            <div class="invalid-feedback">Please provide a valid floor price.</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> The generated endpoint will use Exoclick's RTB system with zone ID 
                        <code><?php echo EXOCLICK_DEFAULT_ZONE; ?></code> and FID 
                        <code><?php echo EXOCLICK_DEFAULT_FID; ?></code>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i> Create RTB Endpoint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Endpoints -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> RTB Buy Endpoints</h5>
            </div>
            <div class="card-body">
                <?php if (empty($endpoints)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No RTB endpoints found</h5>
                        <p class="text-muted">Create your first RTB endpoint to start buying traffic from external sources.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Supported Sizes</th>
                                    <th>Floor Price</th>
                                    <th>Endpoint URL</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($endpoints as $endpoint): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($endpoint['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($endpoint['category_name'] ?? 'Unknown'); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo ucfirst($endpoint['category_type'] ?? 'unknown'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            $sizes = json_decode($endpoint['banner_sizes'], true);
                                            if (!empty($sizes)) {
                                                echo '<div class="targeting-options">';
                                                foreach (array_slice($sizes, 0, 3) as $size) {
                                                    echo '<span class="targeting-tag">' . htmlspecialchars($size) . '</span>';
                                                }
                                                if (count($sizes) > 3) {
                                                    echo '<span class="targeting-tag">+' . (count($sizes) - 3) . ' more</span>';
                                                }
                                                echo '</div>';
                                            } else {
                                                echo '<span class="text-muted">All sizes</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>$<?php echo number_format($endpoint['floor_price'], 4); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <code class="flex-grow-1" style="font-size: 0.8rem;">
                                                    <?php echo htmlspecialchars(substr($endpoint['url'], 0, 50)); ?>...
                                                </code>
                                                <button class="btn btn-sm btn-outline-primary ms-2" 
                                                        onclick="copyToClipboard('<?php echo htmlspecialchars($endpoint['url']); ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $endpoint['status']; ?>">
                                                <?php echo ucfirst($endpoint['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="endpoint_id" value="<?php echo $endpoint['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $endpoint['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $endpoint['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $endpoint['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="showEndpointDetails(<?php echo htmlspecialchars(json_encode($endpoint)); ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this endpoint?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="endpoint_id" value="<?php echo $endpoint['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
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

<!-- Endpoint Details Modal -->
<div class="modal fade" id="endpointModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">RTB Endpoint Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="endpoint-details">
                    Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('URL copied to clipboard!', 'success');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showToast('Failed to copy URL', 'error');
    });
}

// Show endpoint details in modal
function showEndpointDetails(endpoint) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Name:</strong></td><td>${endpoint.name}</td></tr>
                    <tr><td><strong>Category:</strong></td><td>${endpoint.category_name}</td></tr>
                    <tr><td><strong>Floor Price:</strong></td><td>$${parseFloat(endpoint.floor_price).toFixed(4)}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge badge-status-${endpoint.status}">${endpoint.status}</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Supported Sizes</h6>
                <div class="targeting-options">
                    ${JSON.parse(endpoint.banner_sizes || '[]').map(size => 
                        `<span class="targeting-tag">${size}</span>`
                    ).join('')}
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Full Endpoint URL</h6>
                <div class="zone-code">
                    ${endpoint.url}
                </div>
                <button class="btn btn-sm btn-primary mt-2" onclick="copyToClipboard('${endpoint.url}')">
                    <i class="fas fa-copy"></i> Copy Full URL
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('endpoint-details').innerHTML = detailsHtml;
    new bootstrap.Modal(document.getElementById('endpointModal')).show();
}

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include 'includes/footer.php'; ?>