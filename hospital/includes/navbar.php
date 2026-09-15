<?php
/**
 * Hospital Top Navbar Include
 * Child Vaccination Management System (VMS)
 */
?>
<div class="main-header">
    <div class="main-header-logo">
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>hospital/index.php" class="app-brand">
                <i class="fas fa-hospital text-info"></i>
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
    </div>

    <!-- Navbar Header -->
    <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
        <div class="container-fluid">
            
            <div class="d-none d-md-flex align-items-center">
                <span class="text-muted small">
                    <i class="fas fa-hospital-alt text-info me-1"></i>
                    <strong>VaxCare</strong> &bull; Healthcare Facility Clinical Portal
                </span>
            </div>

            <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

                <!-- Dark / Light Mode Toggle Button -->
                <li class="nav-item me-3">
                    <button type="button" class="theme-toggle-btn shadow-sm" id="themeToggleBtn" onclick="toggleTheme()" title="Switch Dark / Light Mode">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                </li>

                <!-- Facility Status Badge -->
                <li class="nav-item me-3 d-none d-sm-block">
                    <span class="badge bg-success rounded-pill px-3 py-1">
                        <i class="fas fa-check-circle me-1"></i> Accredited Center
                    </span>
                </li>

                <!-- Today's Appointments Shortcut -->
                <li class="nav-item me-3">
                    <a href="<?= BASE_URL ?>hospital/appointments.php?filter=Today" class="btn btn-xs btn-outline-primary position-relative">
                        <i class="fas fa-calendar-day me-1"></i> Today's Schedule
                        <?php if (!empty($todayAppointmentsCount) && $todayAppointmentsCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $todayAppointmentsCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- User Profile Dropdown -->
                <li class="nav-item topbar-user dropdown hidden-caret">
                    <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="avatar-sm">
                            <span class="avatar-title rounded-circle border border-white bg-info text-white font-weight-bold">
                                <?= strtoupper(substr($currentHospital['hospital_name'] ?? 'H', 0, 1)) ?>
                            </span>
                        </div>
                        <span class="profile-username">
                            <span class="fw-bold"><?= htmlspecialchars($currentHospital['hospital_name'] ?? 'Hospital') ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box p-3">
                                    <div class="avatar-lg mb-2">
                                        <span class="avatar-title rounded-circle bg-info text-white fw-bold fs-3">
                                            <?= strtoupper(substr($currentHospital['hospital_name'] ?? 'H', 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="u-text">
                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($currentHospital['hospital_name'] ?? 'Facility') ?></h5>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($currentHospital['email'] ?? '') ?></p>
                                        <span class="badge bg-info">HEALTHCARE FACILITY</span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?= BASE_URL ?>hospital/appointments.php">
                                    <i class="fas fa-calendar-check me-2 text-primary"></i> Patient Appointments
                                    <?php if (!empty($todayAppointmentsCount) && $todayAppointmentsCount > 0): ?>
                                        <span class="badge bg-danger rounded-pill float-end"><?= $todayAppointmentsCount ?> Today</span>
                                    <?php endif; ?>
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>hospital/records.php">
                                    <i class="fas fa-file-medical me-2 text-success"></i> Vaccination Records
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>hospital/vaccines.php">
                                    <i class="fas fa-syringe me-2 text-warning"></i> Vaccine Inventory
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>hospital/profile.php">
                                    <i class="fas fa-clinic-medical me-2 text-info"></i> Facility Profile & Location
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                                </a>
                            </li>
                        </div>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <!-- End Navbar -->
</div>
