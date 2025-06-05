        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const utcTime = now.toISOString().slice(0, 19).replace('T', ' ');
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = utcTime;
            }
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('show');
                }
            });
        }
        
        // Initialize DataTables with better error handling
        $(document).ready(function() {
            try {
                // Only initialize DataTable if it exists and has data
                const tables = $('.data-table, #zonesTable');
                tables.each(function() {
                    const table = $(this);
                    const tbody = table.find('tbody');
                    const rows = tbody.find('tr');
                    
                    // Check if table has valid data rows
                    let hasValidData = false;
                    rows.each(function() {
                        const row = $(this);
                        const cells = row.find('td');
                        if (cells.length > 0 && !row.hasClass('no-data')) {
                            hasValidData = true;
                            return false; // break
                        }
                    });
                    
                    if (hasValidData) {
                        table.DataTable({
                            responsive: true,
                            pageLength: 25,
                            order: [[0, 'desc']],
                            language: {
                                search: "Search:",
                                lengthMenu: "Show _MENU_ entries",
                                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                                paginate: {
                                    first: "First",
                                    last: "Last",
                                    next: "Next",
                                    previous: "Previous"
                                },
                                emptyTable: "No data available",
                                zeroRecords: "No matching records found"
                            },
                            columnDefs: [
                                { 
                                    targets: 'no-sort', 
                                    orderable: false 
                                }
                            ],
                            drawCallback: function(settings) {
                                // Re-initialize tooltips after table redraw
                                $('[data-bs-toggle="tooltip"]').tooltip();
                            },
                            initComplete: function(settings, json) {
                                console.log('DataTable initialized successfully');
                            }
                        });
                    }
                });
            } catch (error) {
                console.error('DataTable initialization error:', error);
                // Continue without DataTable functionality
            }
        });
        
        // Auto refresh statistics every 30 seconds
        setInterval(function() {
            const liveStats = $('#live-stats');
            if (liveStats.length && !document.hidden) {
                // Use fetch API for better error handling
                fetch(location.href + (location.href.includes('?') ? '&' : '?') + 'ajax=1')
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newStats = doc.querySelector('#live-stats');
                        if (newStats) {
                            liveStats.html(newStats.innerHTML);
                        }
                    })
                    .catch(error => {
                        console.error('Stats refresh error:', error);
                    });
            }
        }, 30000);
        
        // Confirm delete actions
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        }
        
        // Show loading spinner
        function showLoading(button) {
            if (!button) return;
            
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            button.disabled = true;
            
            setTimeout(function() {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }, 2000);
        }
        
        // Format numbers
        function formatNumber(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(1) + 'B';
            } else if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
        
        // Format currency
        function formatCurrency(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        }
        
        // Format percentage
        function formatPercentage(value) {
            return parseFloat(value).toFixed(2) + '%';
        }
        
        // Success message
        function showSuccess(message) {
            const alertContainer = document.getElementById('alerts-container');
            if (alertContainer) {
                const alert = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                alertContainer.innerHTML = alert;
                setTimeout(() => {
                    const alertElement = alertContainer.querySelector('.alert');
                    if (alertElement) {
                        alertElement.remove();
                    }
                }, 5000);
            }
        }
        
        // Error message
        function showError(message) {
            const alertContainer = document.getElementById('alerts-container');
            if (alertContainer) {
                const alert = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                alertContainer.innerHTML = alert;
            }
        }
        
        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                sidebar && sidebar.classList.contains('show') && 
                !sidebar.contains(event.target) && 
                sidebarToggle && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
        
        // Global error handling
        window.addEventListener('error', function(e) {
            console.error('Global JavaScript error:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
        });
    </script>
</body>
</html>
