<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get blacklisted IPs with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$blacklisted_ips = fetchAll("
    SELECT * FROM ip_blacklist 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
", [$limit, $offset]);

$total_count = fetchOne("SELECT COUNT(*) as count FROM ip_blacklist")['count'];
$total_pages = ceil($total_count / $limit);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_ips'])) {
        $action = $_POST['bulk_action'];
        $selected_ips = $_POST['selected_ips'];
        
        if ($action == 'delete' && !empty($selected_ips)) {
            $placeholders = str_repeat('?,', count($selected_ips) - 1) . '?';
            executeQuery("DELETE FROM ip_blacklist WHERE id IN ($placeholders)", $selected_ips);
            $_SESSION['success'] = count($selected_ips) . ' IP addresses removed from blacklist';
        }
        
        header('Location: fraud_blacklist.php');
        exit;
    }
    
    // Add single IP
    if (isset($_POST['add_ip'])) {
        $ip_address = sanitizeInput($_POST['ip_address']);
        $reason = sanitizeInput($_POST['reason']);
        
        if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
            try {
                executeQuery("INSERT INTO ip_blacklist (ip_address, reason) VALUES (?, ?)", [$ip_address, $reason]);
                $_SESSION['success'] = 'IP address added to blacklist successfully!';
            } catch (Exception $e) {
                $_SESSION['error'] = 'IP address already exists in blacklist!';
            }
        } else {
            $_SESSION['error'] = 'Invalid IP address format!';
        }
        header('Location: fraud_blacklist.php');
        exit;
    }
}

