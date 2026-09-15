<?php
/**
 * Report of Vaccination - Date Wise & Filtered Reports
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Enforce admin login
requireAdmin();

// Filter parameters
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date'] ?? '';
$childId    = (int)($_GET['child_id'] ?? 0);
$vaccineId  = (int)($_GET['vaccine_id'] ?? 0);
$hospitalId = (int)($_GET['hospital_id'] ?? 0);
$status     = $_GET['status'] ?? '';
$exportCsv  = isset($_GET['export']) && $_GET['export'] === 'csv';

$reportRows = [];
$childrenList = [];
$vaccinesList = [];
$hospitalsList = [];

if ($pdo) {
    try {
        $childrenList = $pdo->query("SELECT child_id, child_name FROM children ORDER BY child_name ASC")->fetchAll();
        $vaccinesList = $pdo->query("SELECT vaccine_id, vaccine_name, age_group FROM vaccines ORDER BY vaccine_name ASC")->fetchAll();
        $hospitalsList = $pdo->query("SELECT hospital_id, hospital_name FROM hospitals ORDER BY hospital_name ASC")->fetchAll();

        // Build SQL Query
        $sql = "SELECT b.*, c.child_name, c.date_of_birth, c.gender,
                       p.name as parent_name, p.phone as parent_phone, p.email as parent_email,
                       v.vaccine_name, v.age_group,
                       h.hospital_name, h.location as hospital_location,
                       vr.record_id, vr.vaccination_date, vr.status as record_status, vr.remarks as record_remarks,
                       adm.name as admin_name
                FROM bookings b
                JOIN children c ON b.child_id = c.child_id
                JOIN parents p ON c.parent_id = p.parent_id
                JOIN vaccines v ON b.vaccine_id = v.vaccine_id
                JOIN hospitals h ON b.hospital_id = h.hospital_id
                LEFT JOIN vaccination_records vr ON b.booking_id = vr.booking_id
                LEFT JOIN admins adm ON b.admin_id = adm.admin_id
                WHERE 1=1";
        $params = [];

        if (!empty($startDate)) {
            $sql .= " AND b.appointment_date >= ?";
            $params[] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND b.appointment_date <= ?";
            $params[] = $endDate;
        }
        if ($childId > 0) {
            $sql .= " AND b.child_id = ?";
            $params[] = $childId;
        }
        if ($vaccineId > 0) {
            $sql .= " AND b.vaccine_id = ?";
            $params[] = $vaccineId;
        }
        if ($hospitalId > 0) {
            $sql .= " AND b.hospital_id = ?";
            $params[] = $hospitalId;
        }
        if (!empty($status)) {
            if ($status === 'Vaccinated' || $status === 'Not Vaccinated') {
                $sql .= " AND vr.status = ?";
                $params[] = $status;
            } else {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }
        }

        $sql .= " ORDER BY b.appointment_date DESC, b.booking_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportRows = $stmt->fetchAll();

        // Handle CSV Export
        if ($exportCsv) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=vaccination_report_' . date('Ymd_His') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');
            $output = fopen('php://output', 'w');
            
            // CSV Header row
            fputcsv($output, ['Booking ID', 'Appointment Date', 'Child Name', 'Date of Birth', 'Gender', 'Parent Name', 'Parent Phone', 'Vaccine Name', 'Age Group', 'Hospital Name', 'Location', 'Booking Status', 'Vaccination Date', 'Vaccination Record Status', 'Remarks']);

            foreach ($reportRows as $row) {
                fputcsv($output, [
                    'BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT),
                    $row['appointment_date'],
                    $row['child_name'],
                    $row['date_of_birth'],
                    $row['gender'],
                    $row['parent_name'],
                    $row['parent_phone'],
                    $row['vaccine_name'],
                    $row['age_group'],
                    $row['hospital_name'],
                    $row['hospital_location'],
                    $row['status'],
                    $row['vaccination_date'] ?? 'N/A',
                    $row['record_status'] ?? $row['status'],
                    $row['record_remarks'] ?? '-'
                ]);
            }
            fclose($output);
            exit;
        }

    } catch (Exception $e) {
        setFlash('error', 'Error generating report: ' . $e->getMessage());
    }
}

// Calculate summary counts
$cntVaccinated = 0;
$cntPending = 0;
$cntApproved = 0;
$cntRejected = 0;
foreach ($reportRows as $r) {
    if (($r['record_status'] ?? '') === 'Vaccinated' || $r['status'] === 'Completed') $cntVaccinated++;
    elseif ($r['status'] === 'Pending') $cntPending++;
    elseif ($r['status'] === 'Approved') $cntApproved++;
    elseif ($r['status'] === 'Rejected') $cntRejected++;
}

$pageTitle = 'Report of Vaccination';
$activePage = 'admin_reports';

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-file-medical-alt text-primary me-2"></i> Report of Vaccination</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Child & Vaccination Date Wise Reports</li>
                    </ul>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-round">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                    <?php 
                        $queryString = http_build_query(array_merge($_GET, ['export' => 'csv']));
                    ?>
                    <a href="reports.php?<?= $queryString ?>" class="btn btn-success btn-round">
                        <i class="fas fa-file-csv me-1"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Filter Parameters Card -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-header bg-light">
                    <div class="card-title text-dark"><i class="fas fa-filter text-primary me-2"></i> Date Wise & Category Filters</div>
                </div>
                <div class="card-body">
                    <form method="GET" action="reports.php">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">From Date</label>
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">To Date</label>
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Select Child</label>
                                <select name="child_id" class="form-select form-select-sm">
                                    <option value="0">All Children</option>
                                    <?php foreach ($childrenList as $cl): ?>
                                        <option value="<?= $cl['child_id'] ?>" <?= $childId == $cl['child_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cl['child_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Select Vaccine</label>
                                <select name="vaccine_id" class="form-select form-select-sm">
                                    <option value="0">All Vaccines</option>
                                    <?php foreach ($vaccinesList as $vl): ?>
                                        <option value="<?= $vl['vaccine_id'] ?>" <?= $vaccineId == $vl['vaccine_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vl['vaccine_name']) ?> (<?= htmlspecialchars($vl['age_group']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Hospital Facility</label>
                                <select name="hospital_id" class="form-select form-select-sm">
                                    <option value="0">All Hospitals</option>
                                    <?php foreach ($hospitalsList as $hl): ?>
                                        <option value="<?= $hl['hospital_id'] ?>" <?= $hospitalId == $hl['hospital_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($hl['hospital_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Booking / Vaccination Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="Vaccinated" <?= $status === 'Vaccinated' ? 'selected' : '' ?>>Vaccinated (Administered)</option>
                                    <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved / Scheduled</option>
                                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
                                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="fas fa-search me-1"></i> Apply Filters
                                </button>
                                <a href="reports.php" class="btn btn-light border btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Stats of Filtered Results -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card p-3 border-start border-primary border-4 text-center shadow-sm">
                        <small class="text-muted fw-bold">Matching Records</small>
                        <h3 class="fw-bold mb-0 text-primary"><?= count($reportRows) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-start border-success border-4 text-center shadow-sm">
                        <small class="text-muted fw-bold">Vaccinated</small>
                        <h3 class="fw-bold mb-0 text-success"><?= $cntVaccinated ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-start border-info border-4 text-center shadow-sm">
                        <small class="text-muted fw-bold">Approved / Scheduled</small>
                        <h3 class="fw-bold mb-0 text-info"><?= $cntApproved ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-start border-warning border-4 text-center shadow-sm">
                        <small class="text-muted fw-bold">Pending Review</small>
                        <h3 class="fw-bold mb-0 text-warning"><?= $cntPending ?></h3>
                    </div>
                </div>
            </div>

            <!-- Report Results Table -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light">
                    <div class="card-title text-dark">Generated Date-Wise Vaccination Report</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Appt Date</th>
                                    <th>Child Profile</th>
                                    <th>Vaccine & Age Group</th>
                                    <th>Hospital Facility</th>
                                    <th>Vaccination Date</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reportRows)): ?>
                                    <?php foreach ($reportRows as $row): ?>
                                        <tr>
                                            <td class="fw-bold text-primary font-monospace">
                                                #BK-<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= formatDate($row['appointment_date']) ?></div>
                                                <small class="text-muted">Booked: <?= formatDate($row['booking_date']) ?></small>
                                            </td>
                                            <td>
                                                <a href="child-details.php?id=<?= $row['child_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($row['child_name']) ?>
                                                </a>
                                                <div class="small text-muted">
                                                    Parent: <?= htmlspecialchars($row['parent_name']) ?> (<?= htmlspecialchars($row['parent_phone'] ?: 'N/A') ?>)
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($row['vaccine_name']) ?></span>
                                                <div class="small text-muted"><?= htmlspecialchars($row['age_group']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($row['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['hospital_location'] ?: '') ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['vaccination_date'])): ?>
                                                    <div class="fw-bold text-success"><?= formatDate($row['vaccination_date']) ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['record_status'])): ?>
                                                    <?= getStatusBadge($row['record_status']) ?>
                                                <?php else: ?>
                                                    <?= getStatusBadge($row['status']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($row['record_remarks'] ?: ($row['status'] === 'Approved' ? 'Approved by ' . ($row['admin_name'] ?: 'Admin') : '-')) ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
