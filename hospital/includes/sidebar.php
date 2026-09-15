<?php
/**
 * Hospital Sidebar Navigation
 * Child Vaccination Management System (VMS)
 */
?>
<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>hospital/index.php" class="app-brand">
                <i class="fas fa-hospital text-info" style="font-size: 24px;"></i>
                <span>VaxCare</span>
                <span class="badge badge-role ms-1">Hospital</span>
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

                <li class="nav-item <?= ($activePage === 'hospital_dashboard') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>hospital/index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <p>Hospital Dashboard</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">APPOINTMENTS & VACCINATION</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'hospital_appointments') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>hospital/appointments.php">
                        <i class="fas fa-calendar-check"></i>
                        <p>Patient Appointments</p>
                        <?php if (!empty($pendingAppointmentsCount) && $pendingAppointmentsCount > 0): ?>
                            <span class="badge bg-warning text-dark rounded-pill"><?= $pendingAppointmentsCount ?> New</span>
                        <?php elseif (!empty($todayAppointmentsCount) && $todayAppointmentsCount > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $todayAppointmentsCount ?> Today</span>
                        <?php elseif (!empty($approvedAppointmentsCount) && $approvedAppointmentsCount > 0): ?>
                            <span class="badge bg-info text-white"><?= $approvedAppointmentsCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'hospital_records') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>hospital/records.php">
                        <i class="fas fa-file-medical"></i>
                        <p>Vaccination Records</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
                    <h4 class="text-section">FACILITY & INVENTORY</h4>
                </li>

                <li class="nav-item <?= ($activePage === 'hospital_vaccines') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>hospital/vaccines.php">
                        <i class="fas fa-syringe"></i>
                        <p>Available Vaccines</p>
                    </a>
                </li>

                <li class="nav-item <?= ($activePage === 'hospital_profile') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>hospital/profile.php">
                        <i class="fas fa-clinic-medical"></i>
                        <p>Facility Profile</p>
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
