                    </div>
                </div>
            </div>
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
        // Initialize DataTables
        $(document).ready(function() {
            $('.data-table').DataTable({
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
                    }
                }
            });
        });
        
        // Auto refresh statistics every 30 seconds
        setInterval(function() {
            if ($('#live-stats').length) {
                $('#live-stats').load(location.href + ' #live-stats > *');
            }
        }, 30000);
        
        // Confirm delete actions
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        }
        
        // Show loading spinner
        function showLoading(button) {
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
            const alert = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alerts-container').html(alert);
            setTimeout(() => $('.alert').fadeOut(), 5000);
        }
        
        // Error message
        function showError(message) {
            const alert = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alerts-container').html(alert);
        }
    </script>
</body>
</html>
