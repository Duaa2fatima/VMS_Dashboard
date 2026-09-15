<?php
/**
 * Parent Top Navbar Include
 * Child Vaccination Management System (VMS)
 */
?>
<div class="main-header">
    <div class="main-header-logo">
        <div class="logo-header" data-background-color="dark">
            <a href="<?= BASE_URL ?>parent/index.php" class="app-brand">
                <i class="fas fa-baby-carriage text-success"></i>
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
                    <i class="fas fa-baby text-success me-1"></i>
                    <strong>VaxCare</strong> &bull; Parent Health & Vaccination Portal
                </span>
            </div>

            <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

                <!-- Dark / Light Mode Toggle Button -->
                <li class="nav-item me-3">
                    <button type="button" class="theme-toggle-btn shadow-sm" id="themeToggleBtn" onclick="toggleTheme()" title="Switch Dark / Light Mode">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                </li>

                <!-- Quick Book Hospital Shortcut -->
                <li class="nav-item me-2">
                    <a href="<?= BASE_URL ?>parent/book-appointment.php" class="btn btn-xs btn-primary rounded-pill px-3 shadow-sm text-white">
                        <i class="fas fa-calendar-plus me-1"></i> Book Hospital
                    </a>
                </li>

                <!-- Upcoming Vaccination Notification Badge -->
                <li class="nav-item me-3">
                    <a href="<?= BASE_URL ?>parent/schedule.php" class="btn btn-xs btn-outline-warning position-relative" title="Upcoming Vaccinations">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($parentUpcomingCount) && $parentUpcomingCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $parentUpcomingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- User Profile Dropdown -->
                <li class="nav-item topbar-user dropdown hidden-caret">
                    <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="avatar-sm">
                            <span class="avatar-title rounded-circle border border-white bg-success text-white font-weight-bold">
                                <?= strtoupper(substr($currentParent['name'] ?? 'P', 0, 1)) ?>
                            </span>
                        </div>
                        <span class="profile-username">
                            <span class="fw-bold"><?= htmlspecialchars($currentParent['name'] ?? 'Parent') ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box p-3">
                                    <div class="avatar-lg mb-2">
                                        <span class="avatar-title rounded-circle bg-success text-white fw-bold fs-3">
                                            <?= strtoupper(substr($currentParent['name'] ?? 'P', 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="u-text">
                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($currentParent['name'] ?? 'Parent User') ?></h5>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($currentParent['email'] ?? '') ?></p>
                                        <span class="badge bg-success">REGISTERED PARENT</span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?= BASE_URL ?>parent/children.php">
                                    <i class="fas fa-baby me-2 text-primary"></i> Details of Child
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>parent/schedule.php">
                                    <i class="fas fa-calendar-alt me-2 text-warning"></i> Vaccination Dates
                                    <?php if (!empty($parentUpcomingCount) && $parentUpcomingCount > 0): ?>
                                        <span class="badge bg-warning text-dark rounded-pill float-end"><?= $parentUpcomingCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>parent/book-appointment.php">
                                    <i class="fas fa-hospital me-2 text-info"></i> Book Hospital
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>parent/reports.php">
                                    <i class="fas fa-file-medical-alt me-2 text-success"></i> Vaccination Reports
                                </a>
                                <a class="dropdown-item" href="<?= BASE_URL ?>parent/profile.php">
                                    <i class="fas fa-user-cog me-2"></i> Account Settings
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
