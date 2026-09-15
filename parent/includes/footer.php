<?php
/**
 * Parent Panel Footer Include
 * Child Vaccination Management System (VMS)
 */
?>
        <footer class="footer">
            <div class="container-fluid d-flex justify-content-between">
                <nav class="pull-left">
                    <ul class="nav">
                        <li class="nav-item">
                            <a class="nav-link text-muted" href="<?= BASE_URL ?>parent/index.php">Parent Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-muted" href="<?= BASE_URL ?>parent/children.php">Child Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-muted" href="<?= BASE_URL ?>parent/book-appointment.php">Book Hospital</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-muted" href="<?= BASE_URL ?>parent/reports.php">Vaccination Reports</a>
                        </li>
                    </ul>
                </nav>
                <div class="copyright text-muted">
                    &copy; <?= date('Y') ?> <strong>VaxCare</strong> &bull; Parent Child Health Portal
                </div>
            </div>
        </footer>
    </div>
    <!-- End Wrapper -->

    <!-- Core JS Files -->
    <script src="<?= BASE_URL ?>assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/core/popper.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="<?= BASE_URL ?>assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Datatables -->
    <script src="<?= BASE_URL ?>assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="<?= BASE_URL ?>assets/js/kaiadmin.min.js"></script>

    <script>
        // Dark / Light Mode Switcher
        function updateThemeIcon(theme) {
            var icon = document.getElementById('themeIcon');
            var btn = document.getElementById('themeToggleBtn');
            if (!icon) return;

            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
                if (btn) btn.setAttribute('title', 'Switch to Light Mode');
            } else {
                icon.className = 'fas fa-moon';
                if (btn) btn.setAttribute('title', 'Switch to Dark Mode');
            }
        }

        function toggleTheme() {
            var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            var newTheme = (currentTheme === 'dark') ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            if (document.body) {
                document.body.setAttribute('data-theme', newTheme);
                document.body.setAttribute('data-background-color', newTheme === 'dark' ? 'dark' : 'white');
            }
            localStorage.setItem('vaxcare_theme', newTheme);
            updateThemeIcon(newTheme);
        }

        $(document).ready(function() {
            var savedTheme = localStorage.getItem('vaxcare_theme') || 'light';
            if (document.body) {
                document.body.setAttribute('data-theme', savedTheme);
                document.body.setAttribute('data-background-color', savedTheme === 'dark' ? 'dark' : 'white');
            }
            updateThemeIcon(savedTheme);

            // Auto initialize standard datatables
            if ($.fn.DataTable) {
                $('.datatable-custom').DataTable({
                    pageLength: 10,
                    responsive: true,
                    order: [],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search records..."
                    }
                });
            }

            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
