<?php
$pageTitle = 'Zone Management';
include 'includes/header.php';

$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'create':
                    $website_id = (int)$_POST['website_id'];
                    $name = sanitize($_POST['name']);
                    $zone_type = sanitize($_POST['zone_type']);
                    $size = sanitize($_POST['size']);
                    
                    $zone_id = $db->insert('zones', [
                        'website_id' => $website_id,
                        'name' => $name,
                        'zone_type' => $zone_type,
                        'size' => $size,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Zone created successfully!';
                    break;
                    
                case 'update_status':
                    $zone_id = (int)$_POST['zone_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('zones', 
                        ['status' => $status], 
                        'id = ?', 
                        [$zone_id]
                    );
                    
                    $success = 'Zone status updated successfully!';
                    break;
                    
                case 'delete':
                    $zone_id = (int)$_POST['zone_id'];
                    $db->delete('zones', 'id = ?', [$zone_id]);
                    $success = 'Zone deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get zones with website and publisher info
$zones = $db->fetchAll("
    SELECT z.*, w.name as website_name, w.url as website_url, 
           p.name as publisher_name, c.name as category_name
    FROM zones z 
    LEFT JOIN websites w ON z.website_id = w.id 
    LEFT JOIN publishers p ON w.publisher_id = p.id 
    LEFT JOIN categories c ON w.category_id = c.id 
    ORDER BY z.created_at DESC
");

// Get websites for dropdown
$websites = $db->fetchAll("
    SELECT w.id, w.name, w.url, p.name as publisher_name 
    FROM websites w 
    LEFT JOIN publishers p ON w.publisher_id = p.id 
    WHERE w.status = 'active' 
    ORDER BY w.name
");

// Get banner sizes
$banner_sizes = $db->fetchAll("SELECT name FROM banner_sizes WHERE status = 'active' ORDER BY name");

// Generate zone code function
function generateZoneCode($zone_id, $zone_type, $size = null) {
    $base_url = "https://ads.example.com"; // Replace with your actual domain
    
    switch ($zone_type) {
        case 'banner':
            $dimensions = explode('x', $size ?? '300x250');
            $width = $dimensions[0] ?? '300';
            $height = $dimensions[1] ?? '250';
            
            return "<!-- Banner Zone {$zone_id} -->
<script type=\"text/javascript\">
var adstart_zone_id = {$zone_id};
var adstart_width = {$width};
var adstart_height = {$height};
</script>
<script type=\"text/javascript\" src=\"{$base_url}/serve/banner.php?zone_id={$zone_id}\"></script>
<noscript>
    <a href=\"{$base_url}/serve/banner.php?zone_id={$zone_id}&noscript=1\" target=\"_blank\">
        <img src=\"{$base_url}/serve/banner.php?zone_id={$zone_id}&noscript=1&format=img\" 
             width=\"{$width}\" height=\"{$height}\" border=\"0\" alt=\"Advertisement\">
    </a>
</noscript>";

        case 'popup':
            return "<!-- Popup Zone {$zone_id} -->
<script type=\"text/javascript\">
var adstart_zone_id = {$zone_id};
var adstart_popup_frequency = 1; // Show once per session
</script>
<script type=\"text/javascript\" src=\"{$base_url}/serve/popup.php?zone_id={$zone_id}\"></script>";

        case 'native':
            return "<!-- Native Zone {$zone_id} -->
<div id=\"adstart_native_{$zone_id}\" class=\"adstart-native-container\"></div>
<script type=\"text/javascript\">
var adstart_zone_id = {$zone_id};
var adstart_native_container = 'adstart_native_{$zone_id}';
</script>
<script type=\"text/javascript\" src=\"{$base_url}/serve/native.php?zone_id={$zone_id}\"></script>";

        default:
            return "<!-- Zone {$zone_id} -->
<script src=\"{$base_url}/serve/zone.php?id={$zone_id}\"></script>";
    }
}
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

<!-- Zone Types Info -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="fas fa-image fa-2x text-primary mb-2"></i>
                <h6 class="card-title">Banner Zones</h6>
                <p class="card-text small">Standard display banners with various sizes for optimal ad placement.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-external-link-alt fa-2x text-warning mb-2"></i>
                <h6 class="card-title">Popup Zones</h6>
                <p class="card-text small">Popup and popunder ads for high-impact advertising campaigns.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-newspaper fa-2x text-success mb-2"></i>
                <h6 class="card-title">Native Zones</h6>
                <p class="card-text small">Native ads that blend seamlessly with website content.</p>
            </div>
        </div>
    </div>
</div>

<!-- Create New Zone -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Create New Zone</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Zone Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Descriptive name for this ad zone</div>
                            <div class="invalid-feedback">Please provide a zone name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="website_id" class="form-label">Website <span class="text-danger">*</span></label>
                            <select class="form-select" id="website_id" name="website_id" required>
                                <option value="">Select Website</option>
                                <?php foreach ($websites as $website): ?>
                                    <option value="<?php echo $website['id']; ?>">
                                        <?php echo htmlspecialchars($website['name']); ?> 
                                        (<?php echo htmlspecialchars($website['publisher_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a website.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="zone_type" class="form-label">Zone Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="zone_type" name="zone_type" required onchange="toggleSizeField()">
                                <option value="">Select Zone Type</option>
                                <option value="banner">Banner</option>
                                <option value="popup">Popup</option>
                                <option value="native">Native</option>
                            </select>
                            <div class="invalid-feedback">Please select a zone type.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="size" class="form-label">Size</label>
                            <select class="form-select" id="size" name="size" disabled>
                                <option value="">Select size (Banner zones only)</option>
                                <?php foreach ($banner_sizes as $size): ?>
                                    <option value="<?php echo $size['name']; ?>">
                                        <?php echo $size['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Size selection is required for banner zones</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Zone
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Zones -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Ad Zones</h5>
            </div>
            <div class="card-body">
                <?php if (empty($zones)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-th-large fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No zones found</h5>
                        <p class="text-muted">Create your first ad zone to start placing ads on publisher websites.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Zone Name</th>
                                    <th>Website</th>
                                    <th>Publisher</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zones as $zone): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($zone['name']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $zone['id']; ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($zone['website_name'] ?? 'Unknown'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($zone['website_url'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($zone['publisher_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                switch($zone['zone_type']) {
                                                    case 'banner': echo 'bg-primary'; break;
                                                    case 'popup': echo 'bg-warning'; break;
                                                    case 'native': echo 'bg-success'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                                ?>">
                                                <?php echo ucfirst($zone['zone_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $zone['size'] ? htmlspecialchars($zone['size']) : '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $zone['status']; ?>">
                                                <?php echo ucfirst($zone['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($zone['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="showZoneCode(<?php echo $zone['id']; ?>, '<?php echo $zone['zone_type']; ?>', '<?php echo $zone['size']; ?>')">
                                                    <i class="fas fa-code"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="zone_id" value="<?php echo $zone['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $zone['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $zone['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $zone['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this zone?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="zone_id" value="<?php echo $zone['id']; ?>">
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

<!-- Zone Code Modal -->
<div class="modal fade" id="zoneCodeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Zone Implementation Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Copy and paste this code into your website where you want the ads to appear.
                </p>
                <div class="zone-code" id="zone-code-content">
                    Loading...
                </div>
                <div class="text-end mt-3">
                    <button class="btn btn-primary" onclick="copyZoneCode()">
                        <i class="fas fa-copy"></i> Copy Code
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle size field based on zone type
function toggleSizeField() {
    const zoneType = document.getElementById('zone_type').value;
    const sizeField = document.getElementById('size');
    
    if (zoneType === 'banner') {
        sizeField.disabled = false;
        sizeField.required = true;
    } else {
        sizeField.disabled = true;
        sizeField.required = false;
        sizeField.value = '';
    }
}

// Show zone code in modal
function showZoneCode(zoneId, zoneType, size) {
    const zoneCode = generateZoneCodeClient(zoneId, zoneType, size);
    document.getElementById('zone-code-content').textContent = zoneCode;
    new bootstrap.Modal(document.getElementById('zoneCodeModal')).show();
}

// Generate zone code on client side (similar to PHP function)
function generateZoneCodeClient(zoneId, zoneType, size) {
    const baseUrl = "https://ads.example.com"; // Replace with your actual domain
    
    switch (zoneType) {
        case 'banner':
            const dimensions = size ? size.split('x') : ['300', '250'];
            const width = dimensions[0] || '300';
            const height = dimensions[1] || '250';
            
            return `<!-- Banner Zone ${zoneId} -->
<script type="text/javascript">
var adstart_zone_id = ${zoneId};
var adstart_width = ${width};
var adstart_height = ${height};
</script>
<script type="text/javascript" src="${baseUrl}/serve/banner.php?zone_id=${zoneId}"></script>
<noscript>
    <a href="${baseUrl}/serve/banner.php?zone_id=${zoneId}&noscript=1" target="_blank">
        <img src="${baseUrl}/serve/banner.php?zone_id=${zoneId}&noscript=1&format=img" 
             width="${width}" height="${height}" border="0" alt="Advertisement">
    </a>
</noscript>`;

        case 'popup':
            return `<!-- Popup Zone ${zoneId} -->
<script type="text/javascript">
var adstart_zone_id = ${zoneId};
var adstart_popup_frequency = 1; // Show once per session
</script>
<script type="text/javascript" src="${baseUrl}/serve/popup.php?zone_id=${zoneId}"></script>`;

        case 'native':
            return `<!-- Native Zone ${zoneId} -->
<div id="adstart_native_${zoneId}" class="adstart-native-container"></div>
<script type="text/javascript">
var adstart_zone_id = ${zoneId};
var adstart_native_container = 'adstart_native_${zoneId}';
</script>
<script type="text/javascript" src="${baseUrl}/serve/native.php?zone_id=${zoneId}"></script>`;

        default:
            return `<!-- Zone ${zoneId} -->
<script src="${baseUrl}/serve/zone.php?id=${zoneId}"></script>`;
    }
}

// Copy zone code to clipboard
function copyZoneCode() {
    const codeContent = document.getElementById('zone-code-content').textContent;
    navigator.clipboard.writeText(codeContent).then(function() {
        showToast('Zone code copied to clipboard!', 'success');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showToast('Failed to copy code', 'error');
    });
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