<?php
/**
 * Parent Sidebar Navigation
 * Child Vaccination Management System (VMS)
 */
?>
<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>parent/index.php" class="app-brand">
                <i class="fas fa-baby-carriage text-success" style="font-size: 24px;"></i>
                <span>VaxCare</span>
                <span class="badge badge-role ms-1">Parent</span>
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

                <li class="nav-item <?= ($activePage === 'parent_dashboard') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/index.php">
                        <i class="fas fa-home"></i>
                        <p>Parent Dashboard</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">CHILDREN & IMMUNIZATION</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_children') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/children.php">
                        <i class="fas fa-baby"></i>
                        <p>Details of Child</p>
                        <?php if (!empty($parentChildren)): ?>
                            <span class="badge bg-primary"><?= count($parentChildren) ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_schedule') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/schedule.php">
                        <i class="fas fa-calendar-alt"></i>
                        <p>Vaccination Dates</p>
                        <?php if (!empty($parentUpcomingCount) && $parentUpcomingCount > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $parentUpcomingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">APPOINTMENTS & CLINICS</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_book') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/book-appointment.php">
                        <i class="fas fa-hospital-user"></i>
                        <p>Book Hospital</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_bookings') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/bookings.php">
                        <i class="fas fa-calendar-check"></i>
                        <p>My Bookings</p>
                        <?php if (!empty($parentPendingCount) && $parentPendingCount > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $parentPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">RECORDS & REPORTS</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_reports') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/reports.php">
                        <i class="fas fa-file-medical-alt"></i>
                        <p>Report of Vaccination</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'parent_profile') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>parent/profile.php">
                        <i class="fas fa-user-cog"></i>
                        <p>My Profile</p>
                    </a>
                </li>

                <li class="nav-item mt-4">
                    <a href="<?= BASE_URL ?>auth/logout.php" class="text-danger">
                        <i class="fas fa-sign-out-alt text-danger"></i>
                        <p>Sign Out</p>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- End Sidebar -->
