<?php
/**
 * Admin Dashboard
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Admin Dashboard';
$activePage = 'admin_dashboard';

require_once __DIR__ . '/../includes/header.php';

// Fetch Metrics from child_vaccination tables
$totalChildren = 0;
$totalParents = 0;
$totalHospitals = 0;
$totalVaccines = 0;
$pendingRequests = 0;
$completedVaccinations = 0;
$recentBookings = [];
$recentChildren = [];

if ($pdo) {
    try {
        $totalChildren = (int)$pdo->query("SELECT COUNT(*) FROM children")->fetchColumn();
        $totalParents  = (int)$pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
        $totalHospitals = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Active'")->fetchColumn();
        $totalVaccines = (int)$pdo->query("SELECT COUNT(*) FROM vaccines WHERE stock_status = 'Available'")->fetchColumn();
        $pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn();
        $pendingHospitals = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Pending'")->fetchColumn();
        $completedVaccinations = (int)$pdo->query("SELECT COUNT(*) FROM vaccination_records WHERE status = 'Vaccinated'")->fetchColumn();

        // Recent Pending Requests from Parents
        $stmtReq = $pdo->query("SELECT b.*, c.child_name, c.date_of_birth, v.vaccine_name, v.age_group, h.hospital_name, p.name as parent_name, p.phone as parent_phone
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE b.status = 'Pending'
            ORDER BY b.booking_date DESC, b.booking_id DESC LIMIT 5");
        $recentBookings = $stmtReq->fetchAll();

        // Recent Registered Children
        $stmtChild = $pdo->query("SELECT c.*, p.name as parent_name, p.phone as parent_phone 
            FROM children c 
            JOIN parents p ON c.parent_id = p.parent_id 
            ORDER BY c.child_id DESC LIMIT 5");
        $recentChildren = $stmtChild->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading statistics: ' . $e->getMessage());
    }
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">
            
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Administrative Overview</h3>
                    <h6 class="op-7 mb-2 text-muted">Vaccination monitoring, parent request approvals, hospital directory & reports</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2 flex-wrap">
                    <?php if (!empty($pendingHospitals) && $pendingHospitals > 0): ?>
                        <a href="hospitals.php?filter=pending" class="btn btn-danger btn-round btn-header-action shadow-sm">
                            <i class="fas fa-hospital me-1"></i> Hospital Requests (<?= $pendingHospitals ?>)
                        </a>
                    <?php endif; ?>
                    <a href="requests.php" class="btn btn-warning btn-round btn-header-action">
                        <i class="fas fa-bell me-1"></i> Parent Requests (<?= $pendingRequests ?>)
                    </a>
                    <a href="reports.php" class="btn btn-primary btn-round btn-header-action">
                        <i class="fas fa-file-alt me-1"></i> Vaccination Reports
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <?php if (!empty($pendingHospitals) && $pendingHospitals > 0): ?>
                <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-3 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-hospital-alt fs-3 me-3 text-danger"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Pending Hospital Registration Requests</h6>
                            <span class="small text-muted">There are <strong><?= $pendingHospitals ?></strong> healthcare facility registration request(s) awaiting your approval.</span>
                        </div>
                    </div>
                    <a href="hospitals.php?filter=pending" class="btn btn-sm btn-danger rounded-pill fw-bold px-3">
                        <i class="fas fa-check-circle me-1"></i> Review Requests &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <!-- Metric Cards -->
            <div class="row">
                <!-- Total Children -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='children.php'" title="Click to view Children details">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                                        <i class="fas fa-baby"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Children</p>
                                        <h4 class="card-title"><?= $totalChildren ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Parents -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='parents.php'" title="Click to view Parents details">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-info bubble-shadow-small">
                                        <i class="fas fa-user-friends"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Parents</p>
                                        <h4 class="card-title"><?= $totalParents ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Hospitals -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='hospitals.php'" title="Click to view Hospitals directory">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-success bubble-shadow-small">
                                        <i class="fas fa-hospital"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Hospitals</p>
                                        <h4 class="card-title"><?= $totalHospitals ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Vaccines -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='vaccines.php'" title="Click to view Vaccines catalog">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-secondary bubble-shadow-small">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Vaccines</p>
                                        <h4 class="card-title"><?= $totalVaccines ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='requests.php'" title="Click to review Pending Requests">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-warning bubble-shadow-small">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Pending Req</p>
                                        <h4 class="card-title"><?= $pendingRequests ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vaccinated Count -->
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='reports.php'" title="Click to view Vaccination Reports">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Vaccinated</p>
                                        <h4 class="card-title"><?= $completedVaccinations ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Approvals Queue & Recent Children -->
            <div class="row">
                <!-- Pending Approvals Queue -->
                <div class="col-lg-8">
                    <div class="card card-round shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <div class="card-title text-dark">
                                <i class="fas fa-envelope-open-text text-warning me-2"></i> Pending Parent Appointment Requests
                            </div>
                            <a href="requests.php" class="btn btn-sm btn-outline-primary">View All (<?= $pendingRequests ?>)</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Child Profile</th>
                                            <th>Vaccine</th>
                                            <th>Target Hospital</th>
                                            <th>Appt Date</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentBookings)): ?>
                                            <?php foreach ($recentBookings as $b): ?>
                                                <tr>
                                                    <td class="fw-bold text-primary font-monospace">#BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($b['child_name']) ?></strong>
                                                        <div class="small text-muted">Parent: <?= htmlspecialchars($b['parent_name']) ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($b['vaccine_name']) ?></span>
                                                        <div class="small text-muted"><?= htmlspecialchars($b['age_group']) ?></div>
                                                    </td>
                                                    <td><?= htmlspecialchars($b['hospital_name']) ?></td>
                                                    <td>
                                                        <div class="fw-bold text-dark"><?= formatDate($b['appointment_date']) ?></div>
                                                        <small class="text-muted">Booked: <?= formatDate($b['booking_date']) ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="requests.php" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-check-double me-1"></i> Review
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-check-circle text-success me-1"></i> All parent appointment requests have been reviewed!
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Registered Children -->
                <div class="col-lg-4">
                    <div class="card card-round shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <div class="card-title text-dark">
                                <i class="fas fa-baby text-primary me-2"></i> Recent Children
                            </div>
                            <a href="children.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentChildren)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentChildren as $c): ?>
                                        <li class="list-group-item recent-child-item d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['child_name']) ?></div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($c['gender']) ?> &bull; Age: <?= calculateAge($c['date_of_birth']) ?>
                                                </small>
                                            </div>
                                            <a href="child-details.php?id=<?= $c['child_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                Profile
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted text-center py-3">No children registered yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Navigation Shortcuts to All Required Modules -->
            <div class="row mt-2">
                <div class="col-md-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card" onclick="location.href='upcoming-vaccinations.php'">
                        <i class="fas fa-calendar-alt fa-2x text-primary mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold">Date of Vaccination</h6>
                        <p class="text-muted small mb-2">Upcoming vaccination dates of all children</p>
                        <a href="upcoming-vaccinations.php" class="btn btn-sm btn-light border btn-shortcut">Open Schedule</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card" onclick="location.href='vaccines.php'">
                        <i class="fas fa-syringe fa-2x text-success mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold">List of Vaccine</h6>
                        <p class="text-muted small mb-2">View catalog & toggle availability</p>
                        <a href="vaccines.php" class="btn btn-sm btn-light border btn-shortcut">View Vaccines</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card" onclick="location.href='hospitals.php'">
                        <i class="fas fa-hospital fa-2x text-info mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold">Hospitals Directory</h6>
                        <p class="text-muted small mb-2">Add, update or remove partner hospitals</p>
                        <a href="hospitals.php" class="btn btn-sm btn-light border btn-shortcut">Manage Hospitals</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card" onclick="location.href='reports.php'">
                        <i class="fas fa-file-medical-alt fa-2x text-danger mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold">Report of Vaccination</h6>
                        <p class="text-muted small mb-2">Filter date-wise vaccination reports</p>
                        <a href="reports.php" class="btn btn-sm btn-light border btn-shortcut">View Reports</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
