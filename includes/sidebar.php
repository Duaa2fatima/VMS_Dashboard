<?php
/**
 * Admin Sidebar Navigation
 * Child Vaccination Management System (VMS)
 */
?>
<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>admin/index.php" class="app-brand">
                <i class="fas fa-shield-virus text-primary" style="font-size: 24px;"></i>
                <span>VaxCare</span>
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">

                <li class="nav-item <?= ($activePage === 'admin_dashboard') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <p>Admin Dashboard</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">CHILD & VACCINATION</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_parents') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/parents.php">
                        <i class="fas fa-user-friends"></i>
                        <p>Parents Registration</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_children') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/children.php">
                        <i class="fas fa-baby"></i>
                        <p>All Child Details</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_upcoming') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/upcoming-vaccinations.php">
                        <i class="fas fa-calendar-alt"></i>
                        <p>Date of Vaccination</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_reports') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/reports.php">
                        <i class="fas fa-chart-line"></i>
                        <p>Report of Vaccination</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_vaccines') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/vaccines.php">
                        <i class="fas fa-syringe"></i>
                        <p>List of Vaccine</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">BOOKINGS & HOSPITALS</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_requests') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/requests.php">
                        <i class="fas fa-envelope-open-text"></i>
                        <p>Request from Parents</p>
                        <?php if (!empty($pendingRequestsCount) && $pendingRequestsCount > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $pendingRequestsCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_hospital_requests') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/hospital-requests.php">
                        <i class="fas fa-hospital-user"></i>
                        <p>Request from Hospitals</p>
                        <?php if (!empty($pendingHospitalsCount) && $pendingHospitalsCount > 0): ?>
                            <span class="badge bg-danger text-white"><?= $pendingHospitalsCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_hospitals') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/hospitals.php">
                        <i class="fas fa-hospital"></i>
                        <p>List of Hospitals</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'admin_bookings') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/bookings.php">
                        <i class="fas fa-book-medical"></i>
                        <p>Booking Details</p>
                    </a>
                </li>

                <li class="nav-item mt-4">
                    <a href="<?= BASE_URL ?>auth/logout.php" class="text-danger">
                        <i class="fas fa-sign-out-alt text-danger"></i>
                        <p>Logout</p>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- End Sidebar -->