include '../includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-ban"></i> IP Blacklist Management</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addIpModal">
                <i class="fas fa-plus"></i> Add IP
            </button>
            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                <i class="fas fa-upload"></i> Bulk Import
            </button>
            <button class="btn btn-outline-success" onclick="exportBlacklist()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Blocked</div>
                            <div class="h5 mb-0"><?= number_format($total_count) ?></div>
                        </div>
                        <div class="h1 opacity-50"><i class="fas fa-ban"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        $today_count = fetchOne("SELECT COUNT(*) as count FROM ip_blacklist WHERE DATE(created_at) = CURDATE()")['count'];
        $week_count = fetchOne("SELECT COUNT(*) as count FROM ip_blacklist WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'];
        $auto_blocked = fetchOne("SELECT COUNT(*) as count FROM ip_blacklist WHERE reason LIKE '%Auto-blocked%'")['count'];
        ?>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Blocked Today</div>
                            <div class="h5 mb-0"><?= number_format($today_count) ?></div>
                        </div>
                        <div class="h1 opacity-50"><i class="fas fa-calendar-day"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">This Week</div>
                            <div class="h5 mb-0"><?= number_format($week_count) ?></div>
                        </div>
                        <div class="h1 opacity-50"><i class="fas fa-calendar-week"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Auto-Blocked</div>
                            <div class="h5 mb-0"><?= number_format($auto_blocked) ?></div>
                        </div>
                        <div class="h1 opacity-50"><i class="fas fa-robot"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- IP Blacklist Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list"></i> Blacklisted IP Addresses
                </h6>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="searchIp" placeholder="Search IP..." style="width: 200px;">
                    <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="blacklistForm">
                <div class="table-responsive">
                    <table class="table table-hover" id="blacklistTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Location</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blacklisted_ips as $ip): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ips[]" value="<?= $ip['id'] ?>" class="ip-checkbox">
                                </td>
                                <td>
                                    <code class="text-danger"><?= htmlspecialchars($ip['ip_address']) ?></code>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?= $ip['ip_address'] ?>')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($ip['reason']) ?></td>
                                <td>
                                    <span id="location-<?= $ip['id'] ?>" class="text-muted">Loading...</span>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($ip['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" onclick="showIpInfo('<?= $ip['ip_address'] ?>')" title="IP Info">
                                            <i class="fas fa-info"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="removeIP(<?= $ip['id'] ?>)" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Blacklist pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Add IP Modal -->
<div class="modal fade" id="addIpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add IP to Blacklist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">IP Address *</label>
                        <input type="text" class="form-control" name="ip_address" required 
                               placeholder="192.168.1.1" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <select class="form-select" name="reason" required onchange="toggleCustomReason(this)">
                            <option value="">Select reason</option>
                            <option value="Bot traffic">Bot traffic</option>
                            <option value="VPN/Proxy">VPN/Proxy</option>
                            <option value="Suspicious activity">Suspicious activity</option>
                            <option value="Manual block">Manual block</option>
                            <option value="Spam">Spam</option>
                            <option value="custom">Custom reason...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="customReasonDiv" style="display: none;">
                        <label class="form-label">Custom Reason</label>
                        <input type="text" class="form-control" id="customReason" placeholder="Enter custom reason">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_ip" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Add to Blacklist
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Import IP Addresses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">IP Addresses (one per line)</label>
                    <textarea class="form-control" id="bulkIpList" rows="10" 
                              placeholder="192.168.1.1&#10;10.0.0.1&#10;172.16.0.1"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Default Reason</label>
                    <input type="text" class="form-control" id="bulkReason" value="Bulk import" placeholder="Reason for blocking">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="bulkImport()">
                    <i class="fas fa-upload"></i> Import IPs
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomReason(select) {
    const customDiv = document.getElementById('customReasonDiv');
    const customInput = document.getElementById('customReason');
    
    if (select.value === 'custom') {
        customDiv.style.display = 'block';
        customInput.required = true;
    } else {
        customDiv.style.display = 'none';
        customInput.required = false;
    }
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.ip-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function bulkDelete() {
    const selected = document.querySelectorAll('.ip-checkbox:checked');
    
    if (selected.length === 0) {
        Swal.fire('No Selection', 'Please select IP addresses to delete.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Delete Selected IPs?',
        text: `This will remove ${selected.length} IP addresses from blacklist.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete them!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('blacklistForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_action';
            input.value = 'delete';
            form.appendChild(input);
            form.submit();
        }
    });
}

function removeIP(ipId) {
    Swal.fire({
        title: 'Remove from blacklist?',
        text: "This IP will be able to access the platform again.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/fraud_check.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'remove_ip_by_id',
                    ip_id: ipId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Removed!', 'IP address removed from blacklist.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            });
        }
    });
}

function showIpInfo(ipAddress) {
    fetch(`../api/ip_info.php?ip=${ipAddress}`)
        .then(response => response.json())
        .then(data => {
            let info = `<strong>IP:</strong> ${ipAddress}<br>`;
            info += `<strong>Country:</strong> ${data.country || 'Unknown'}<br>`;
            info += `<strong>City:</strong> ${data.city || 'Unknown'}<br>`;
            info += `<strong>ISP:</strong> ${data.isp || 'Unknown'}<br>`;
            info += `<strong>Organization:</strong> ${data.org || 'Unknown'}<br>`;
            if (data.proxy) info += `<strong>Proxy/VPN:</strong> Yes<br>`;
            if (data.tor) info += `<strong>Tor:</strong> Yes<br>`;
            
            Swal.fire({
                title: 'IP Information',
                html: info,
                icon: 'info'
            });
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to get IP information', 'error');
        });
}

function bulkImport() {
    const ipList = document.getElementById('bulkIpList').value;
    const reason = document.getElementById('bulkReason').value;
    
    if (!ipList.trim()) {
        Swal.fire('Error', 'Please enter IP addresses to import', 'error');
        return;
    }
    
    fetch('../api/fraud_check.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'bulk_import',
            ip_list: ipList,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success!', `${data.imported} IP addresses imported successfully.`, 'success')
                .then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('bulkImportModal')).hide();
                    location.reload();
                });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    });
}

function exportBlacklist() {
    window.open('../api/export_data.php?type=blacklist');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'IP address copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

// Search functionality
document.getElementById('searchIp').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#blacklistTable tbody tr');
    
    rows.forEach(row => {
        const ipCell = row.cells[1].textContent.toLowerCase();
        const reasonCell = row.cells[2].textContent.toLowerCase();
        
        if (ipCell.includes(filter) || reasonCell.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Load IP locations asynchronously
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($blacklisted_ips as $ip): ?>
    fetch(`../api/ip_info.php?ip=<?= $ip['ip_address'] ?>`)
        .then(response => response.json())
        .then(data => {
            const locationText = data.country ? `${data.city || ''} ${data.country}`.trim() : 'Unknown';
            document.getElementById('location-<?= $ip['id'] ?>').textContent = locationText;
        })
        .catch(() => {
            document.getElementById('location-<?= $ip['id'] ?>').textContent = 'Unknown';
        });
    <?php endforeach; ?>
});
</script>

<?php include '../includes/footer.php'; ?>