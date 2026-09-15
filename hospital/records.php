<?php
/**
 * Vaccination Records - Hospital Administration Log & History
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Vaccination Records';
$activePage = 'hospital_records';

require_once __DIR__ . '/includes/header.php';

$hospitalId = $currentHospital['id'];

// Filters
$filterVaccineId = (int)($_GET['vaccine_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

// Fetch vaccines catalog for filter dropdown
$vaccines = [];
if ($pdo) {
    try {
        $vaccines = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Fetch Vaccination Records
$records = [];
$stats = ['total' => 0, 'vaccinated' => 0, 'not_vaccinated' => 0];

if ($pdo) {
    try {
        $query = "
            SELECT vr.*, c.child_name, c.gender, c.date_of_birth, c.address as child_address,
                   v.vaccine_name, v.age_group,
                   p.name as parent_name, p.phone as parent_phone
            FROM vaccination_records vr
            JOIN bookings b ON vr.booking_id = b.booking_id
            JOIN children c ON vr.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE b.hospital_id = ?
        ";
        $params = [$hospitalId];

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

        // Statistics
        $stats['total'] = count($records);
        foreach ($records as $r) {
            if ($r['status'] === 'Vaccinated') {
                $stats['vaccinated']++;
            } elseif ($r['status'] === 'Not Vaccinated') {
                $stats['not_vaccinated']++;
            }
        }

    } catch (Exception $e) {
        setFlash('error', 'Error loading clinical records: ' . $e->getMessage());
    }
}

// Handle Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Facility_Vaccination_Records_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Record ID', 'Child Name', 'Gender', 'DOB', 'Parent Name', 'Parent Phone', 'Vaccine', 'Age Group', 'Administration Date', 'Status', 'Clinical Remarks']);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['record_id'],
            $r['child_name'],
            $r['gender'],
            $r['date_of_birth'],
            $r['parent_name'],
            $r['parent_phone'],
            $r['vaccine_name'],
            $r['age_group'],
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
                    <h3 class="fw-bold mb-1">Facility Vaccination Records</h3>
                    <h6 class="op-7 mb-0 text-muted">Complete log of all administered and deferred vaccinations performed at <?= htmlspecialchars($currentHospital['hospital_name']) ?></h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2">
                    <?php
                        $exportParams = $_GET;
                        $exportParams['export'] = 'csv';
                        $exportUrl = 'records.php?' . http_build_query($exportParams);
                    ?>
                    <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-round">
                        <i class="fas fa-file-csv me-1"></i> Export Records (CSV)
                    </a>
                    <button type="button" class="btn btn-primary btn-round shadow-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Log Sheet
                    </button>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Filter Controls Form -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="records.php" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Filter by Vaccine:</label>
                            <select name="vaccine_id" class="form-select form-select-sm">
                                <option value="0">All Vaccines</option>
                                <?php foreach ($vaccines as $v): ?>
                                    <option value="<?= $v['vaccine_id'] ?>" <?= ($filterVaccineId == $v['vaccine_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['vaccine_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
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
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-filter"></i>
                            </button>
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
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Vaccinated Successfully</p>
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
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Deferred / Not Vaccinated</p>
                                        <h4 class="card-title text-danger"><?= $stats['not_vaccinated'] ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Master Records Table -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-notes-medical text-primary me-2"></i> Clinical Administration Log
                    </div>
                    <span class="badge bg-primary"><?= count($records) ?> Entries</span>
                </div>
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-file-medical-alt fa-3x mb-3 text-muted"></i>
                            <h5 class="fw-bold">No Records Found</h5>
                            <p class="small text-muted mb-0">No entries match your search filter or no patients have been vaccinated yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable-custom">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date Administered</th>
                                        <th>Child Patient</th>
                                        <th>Parent Contact</th>
                                        <th>Vaccine</th>
                                        <th>Status</th>
                                        <th>Clinical Remarks & Batch Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $r): ?>
                                        <tr>
                                            <td class="fw-bold text-primary">
                                                <i class="fas fa-calendar-check text-success me-1"></i>
                                                <?= formatDate($r['vaccination_date'], 'd M Y') ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($r['child_name']) ?></strong>
                                                <div class="small text-muted"><?= calculateAge($r['date_of_birth']) ?> &bull; <?= htmlspecialchars($r['gender']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($r['parent_name']) ?></div>
                                                <small class="text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($r['parent_phone'] ?? '-') ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($r['vaccine_name']) ?></div>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($r['age_group']) ?></span>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($r['status']) ?>
                                            </td>
                                            <td>
                                                <div class="small text-muted fst-italic" style="max-width: 260px;">
                                                    <?= !empty($r['remarks']) ? htmlspecialchars($r['remarks']) : 'Vaccine administered as scheduled.' ?>
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
            .sidebar, .main-header, .btn, form, .footer, .nav-pills {
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
