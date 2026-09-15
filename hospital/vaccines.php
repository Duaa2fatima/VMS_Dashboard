<?php
/**
 * Vaccine Inventory & Availability Management - Hospital Panel
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireHospital();

$currentHospital = getCurrentHospital();
$hospitalId = $currentHospital['id'];

// Handle POST Requests BEFORE sending any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    // Action: Add Vaccine
    if ($action === 'add_vaccine') {
        $vaccineName = trim($_POST['vaccine_name'] ?? '');
        $ageGroup    = trim($_POST['age_group'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stockStatus = trim($_POST['stock_status'] ?? 'Available');

        if (!in_array($stockStatus, ['Available', 'Unavailable'])) {
            $stockStatus = 'Available';
        }

        if (empty($vaccineName) || empty($ageGroup)) {
            setFlash('error', 'Vaccine name and recommended age group are required fields.');
        } else {
            try {
                // Check if duplicate name exists
                $stmtCheck = $pdo->prepare("SELECT vaccine_id FROM vaccines WHERE LOWER(vaccine_name) = LOWER(?)");
                $stmtCheck->execute([$vaccineName]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', "A vaccine with the name '{$vaccineName}' already exists in the catalog.");
                } else {
                    $stmtInsert = $pdo->prepare("INSERT INTO vaccines (vaccine_name, age_group, description, stock_status) VALUES (?, ?, ?, ?)");
                    $stmtInsert->execute([$vaccineName, $ageGroup, $description, $stockStatus]);
                    $newVacId = $pdo->lastInsertId();

                    // Link specifically to this hospital's inventory
                    $stmtHv = $pdo->prepare("INSERT INTO hospital_vaccines (hospital_id, vaccine_id, stock_status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock_status = VALUES(stock_status)");
                    $stmtHv->execute([$hospitalId, $newVacId, $stockStatus]);

                    setFlash('success', "Vaccine '{$vaccineName}' has been added successfully with status '{$stockStatus}' for your facility.");
                }
            } catch (Exception $e) {
                setFlash('error', 'Error adding vaccine: ' . $e->getMessage());
            }
        }
        header('Location: vaccines.php');
        exit;
    }

    // Action: Toggle Stock Availability (Isolated to this Hospital)
    elseif ($action === 'toggle_status') {
        $vaccineId  = (int)($_POST['vaccine_id'] ?? 0);
        $currStatus = $_POST['current_status'] ?? 'Available';
        $newStatus  = ($currStatus === 'Available') ? 'Unavailable' : 'Available';

        if ($vaccineId > 0) {
            try {
                $stmtToggle = $pdo->prepare("
                    INSERT INTO hospital_vaccines (hospital_id, vaccine_id, stock_status) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE stock_status = VALUES(stock_status)
                ");
                $stmtToggle->execute([$hospitalId, $vaccineId, $newStatus]);
                
                $vName = $pdo->query("SELECT vaccine_name FROM vaccines WHERE vaccine_id = {$vaccineId}")->fetchColumn() ?: "Vaccine #{$vaccineId}";
                setFlash('success', "Availability for '{$vName}' in your facility changed to '{$newStatus}'.");
            } catch (Exception $e) {
                setFlash('error', 'Error updating stock status: ' . $e->getMessage());
            }
        }
        header('Location: vaccines.php');
        exit;
    }

    // Action: Edit Vaccine Details & Hospital Stock Status
    elseif ($action === 'edit_vaccine') {
        $vaccineId   = (int)($_POST['vaccine_id'] ?? 0);
        $vaccineName = trim($_POST['vaccine_name'] ?? '');
        $ageGroup    = trim($_POST['age_group'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stockStatus = trim($_POST['stock_status'] ?? 'Available');

        if (!in_array($stockStatus, ['Available', 'Unavailable'])) {
            $stockStatus = 'Available';
        }

        if ($vaccineId <= 0 || empty($vaccineName) || empty($ageGroup)) {
            setFlash('error', 'Invalid vaccine ID, name, or age group.');
        } else {
            try {
                // Update catalog metadata
                $stmtUpdate = $pdo->prepare("UPDATE vaccines SET vaccine_name = ?, age_group = ?, description = ? WHERE vaccine_id = ?");
                $stmtUpdate->execute([$vaccineName, $ageGroup, $description, $vaccineId]);

                // Update stock status specifically for this hospital
                $stmtHv = $pdo->prepare("
                    INSERT INTO hospital_vaccines (hospital_id, vaccine_id, stock_status) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE stock_status = VALUES(stock_status)
                ");
                $stmtHv->execute([$hospitalId, $vaccineId, $stockStatus]);

                setFlash('success', "Vaccine '{$vaccineName}' updated successfully for your facility.");
            } catch (Exception $e) {
                setFlash('error', 'Error updating vaccine: ' . $e->getMessage());
            }
        }
        header('Location: vaccines.php');
        exit;
    }

    // Action: Delete Vaccine (with dependency check)
    elseif ($action === 'delete_vaccine') {
        $vaccineId = (int)($_POST['vaccine_id'] ?? 0);

        if ($vaccineId > 0) {
            try {
                // Check if bookings reference this vaccine at this hospital
                $stmtBk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vaccine_id = ? AND hospital_id = ?");
                $stmtBk->execute([$vaccineId, $hospitalId]);
                $bookingCount = (int)$stmtBk->fetchColumn();

                if ($bookingCount > 0) {
                    setFlash('warning', "Cannot remove this vaccine because {$bookingCount} booking(s) at your facility are linked to it. You can set its status to 'Unavailable' instead.");
                } else {
                    // Mark as Unavailable in this hospital's inventory
                    $stmtHv = $pdo->prepare("
                        INSERT INTO hospital_vaccines (hospital_id, vaccine_id, stock_status) 
                        VALUES (?, ?, 'Unavailable') 
                        ON DUPLICATE KEY UPDATE stock_status = 'Unavailable'
                    ");
                    $stmtHv->execute([$hospitalId, $vaccineId]);
                    setFlash('success', 'Vaccine availability marked as Unavailable in your facility.');
                }
            } catch (Exception $e) {
                setFlash('error', 'Error updating vaccine: ' . $e->getMessage());
            }
        }
        header('Location: vaccines.php');
        exit;
    }
}

// Fetch Vaccine Catalog & Summary Counts for THIS Hospital
$vaccines = [];
$totalVaccines = 0;
$availableCount = 0;
$unavailableCount = 0;

if ($pdo) {
    try {
        $stmtVax = $pdo->prepare("
            SELECT v.*, COALESCE(hv.stock_status, 'Available') AS stock_status
            FROM vaccines v
            LEFT JOIN hospital_vaccines hv ON v.vaccine_id = hv.vaccine_id AND hv.hospital_id = ?
            ORDER BY v.vaccine_id ASC
        ");
        $stmtVax->execute([$hospitalId]);
        $vaccines = $stmtVax->fetchAll();
        $totalVaccines = count($vaccines);
        foreach ($vaccines as $v) {
            if ($v['stock_status'] === 'Available') {
                $availableCount++;
            } else {
                $unavailableCount++;
            }
        }
    } catch (Exception $e) {
        setFlash('error', 'Error loading vaccine catalog: ' . $e->getMessage());
    }
}

$pageTitle = 'Vaccine Inventory & Availability';
$activePage = 'hospital_vaccines';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-syringe text-primary me-2"></i> Vaccine Inventory & Availability</h3>
                    <h6 class="op-7 mb-0 text-muted">Manage live vaccine stock for <strong class="text-primary"><?= htmlspecialchars($currentHospital['hospital_name'] ?? $currentHospital['name'] ?? 'Healthcare Facility') ?></strong> &bull; Changes apply only to your healthcare center</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 mt-3 mt-md-0">
                    <button type="button" class="btn btn-primary btn-round shadow-sm" data-bs-toggle="modal" data-bs-target="#addVaccineModal">
                        <i class="fas fa-plus-circle me-1"></i> Add New Vaccination
                    </button>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Metrics Cards -->
            <div class="row mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm border-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                                        <i class="fas fa-prescription-bottle-alt"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category text-muted mb-0">Total Vaccines</p>
                                        <h4 class="card-title fw-bold text-primary mb-0"><?= $totalVaccines ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm border-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-success bubble-shadow-small">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category text-muted mb-0">Available (In Stock)</p>
                                        <h4 class="card-title fw-bold text-success mb-0"><?= $availableCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm border-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category text-muted mb-0">Unavailable (Out of Stock)</p>
                                        <h4 class="card-title fw-bold text-danger mb-0"><?= $unavailableCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vaccines Catalog Table -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-list-ul text-primary me-2"></i> Immunization Vaccines & Stock Status
                        </h5>
                        <small class="text-muted">Click the availability button to toggle stock status instantly</small>
                    </div>
                    <span class="badge bg-primary px-3 py-2 fs-7"><?= count($vaccines) ?> Registered</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable-custom">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Vaccine Name</th>
                                    <th>Recommended Age</th>
                                    <th>Target Diseases & Notes</th>
                                    <th class="text-center" style="width: 170px;">Availability Status</th>
                                    <th class="text-end" style="width: 130px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($vaccines)): ?>
                                    <?php foreach ($vaccines as $v): ?>
                                        <tr>
                                            <td><span class="badge bg-dark">#<?= $v['vaccine_id'] ?></span></td>
                                            <td>
                                                <strong class="text-primary fs-6"><?= htmlspecialchars($v['vaccine_name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark px-2 py-1">
                                                    <i class="fas fa-baby me-1"></i><?= htmlspecialchars($v['age_group']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small text-muted" style="max-width: 380px;">
                                                    <?= htmlspecialchars($v['description'] ?: 'Standard immunization dose.') ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" action="vaccines.php" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="vaccine_id" value="<?= $v['vaccine_id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $v['stock_status'] ?>">
                                                    <?php if ($v['stock_status'] === 'Available'): ?>
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" data-bs-toggle="tooltip" title="Click to mark as Unavailable">
                                                            <i class="fas fa-check-circle me-1"></i> Available
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm" data-bs-toggle="tooltip" title="Click to mark as Available">
                                                            <i class="fas fa-times-circle me-1"></i> Unavailable
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-round" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editVaccineModal"
                                                            data-id="<?= $v['vaccine_id'] ?>"
                                                            data-name="<?= htmlspecialchars($v['vaccine_name'], ENT_QUOTES) ?>"
                                                            data-age="<?= htmlspecialchars($v['age_group'], ENT_QUOTES) ?>"
                                                            data-desc="<?= htmlspecialchars($v['description'], ENT_QUOTES) ?>"
                                                            data-status="<?= $v['stock_status'] ?>"
                                                            title="Edit Details">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-round ms-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteVaccineModal"
                                                            data-id="<?= $v['vaccine_id'] ?>"
                                                            data-name="<?= htmlspecialchars($v['vaccine_name'], ENT_QUOTES) ?>"
                                                            title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-syringe fa-2x mb-2 text-muted opacity-50 d-block"></i>
                                            No vaccines currently registered in catalog.
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

    <!-- Modal: Add New Vaccine -->
    <div class="modal fade" id="addVaccineModal" tabindex="-1" aria-labelledby="addVaccineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addVaccineModalLabel">
                        <i class="fas fa-plus-circle me-2"></i> Add New Vaccination
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="vaccines.php">
                    <input type="hidden" name="action" value="add_vaccine">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Vaccine Name <span class="text-danger">*</span></label>
                            <input type="text" name="vaccine_name" class="form-control" placeholder="e.g. Hepatitis A (HepA-1)" required maxlength="150">
                            <small class="text-muted">Enter brand or standard generic vaccine denomination</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Recommended Age Group <span class="text-danger">*</span></label>
                            <input type="text" name="age_group" list="ageGroupSuggestions" class="form-control" placeholder="e.g. 6 Weeks, 12 Months" required maxlength="100">
                            <datalist id="ageGroupSuggestions">
                                <option value="At Birth">
                                <option value="At Birth (within 24h)">
                                <option value="6 Weeks">
                                <option value="10 Weeks">
                                <option value="14 Weeks">
                                <option value="6 Months">
                                <option value="9 Months">
                                <option value="12 Months">
                                <option value="15-18 Months">
                                <option value="16-24 Months">
                                <option value="2-3 Years">
                                <option value="5-6 Years">
                                <option value="10-12 Years">
                                <option value="All Age Groups">
                            </datalist>
                            <small class="text-muted">Select a standard schedule milestone or enter custom age text</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Stock Availability Status <span class="text-danger">*</span></label>
                            <select name="stock_status" class="form-select" required>
                                <option value="Available" selected>Available (In Stock & Open for Appointments)</option>
                                <option value="Unavailable">Unavailable (Out of Stock / Restocking)</option>
                            </select>
                            <small class="text-muted">When marked as Available, parents will be able to book this vaccine</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Target Diseases & Clinical Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Description of protection, dosing guidelines, or immunization targets..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i> Add Vaccination
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Vaccine -->
    <div class="modal fade" id="editVaccineModal" tabindex="-1" aria-labelledby="editVaccineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="editVaccineModalLabel">
                        <i class="fas fa-edit me-2"></i> Edit Vaccine Information
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="vaccines.php">
                    <input type="hidden" name="action" value="edit_vaccine">
                    <input type="hidden" name="vaccine_id" id="edit_vaccine_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Vaccine Name <span class="text-danger">*</span></label>
                            <input type="text" name="vaccine_name" id="edit_vaccine_name" class="form-control" required maxlength="150">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Recommended Age Group <span class="text-danger">*</span></label>
                            <input type="text" name="age_group" id="edit_age_group" list="ageGroupSuggestions" class="form-control" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Stock Availability Status <span class="text-danger">*</span></label>
                            <select name="stock_status" id="edit_stock_status" class="form-select" required>
                                <option value="Available">Available (In Stock & Open for Appointments)</option>
                                <option value="Unavailable">Unavailable (Out of Stock / Restocking)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Target Diseases & Clinical Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Vaccine Confirmation -->
    <div class="modal fade" id="deleteVaccineModal" tabindex="-1" aria-labelledby="deleteVaccineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="deleteVaccineModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i> Confirm Removal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="vaccines.php">
                    <input type="hidden" name="action" value="delete_vaccine">
                    <input type="hidden" name="vaccine_id" id="delete_vaccine_id">
                    <div class="modal-body">
                        <p class="mb-2">Are you sure you want to remove the following vaccine from the catalog?</p>
                        <div class="alert alert-light border">
                            <strong id="delete_vaccine_name" class="text-danger"></strong>
                        </div>
                        <small class="text-muted">Note: Vaccines associated with existing appointments or clinical records cannot be deleted; consider marking them as <em>Unavailable</em> instead.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete Vaccine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Populate Edit Modal
            var editModal = document.getElementById('editVaccineModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    if (!button) return;
                    document.getElementById('edit_vaccine_id').value = button.getAttribute('data-id') || '';
                    document.getElementById('edit_vaccine_name').value = button.getAttribute('data-name') || '';
                    document.getElementById('edit_age_group').value = button.getAttribute('data-age') || '';
                    document.getElementById('edit_description').value = button.getAttribute('data-desc') || '';
                    document.getElementById('edit_stock_status').value = button.getAttribute('data-status') || 'Available';
                });
            }

            // Populate Delete Modal
            var deleteModal = document.getElementById('deleteVaccineModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    if (!button) return;
                    document.getElementById('delete_vaccine_id').value = button.getAttribute('data-id') || '';
                    document.getElementById('delete_vaccine_name').textContent = button.getAttribute('data-name') || 'Selected Vaccine';
                });
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
