<?php
/**
 * Hospital Vaccine Availability & Stock Directory - Admin View
 * Child Vaccination Management System (VMS)
 * 
 * Note: Admin views vaccine availability across all accredited hospitals.
 * Adding vaccines by admin is disabled; hospitals manage facility-level availability.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pageTitle = 'Vaccines Availability by Hospital';
$activePage = 'admin_vaccines';

// Filter Parameters
$selectedHospitalId = (int)($_GET['hospital_id'] ?? 0);
$selectedStatus     = $_GET['status'] ?? 'all'; // 'all', 'Available', 'Unavailable'

$hospitals = [];
$vaccineStockList = [];
$totalCatalogVaccines = 0;
$totalActiveHospitals = 0;
$availableStockCount = 0;
$unavailableStockCount = 0;

if ($pdo) {
    try {
        // Fetch active hospitals for the filter dropdown
        $hospitals = $pdo->query("SELECT hospital_id, hospital_name, location FROM hospitals WHERE status = 'Active' ORDER BY hospital_name ASC")->fetchAll();
        $totalActiveHospitals = count($hospitals);
        $totalCatalogVaccines = (int)$pdo->query("SELECT COUNT(*) FROM vaccines")->fetchColumn();

        // Build filter conditions
        $whereClauses = ["h.status = 'Active'"];
        $params = [];

        if ($selectedHospitalId > 0) {
            $whereClauses[] = "h.hospital_id = ?";
            $params[] = $selectedHospitalId;
        }

        if ($selectedStatus === 'Available') {
            $whereClauses[] = "COALESCE(hv.stock_status, 'Available') = 'Available'";
        } elseif ($selectedStatus === 'Unavailable') {
            $whereClauses[] = "COALESCE(hv.stock_status, 'Available') = 'Unavailable'";
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "SELECT 
                    h.hospital_id,
                    h.hospital_name,
                    h.location as hospital_location,
                    h.phone as hospital_phone,
                    v.vaccine_id,
                    v.vaccine_name,
                    v.age_group,
                    v.description,
                    COALESCE(hv.stock_status, 'Available') AS stock_status,
                    hv.updated_at
                FROM hospitals h
                CROSS JOIN vaccines v
                LEFT JOIN hospital_vaccines hv ON hv.hospital_id = h.hospital_id AND hv.vaccine_id = v.vaccine_id
                WHERE $whereSql
                ORDER BY h.hospital_name ASC, v.vaccine_id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vaccineStockList = $stmt->fetchAll();

        // Summary counts across all active hospitals
        $statsStmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN COALESCE(hv.stock_status, 'Available') = 'Available' THEN 1 ELSE 0 END) AS available_count,
                SUM(CASE WHEN COALESCE(hv.stock_status, 'Available') = 'Unavailable' THEN 1 ELSE 0 END) AS unavailable_count
            FROM hospitals h
            CROSS JOIN vaccines v
            LEFT JOIN hospital_vaccines hv ON hv.hospital_id = h.hospital_id AND hv.vaccine_id = v.vaccine_id
            WHERE h.status = 'Active'
        ");
        $statsRow = $statsStmt->fetch();
        $availableStockCount   = (int)($statsRow['available_count'] ?? 0);
        $unavailableStockCount = (int)($statsRow['unavailable_count'] ?? 0);

    } catch (Exception $e) {
        setFlash('error', 'Error loading vaccine stock directory: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-syringe text-primary me-2"></i> Hospital Vaccine Availability</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Vaccine Stock Directory Across Hospitals</li>
                    </ul>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Metric Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-primary me-3">
                                    <i class="fas fa-syringe fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Catalog Vaccines</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $totalCatalogVaccines ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-info me-3">
                                    <i class="fas fa-hospital fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Active Hospitals</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $totalActiveHospitals ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-success me-3">
                                    <i class="fas fa-check-circle fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">In-Stock Across Facilities</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $availableStockCount ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-danger me-3">
                                    <i class="fas fa-exclamation-triangle fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Out of Stock Across Facilities</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $unavailableStockCount ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Card -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body py-3">
                    <form method="GET" action="vaccines.php" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold mb-1"><i class="fas fa-filter me-1 text-primary"></i> Filter by Hospital Facility:</label>
                            <select name="hospital_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">All Accredited Hospitals (<?= $totalActiveHospitals ?>)</option>
                                <?php foreach ($hospitals as $h): ?>
                                    <option value="<?= $h['hospital_id'] ?>" <?= $selectedHospitalId === (int)$h['hospital_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['hospital_name']) ?> (<?= htmlspecialchars($h['location'] ?: 'Facility') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1"><i class="fas fa-layer-group me-1 text-primary"></i> Availability Status:</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All Statuses (Available & Unavailable)</option>
                                <option value="Available" <?= $selectedStatus === 'Available' ? 'selected' : '' ?>>In Stock / Available Only</option>
                                <option value="Unavailable" <?= $selectedStatus === 'Unavailable' ? 'selected' : '' ?>>Out of Stock / Unavailable Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2 pt-3">
                            <button type="submit" class="btn btn-sm btn-primary flex-fill">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                            <?php if ($selectedHospitalId > 0 || $selectedStatus !== 'all'): ?>
                                <a href="vaccines.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Vaccine Stock Directory Table -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">
                        <i class="fas fa-clipboard-check text-primary me-2"></i> Hospital Vaccine Inventory & Availability Status
                    </div>
                    <span class="badge bg-primary px-3 py-2"><?= count($vaccineStockList) ?> Records Found</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Hospital Facility</th>
                                    <th>Vaccine Name</th>
                                    <th>Recommended Age Group</th>
                                    <th>Description & Protection</th>
                                    <th class="text-center" style="width: 170px;">Facility Availability</th>
                                    <th style="width: 140px;">Last Status Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($vaccineStockList)): ?>
                                    <?php foreach ($vaccineStockList as $index => $item): ?>
                                        <tr>
                                            <td class="font-monospace text-muted small">#<?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2 bg-primary text-white" style="width: 32px; height: 32px; font-size: 13px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                                        <i class="fas fa-hospital-alt"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['hospital_name']) ?></div>
                                                        <small class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($item['hospital_location'] ?: 'Main Clinic') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($item['vaccine_name']) ?></div>
                                                <small class="text-muted font-monospace">Code #VAC-<?= str_pad($item['vaccine_id'], 3, '0', STR_PAD_LEFT) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark px-2 py-1">
                                                    <i class="fas fa-baby me-1"></i><?= htmlspecialchars($item['age_group']) ?>
                                                </span>
                                            </td>
                                            <td style="max-width: 320px;">
                                                <small class="text-muted"><?= htmlspecialchars($item['description'] ?: 'Standard child immunization dose.') ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($item['stock_status'] === 'Available'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill fw-bold">
                                                        <i class="fas fa-check-circle me-1"></i> Available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill fw-bold">
                                                        <i class="fas fa-times-circle me-1"></i> Unavailable (Out of Stock)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= !empty($item['updated_at']) ? formatDate($item['updated_at']) : 'Catalog Default' ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">
                                            <i class="fas fa-syringe fa-3x mb-3 text-secondary d-block"></i>
                                            <h5>No Vaccine Availability Records Found</h5>
                                            <p class="small mb-0">Try changing or clearing your hospital or status filter criteria above.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
