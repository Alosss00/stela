            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/language-switcher.js"></script>
    <script>
        // Mobile Sidebar Toggle Functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
        
        // Close sidebar when clicking on a menu item (mobile)
        document.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 767) {
                    closeSidebar();
                }
            });
        });
        
        // Close sidebar when window is resized to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

