<?php
$pageTitle = 'Creative Management';
$breadcrumb = [
    ['text' => 'Creative Management']
];

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Get campaign context if provided
$campaign_id = $_GET['campaign_id'] ?? null;
$campaign_type = $_GET['type'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $campaign_id = (int)$_POST['campaign_id'];
        $campaign_type = sanitize($_POST['campaign_type']);
        $name = sanitize($_POST['name']);
        $creative_type = sanitize($_POST['creative_type']);
        $click_url = sanitize($_POST['click_url']);
        $content_html = $_POST['content_html'] ?? null;
        $content_url = $_POST['content_url'] ?? null;
        $image_url = $_POST['image_url'] ?? null;
        $width = $_POST['width'] ? (int)$_POST['width'] : null;
        $height = $_POST['height'] ? (int)$_POST['height'] : null;
        
        // Validate required fields
        if (empty($name) || empty($campaign_id) || empty($campaign_type) || empty($click_url)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Validate URLs
        if (!filter_var($click_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Please provide a valid click URL');
        }
        
        if ($content_url && !filter_var($content_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Please provide a valid content URL');
        }
        
        if ($image_url && !filter_var($image_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Please provide a valid image URL');
        }
        
        // Validate creative content based on type
        switch ($creative_type) {
            case 'html5':
                if (empty($content_html)) {
                    throw new Exception('HTML5 content is required for HTML5 creatives');
                }
                break;
            case 'third_party':
                if (empty($content_url)) {
                    throw new Exception('Third-party script URL is required');
                }
                break;
            case 'image':
                if (empty($image_url)) {
                    throw new Exception('Image URL is required for image creatives');
                }
                if (!$width || !$height) {
                    throw new Exception('Width and height are required for image creatives');
                }
                break;
        }
        
        $creativeData = [
            'campaign_id' => $campaign_id,
            'campaign_type' => $campaign_type,
            'name' => $name,
            'creative_type' => $creative_type,
            'content_html' => $content_html,
            'content_url' => $content_url,
            'click_url' => $click_url,
            'image_url' => $image_url,
            'width' => $width,
            'height' => $height,
            'status' => 'pending_review',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $db->insert('creatives', $creativeData);
        $creativeId = $db->lastInsertId();
        
        $_SESSION['success'] = "Creative '{$name}' created successfully!";
        header('Location: creative.php' . ($campaign_id ? "?campaign_id={$campaign_id}&type={$campaign_type}" : ''));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get creatives with campaign info
try {
    $whereClause = '';
    $params = [];
    
    if ($campaign_id && $campaign_type) {
        $whereClause = 'WHERE cr.campaign_id = ? AND cr.campaign_type = ?';
        $params = [$campaign_id, $campaign_type];
    }
    
    $creatives = $db->fetchAll("
        SELECT cr.*, 
               CASE 
                   WHEN cr.campaign_type = 'rtb' THEN rtb.name 
                   WHEN cr.campaign_type = 'ron' THEN ron.name 
               END as campaign_name,
               CASE 
                   WHEN cr.campaign_type = 'rtb' THEN rtb.status 
                   WHEN cr.campaign_type = 'ron' THEN ron.status 
               END as campaign_status
        FROM creatives cr
        LEFT JOIN rtb_campaigns rtb ON cr.campaign_id = rtb.id AND cr.campaign_type = 'rtb'
        LEFT JOIN ron_campaigns ron ON cr.campaign_id = ron.id AND cr.campaign_type = 'ron'
        {$whereClause}
        ORDER BY cr.created_at DESC
    ", $params);
} catch (Exception $e) {
    error_log("Creatives query error: " . $e->getMessage());
    $creatives = [];
}

// Get campaigns for dropdown
try {
    $rtbCampaigns = $db->fetchAll("SELECT id, name, 'rtb' as type FROM rtb_campaigns WHERE status != 'completed' ORDER BY name");
    $ronCampaigns = $db->fetchAll("SELECT id, name, 'ron' as type FROM ron_campaigns WHERE status != 'completed' ORDER BY name");
    $campaigns = array_merge($rtbCampaigns, $ronCampaigns);
} catch (Exception $e) {
    $campaigns = [];
}

// Get campaign details if viewing specific campaign
$campaignDetails = null;
if ($campaign_id && $campaign_type) {
    try {
        $table = $campaign_type == 'rtb' ? 'rtb_campaigns' : 'ron_campaigns';
        $campaignDetails = $db->fetch("SELECT * FROM {$table} WHERE id = ?", [$campaign_id]);
    } catch (Exception $e) {
        $campaignDetails = null;
    }
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Creative Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <?php if ($campaignDetails): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo $campaign_type; ?>-<?php echo $campaign_type == 'rtb' ? 'sell' : 'campaign'; ?>.php">
                                    <?php echo strtoupper($campaign_type); ?> Campaigns
                                </a>
                            </li>
                            <li class="breadcrumb-item"><?php echo htmlspecialchars($campaignDetails['name']); ?></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active">Creatives</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCreativeModal">
                    <i class="fas fa-plus me-2"></i>Create Creative
                </button>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper">
        <!-- Campaign Context -->
        <?php if ($campaignDetails): ?>
            <div class="alert alert-info mb-4">
                <div class="row align-items-center">
                    <div class="col-md-1 text-center">
                        <i class="fas fa-<?php echo $campaign_type == 'rtb' ? 'exchange-alt' : 'network-wired'; ?> fa-2x text-info"></i>
                    </div>
                    <div class="col-md-11">
                        <h5 class="mb-1">
                            Managing creatives for: <strong><?php echo htmlspecialchars($campaignDetails['name']); ?></strong>
                        </h5>
                        <p class="mb-0">
                            <span class="badge bg-<?php echo $campaign_type == 'rtb' ? 'primary' : 'success'; ?> me-2">
                                <?php echo strtoupper($campaign_type); ?> Campaign
                            </span>
                            <span class="badge bg-<?php echo match($campaignDetails['status']) {
                                'active' => 'success',
                                'paused' => 'warning',
                                'completed' => 'secondary',
                                default => 'info'
                            }; ?>">
                                <?php echo ucfirst($campaignDetails['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Creative Types Info -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-code fa-2x text-primary mb-2"></i>
                        <h6>HTML5 Banners</h6>
                        <small class="text-muted">Interactive HTML/CSS/JS creatives</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-external-link-alt fa-2x text-success mb-2"></i>
                        <h6>Third-party Scripts</h6>
                        <small class="text-muted">External JavaScript ad tags</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-image fa-2x text-warning mb-2"></i>
                        <h6>Image Banners</h6>
                        <small class="text-muted">Static image advertisements</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-video fa-2x text-info mb-2"></i>
                        <h6>Video Ads</h6>
                        <small class="text-muted">Video advertisement content</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Creatives Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-images me-2"></i>
                    <?php echo $campaignDetails ? 'Campaign Creatives' : 'All Creatives'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($creatives)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-images fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Creatives Found</h4>
                        <p class="text-muted mb-4">
                            <?php echo $campaignDetails ? 'Add creatives to this campaign to start serving ads' : 'Create your first creative to start advertising'; ?>
                        </p>
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createCreativeModal">
                            <i class="fas fa-plus me-2"></i>Create Your First Creative
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="creativesTable">
                            <thead>
                                <tr>
                                    <th>Creative</th>
                                    <?php if (!$campaignDetails): ?>
                                        <th>Campaign</th>
                                    <?php endif; ?>
                                    <th>Type</th>
                                    <th>Dimensions</th>
                                    <th>Click URL</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($creatives as $creative): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($creative['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($creative['image_url']); ?>" 
                                                         alt="Creative" class="me-2" style="width: 50px; height: 32px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div class="me-2 d-flex align-items-center justify-content-center bg-light" 
                                                         style="width: 50px; height: 32px; border-radius: 4px;">
                                                        <i class="fas fa-<?php echo match($creative['creative_type']) {
                                                            'html5' => 'code',
                                                            'third_party' => 'external-link-alt',
                                                            'video' => 'video',
                                                            default => 'image'
                                                        }; ?> text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($creative['name']); ?></strong>
                                                    <?php if ($creative['content_html']): ?>
                                                        <br><small class="text-muted">HTML5 Content</small>
                                                    <?php elseif ($creative['content_url']): ?>
                                                        <br><small class="text-muted">External Script</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <?php if (!$campaignDetails): ?>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($creative['campaign_name'] ?? 'Unknown'); ?></strong>
                                                    <br><span class="badge bg-<?php echo $creative['campaign_type'] == 'rtb' ? 'primary' : 'success'; ?> badge-sm">
                                                        <?php echo strtoupper($creative['campaign_type']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-<?php echo match($creative['creative_type']) {
                                                'html5' => 'primary',
                                                'third_party' => 'success',
                                                'image' => 'warning',
                                                'video' => 'info',
                                                default => 'secondary'
                                            }; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $creative['creative_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($creative['width'] && $creative['height']): ?>
                                                <?php echo $creative['width']; ?>×<?php echo $creative['height']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($creative['click_url']); ?>" 
                                               target="_blank" class="text-decoration-none" title="Open click URL">
                                                <small><?php echo htmlspecialchars(parse_url($creative['click_url'], PHP_URL_HOST)); ?></small>
                                                <i class="fas fa-external-link-alt ms-1"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($creative['status']) {
                                                'active' => 'success',
                                                'inactive' => 'secondary',
                                                'pending_review' => 'warning',
                                                default => 'info'
                                            }; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $creative['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($creative['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="previewCreative(<?php echo $creative['id']; ?>)" title="Preview">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editCreative(<?php echo $creative['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteCreative(<?php echo $creative['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

<!-- Create Creative Modal -->
<div class="modal fade" id="createCreativeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Create Creative
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <!-- Basic Info -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Creative Information</h6>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Creative Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="Enter creative name">
                                <div class="invalid-feedback">Please provide a creative name.</div>
                            </div>
                            
                            <?php if (!$campaign_id): ?>
                                <div class="mb-3">
                                    <label for="campaign_select" class="form-label">Campaign <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="campaign_select" name="campaign_select" required>
                                        <option value="">Select Campaign</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo $campaign['id'] . '|' . $campaign['type']; ?>">
                                                <?php echo htmlspecialchars($campaign['name']); ?> 
                                                (<?php echo strtoupper($campaign['type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a campaign.</div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                                <input type="hidden" name="campaign_type" value="<?php echo $campaign_type; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="creative_type" class="form-label">Creative Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="creative_type" name="creative_type" required>
                                    <option value="">Select Type</option>
                                    <option value="html5">HTML5 Banner</option>
                                    <option value="third_party">Third-party Script</option>
                                    <option value="image">Image Banner</option>
                                    <option value="video">Video Ad</option>
                                </select>
                                <div class="invalid-feedback">Please select a creative type.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="click_url" class="form-label">Click URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="click_url" name="click_url" required 
                                       placeholder="https://example.com/landing-page">
                                <div class="invalid-feedback">Please provide a valid click URL.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="width" class="form-label">Width (px)</label>
                                        <input type="number" class="form-control" id="width" name="width" 
                                               placeholder="300">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="height" class="form-label">Height (px)</label>
                                        <input type="number" class="form-control" id="height" name="height" 
                                               placeholder="250">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Creative Content -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Creative Content</h6>
                            
                            <!-- HTML5 Content -->
                            <div class="mb-3" id="html5_content" style="display: none;">
                                <label for="content_html" class="form-label">HTML5 Content</label>
                                <textarea class="form-control" id="content_html" name="content_html" rows="8" 
                                          placeholder="Enter your HTML/CSS/JavaScript code here..."></textarea>
                                <div class="form-text">
                                    Include complete HTML, CSS, and JavaScript for your interactive banner.
                                </div>
                            </div>
                            
                            <!-- Third-party URL -->
                            <div class="mb-3" id="third_party_content" style="display: none;">
                                <label for="content_url" class="form-label">Third-party Script URL</label>
                                <input type="url" class="form-control" id="content_url" name="content_url" 
                                       placeholder="https://ads.example.com/script.js">
                                <div class="form-text">
                                    URL to external JavaScript ad tag or iframe source.
                                </div>
                            </div>
                            
                            <!-- Image URL -->
                            <div class="mb-3" id="image_content" style="display: none;">
                                <label for="image_url" class="form-label">Image URL</label>
                                <input type="url" class="form-control" id="image_url" name="image_url" 
                                       placeholder="https://example.com/banner.jpg">
                                <div class="form-text">
                                    Direct URL to your banner image (JPG, PNG, GIF).
                                </div>
                            </div>
                            
                            <!-- Video Content -->
                            <div class="mb-3" id="video_content" style="display: none;">
                                <label for="video_url" class="form-label">Video URL</label>
                                <input type="url" class="form-control" id="video_url" name="video_url" 
                                       placeholder="https://example.com/video.mp4">
                                <div class="form-text">
                                    Direct URL to your video file (MP4, WebM).
                                </div>
                            </div>
                            
                            <!-- Preview Area -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-eye me-2"></i>Creative Preview
                                    </h6>
                                </div>
                                <div class="card-body text-center" id="creative_preview">
                                    <div class="text-muted py-4">
                                        <i class="fas fa-image fa-2x mb-2"></i><br>
                                        Preview will appear here
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Creative
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#creativesTable').DataTable({
        order: [[<?php echo $campaignDetails ? 5 : 6; ?>, 'desc']], // Sort by created date
        pageLength: 25,
        responsive: true
    });
    
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#createCreativeModal')
    });
    
    // Creative type change handler
    $('#creative_type').on('change', function() {
        const type = $(this).val();
        
        // Hide all content fields
        $('#html5_content, #third_party_content, #image_content, #video_content').hide();
        
        // Show relevant field
        switch(type) {
            case 'html5':
                $('#html5_content').show();
                break;
            case 'third_party':
                $('#third_party_content').show();
                break;
            case 'image':
                $('#image_content').show();
                break;
            case 'video':
                $('#video_content').show();
                break;
        }
    });
    
    // Campaign selection handler (if not in campaign context)
    $('#campaign_select').on('change', function() {
        const value = $(this).val();
        if (value) {
            const [campaignId, campaignType] = value.split('|');
            $('input[name="campaign_id"]').remove();
            $('input[name="campaign_type"]').remove();
            $(this).closest('form').append(`
                <input type="hidden" name="campaign_id" value="${campaignId}">
                <input type="hidden" name="campaign_type" value="${campaignType}">
            `);
        }
    });
    
    // Real-time preview functionality
    $('#content_html, #image_url').on('input', function() {
        updatePreview();
    });
    
    function updatePreview() {
        const type = $('#creative_type').val();
        const preview = $('#creative_preview');
        
        switch(type) {
            case 'html5':
                const html = $('#content_html').val();
                if (html.trim()) {
                    preview.html('<iframe srcdoc="' + html.replace(/"/g, '&quot;') + '" style="width: 100%; height: 200px; border: 1px solid #ddd;"></iframe>');
                } else {
                    preview.html('<div class="text-muted py-4"><i class="fas fa-code fa-2x mb-2"></i><br>HTML5 preview</div>');
                }
                break;
            case 'image':
                const imageUrl = $('#image_url').val();
                if (imageUrl) {
                    preview.html(`<img src="${imageUrl}" style="max-width: 100%; max-height: 200px;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNmM3NTdkIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+SW1hZ2UgRXJyb3I8L3RleHQ+PC9zdmc+'">`);
                } else {
                    preview.html('<div class="text-muted py-4"><i class="fas fa-image fa-2x mb-2"></i><br>Image preview</div>');
                }
                break;
            default:
                preview.html('<div class="text-muted py-4"><i class="fas fa-image fa-2x mb-2"></i><br>Preview will appear here</div>');
        }
    }
});

function previewCreative(id) {
    // TODO: Implement creative preview
    showWarning('Creative preview coming soon!');
}

function editCreative(id) {
    // TODO: Implement edit functionality
    showWarning('Edit functionality coming soon!');
}

function deleteCreative(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the creative.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: Implement delete functionality
            showWarning('Delete functionality coming soon!');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>