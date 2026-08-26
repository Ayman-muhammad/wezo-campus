<?php
/**
 * WEZO CAMPUS HUB - Admin Template Footer
 * Powered by AYGLOBE INC
 */
?>
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>
                            &copy; <?php echo date('Y'); ?> WEZO CAMPUS HUB. 
                            Powered by <strong>AYGLOBE INC</strong>. 
                            All rights reserved.
                        </span>
                        <div class="mt-1 small text-muted">
                            <span class="me-3">
                                <i class="fas fa-users text-primary me-1"></i>
                                <?php 
                                $db = Core\Database::getInstance();
                                $totalUsers = $db->fetchColumn("SELECT COUNT(*) FROM users");
                                echo number_format($totalUsers); ?> Users
                            </span>
                            <span class="me-3">
                                <i class="fas fa-server text-success me-1"></i>
                                v<?php echo $_ENV['APP_VERSION'] ?? '1.0.0'; ?>
                            </span>
                            <span>
                                <i class="fas fa-database text-info me-1"></i>
                                <?php echo date('H:i:s'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Select "Logout" below if you are ready to end your current session.
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    
    <!-- Admin Custom JS -->
    <script src="/admin/assets/js/admin.js"></script>
    
    <!-- Scripts -->
    <script>
    // Sidebar Toggle
    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-toggled');
        $('.sidebar').toggleClass('toggled');
    });
    
    // Auto-dismiss alerts
    $(document).ready(function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert:not(.alert-permanent)').fadeOut('slow');
        }, 5000);
        
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize DataTables on tables with class 'datatable'
        $('.datatable').DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });
    });
    
    // Confirm before dangerous actions
    $(document).on('click', '.confirm-action', function(e) {
        if (!confirm($(this).data('confirm') || 'Are you sure?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // AJAX CSRF token setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Handle AJAX errors
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (jqxhr.status === 401) {
            // Unauthorized - redirect to login
            window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
        } else if (jqxhr.status === 419) {
            // CSRF token mismatch
            alert('Session expired. Please refresh the page.');
            window.location.reload();
        }
    });
    </script>
    
    <?php if (isset($customScripts)): ?>
    <!-- Page-specific scripts -->
    <?php echo $customScripts; ?>
    <?php endif; ?>
</body>
</html>