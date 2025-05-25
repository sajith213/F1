<?php
/**
 * Footer Template
 * 
 * Main footer template for the Petrol Pump Management System
 */

// Get current year for copyright
$current_year = date('Y');

// Only close HTML tags if not on login page
$current_page = basename($_SERVER['PHP_SELF']);

if ($current_page != 'login.php'):
?>
                <!-- End of main content -->
                </main>
                
                <!-- Footer -->
                <footer class="bg-footer border-t border-gray-200 p-4 mt-auto main-footer">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="text-sm text-gray-600">
                            &copy; <?= $current_year ?> <?= get_setting('company_name', 'Fuel Manager') ?>. All rights reserved.
                        </div>
                        <div class="text-xs text-gray-500 mt-1 md:mt-0">
                            Powered by Cinex.lk
                        </div>
                    </div>
                </footer>
            </div> <!-- End of main content wrapper -->
        </div> <!-- End of main container -->

        <!-- Mobile overlay for sidebar -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

        <!-- JavaScript libraries -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.3/cdn.min.js" defer></script>
        
        <script>
            // Initialize sidebar toggle functionality
            document.addEventListener('DOMContentLoaded', function() {
                const sidebarToggle = document.getElementById('sidebar-toggle');
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                
                if (sidebarToggle && sidebar && overlay) {
                    sidebarToggle.addEventListener('click', function() {
                        sidebar.classList.toggle('active');
                        overlay.classList.toggle('hidden');
                    });
                    
                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        overlay.classList.add('hidden');
                    });
                }
                
                // User dropdown functionality
                const userMenuButton = document.getElementById('user-menu-button');
                const userDropdown = document.getElementById('user-dropdown');
                
                if (userMenuButton && userDropdown) {
                    userMenuButton.addEventListener('click', function() {
                        userDropdown.classList.toggle('hidden');
                    });
                    
                    // Close dropdown when clicking outside
                    document.addEventListener('click', function(event) {
                        if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                            userDropdown.classList.add('hidden');
                        }
                    });
                }
            });
        </script>
        
        <?php if (isset($extra_js)): ?>
            <?= $extra_js ?>
        <?php endif; ?>
    </body>
</html>
<?php endif; ?>