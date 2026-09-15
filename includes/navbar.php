<?php
/**
 * Admin Top Navbar with Dark / Light Mode Toggle
 * Child Vaccination Management System (VMS)
 */
?>
<div class="main-header">
    <div class="main-header-logo">
        <!-- Logo Header for mobile -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>admin/index.php" class="app-brand">
                <i class="fas fa-shield-virus text-primary"></i>
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
                    <i class="fas fa-user-shield text-primary me-1"></i>
                    <strong>Vaccination Management System</strong> &bull; Administrative Portal
                </span>
            </div>

            <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

                <!-- Dark / Light Mode Toggle Button -->
                <li class="nav-item me-3">
                    <button type="button" class="theme-toggle-btn shadow-sm" id="themeToggleBtn" onclick="toggleTheme()" title="Switch Dark / Light Mode">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                </li>



                <!-- Quick Parent Requests Shortcut -->
                <li class="nav-item me-3">
                    <a href="<?= BASE_URL ?>admin/requests.php" class="btn btn-xs btn-outline-warning text-dark position-relative">
                        <i class="fas fa-baby me-1"></i> Parent Requests
                        <?php if (!empty($pendingRequestsCount) && $pendingRequestsCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $pendingRequestsCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- User Profile Dropdown -->
                <li class="nav-item topbar-user dropdown hidden-caret">
                    <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="avatar-sm">
                            <span class="avatar-title rounded-circle border border-white bg-primary text-white font-weight-bold">
                                <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                            </span>
                        </div>
                        <span class="profile-username">
                            <span class="fw-bold"><?= htmlspecialchars($currentAdmin['name'] ?? 'Admin') ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box p-3">
                                    <div class="avatar-lg mb-2">
                                        <span class="avatar-title rounded-circle bg-primary text-white fw-bold fs-3">
                                            <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="u-text">
                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($currentAdmin['name'] ?? 'Administrator') ?></h5>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($currentAdmin['email'] ?? 'admin@vaccination.gov') ?></p>
                                        <span class="badge bg-primary">ADMINISTRATOR</span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/hospital-requests.php">
                                    <i class="fas fa-hospital-user me-2 text-danger"></i> Request from Hospitals
                                    <?php if (!empty($pendingHospitalsCount) && $pendingHospitalsCount > 0): ?>
                                        <span class="badge bg-danger rounded-pill float-end"><?= $pendingHospitalsCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/requests.php">
                                    <i class="fas fa-tasks me-2"></i> Pending Parent Requests
                                    <?php if (!empty($pendingRequestsCount) && $pendingRequestsCount > 0): ?>
                                        <span class="badge bg-warning text-dark rounded-pill float-end"><?= $pendingRequestsCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/reports.php"><i class="fas fa-chart-line me-2"></i> Vaccination Reports</a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/hospitals.php"><i class="fas fa-hospital me-2"></i> Manage Hospitals</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
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
