<?php
/**
 * Report of Vaccination Taken - Infant Immunization Certificates & Reports
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Report of Vaccination';
$activePage = 'parent_reports';

require_once __DIR__ . '/includes/header.php';

$parentId = $currentParent['id'];

// Filter inputs
$filterChildId = (int)($_GET['child_id'] ?? 0);
$filterVaccineId = (int)($_GET['vaccine_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

// Fetch parent's children & vaccines catalog for filter dropdowns
$children = [];
$vaccines = [];

if ($pdo) {
    try {
        $stmtCh = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? ORDER BY child_name ASC");
        $stmtCh->execute([$parentId]);
        $children = $stmtCh->fetchAll();

        $vaccines = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Build query for vaccination records
$records = [];
$stats = ['total' => 0, 'vaccinated' => 0, 'not_vaccinated' => 0];

if ($pdo) {
    try {
        $query = "
            SELECT vr.*, c.child_name, c.gender, c.date_of_birth, c.address as child_address,
                   v.vaccine_name, v.age_group, v.description as vaccine_desc,
                   h.hospital_name, h.location as hospital_location, h.phone as hospital_phone
            FROM vaccination_records vr
            JOIN children c ON vr.child_id = c.child_id
            JOIN bookings b ON vr.booking_id = b.booking_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE c.parent_id = ?
        ";
        $params = [$parentId];

        if ($filterChildId > 0) {
            $query .= " AND vr.child_id = ?";
            $params[] = $filterChildId;
        }

        if ($filterVaccineId > 0) {
            $query .= " AND b.vaccine_id = ?";
            $params[] = $filterVaccineId;
        }

        if (!empty($filterStatus) && in_array($filterStatus, ['Vaccinated', 'Not Vaccinated'])) {
            $query .= " AND vr.status = ?";
            $params[] = $filterStatus;
        }

        if (!empty($dateFrom)) {
            $query .= " AND vr.vaccination_date >= ?";
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $query .= " AND vr.vaccination_date <= ?";
            $params[] = $dateTo;
        }

        $query .= " ORDER BY vr.vaccination_date DESC, vr.record_id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // Calculate statistics
        $stats['total'] = count($records);
        foreach ($records as $rec) {
            if ($rec['status'] === 'Vaccinated') {
                $stats['vaccinated']++;
            } elseif ($rec['status'] === 'Not Vaccinated') {
                $stats['not_vaccinated']++;
            }
        }

    } catch (Exception $e) {
        setFlash('error', 'Error generating report: ' . $e->getMessage());
    }
}

// Handle Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Vaccination_Report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Record ID', 'Child Name', 'Gender', 'DOB', 'Vaccine', 'Age Group', 'Hospital Facility', 'Location', 'Vaccination Date', 'Status', 'Clinical Remarks']);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['record_id'],
            $r['child_name'],
            $r['gender'],
            $r['date_of_birth'],
            $r['vaccine_name'],
            $r['age_group'],
            $r['hospital_name'],
            $r['hospital_location'],
            $r['vaccination_date'],
            $r['status'],
            $r['remarks']
        ]);
    }
    fclose($output);
    exit;
}
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Report of Vaccination Taken</h3>
                    <h6 class="op-7 mb-0 text-muted">Official clinical immunization records, completion status, and infant health reports</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2">
                    <?php
                        $exportParams = $_GET;
                        $exportParams['export'] = 'csv';
                        $exportUrl = 'reports.php?' . http_build_query($exportParams);
                    ?>
                    <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-round">
                        <i class="fas fa-file-csv me-1"></i> Export to CSV
                    </a>
                    <button type="button" class="btn btn-primary btn-round shadow-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Immunization Report
                    </button>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Filter Controls Form -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="reports.php" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Select Infant / Child:</label>
                            <select name="child_id" class="form-select form-select-sm">
                                <option value="0">All Children</option>
                                <?php foreach ($children as $c): ?>
                                    <option value="<?= $c['child_id'] ?>" <?= ($filterChildId == $c['child_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['child_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Vaccine Name:</label>
                            <select name="vaccine_id" class="form-select form-select-sm">
                                <option value="0">All Vaccines</option>
                                <?php foreach ($vaccines as $v): ?>
                                    <option value="<?= $v['vaccine_id'] ?>" <?= ($filterVaccineId == $v['vaccine_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['vaccine_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Status:</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="Vaccinated" <?= ($filterStatus === 'Vaccinated') ? 'selected' : '' ?>>Vaccinated</option>
                                <option value="Not Vaccinated" <?= ($filterStatus === 'Not Vaccinated') ? 'selected' : '' ?>>Not Vaccinated</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">From Date:</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">To Date:</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-12 text-end mt-2">
                            <button type="submit" class="btn btn-sm btn-primary px-3">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary KPI Breakdown -->
            <div class="row mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round mb-3 mb-md-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Total Clinical Logs</p>
                                        <h4 class="card-title"><?= $stats['total'] ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round mb-3 mb-md-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-success bubble-shadow-small">
                                        <i class="fas fa-shield-virus"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Successfully Vaccinated</p>
                                        <h4 class="card-title text-success"><?= $stats['vaccinated'] ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Not Vaccinated / Deferred</p>
                                        <h4 class="card-title text-danger"><?= $stats['not_vaccinated'] ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Printable Immunization Report Card -->
            <div class="card card-round shadow-sm" id="printableReportSection">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-certificate text-success me-2"></i> Infant Vaccination History Records
                        </h5>
                        <small class="text-muted">Parent Account: <strong><?= htmlspecialchars($currentParent['name']) ?></strong></small>
                    </div>
                    <span class="badge bg-success"><?= count($records) ?> Records Found</span>
                </div>
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-file-medical-alt fa-3x mb-3 text-muted"></i>
                            <h5 class="fw-bold">No Vaccination Records Found</h5>
                            <p class="small text-muted mb-0">No immunization entries match your filter criteria or no vaccines have been administered yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable-custom">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date Administered</th>
                                        <th>Child Details</th>
                                        <th>Vaccine Administered</th>
                                        <th>Healthcare Facility</th>
                                        <th>Status</th>
                                        <th>Medical Remarks & Batch #</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $r): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <i class="fas fa-calendar-check text-success me-1"></i>
                                                <?= formatDate($r['vaccination_date'], 'd M Y') ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($r['child_name']) ?></strong>
                                                <div class="small text-muted"><?= calculateAge($r['date_of_birth']) ?> &bull; <?= htmlspecialchars($r['gender']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($r['vaccine_name']) ?></div>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($r['age_group']) ?></span>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-hospital text-muted me-1"></i> <strong><?= htmlspecialchars($r['hospital_name']) ?></strong></div>
                                                <div class="small text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($r['hospital_location'] ?? '-') ?></div>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($r['status']) ?>
                                            </td>
                                            <td>
                                                <div class="small text-muted fst-italic" style="max-width: 250px;">
                                                    <?= !empty($r['remarks']) ? htmlspecialchars($r['remarks']) : 'Vaccine successfully administered.' ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Print Media Styling -->
    <style>
        @media print {
            .sidebar, .main-header, .btn, .card-header button, form, .footer, .nav-pills {
                display: none !important;
            }
            .main-panel {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .page-inner {
                padding: 0 !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
