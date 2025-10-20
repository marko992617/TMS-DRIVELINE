<?php
date_default_timezone_set('Europe/Belgrade');
require __DIR__ . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'header.php';
require 'config.php';
require 'functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$error = '';
$success = '';
$showAssignment = false;
$toursData = [];

// Only clear session data if explicitly requested
if (isset($_GET['clear']) || (isset($_POST['clear_data']))) {
    unset($_SESSION['tours_data'], $_SESSION['original_tours_data'], $_SESSION['assign_file']);
    // Force redirect to avoid form resubmission
    if (isset($_GET['clear'])) {
        header('Location: import_assign_tours.php');
        exit;
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel']) && !isset($_POST['assign_tours'])) {
    try {
        if ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Greška pri otpremanju fajla.');
        }

        $tmp = $_FILES['excel']['tmp_name'];
        $destDir = __DIR__ . '/uploads';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new Exception('Ne mogu kreirati uploads direktorijum.');
        }

        $dest = $destDir . '/assign_tours_' . uniqid() . '_' . basename($_FILES['excel']['name']);
        if (!move_uploaded_file($tmp, $dest)) {
            throw new Exception('Greška pri premještanju fajla.');
        }

        $_SESSION['assign_file'] = $dest;

        // Read Excel data
        $spreadsheet = IOFactory::load($dest);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $maxCol = Coordinate::columnIndexFromString($highestCol);

        // Get headers
        $headers = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $headers[] = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        }

        // Define column mapping
        $columnMapping = [
            'Datum utovara rute' => 'date',
            'ORS_ID' => 'ors_id',
            'Tip isporuke' => 'delivery_type',
            'Mesto utovara' => 'loading_loc',
            'Vreme utova.' => 'loading_time',
            'licenseplate' => 'license_plate',
            'Mesto istovara' => 'unloading_loc'
        ];

        // Get data rows
        $toursData = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $rowData = [];
            $mappedData = [];

            for ($c = 1; $c <= $maxCol; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $cellValue = $sheet->getCell($colLetter . $r)->getCalculatedValue();

                // Handle time format for loading_time
                if (stripos($headers[$c-1], 'vreme') !== false || stripos($headers[$c-1], 'time') !== false) {
                    if (is_numeric($cellValue)) {
                        // Excel time value to time format
                        $seconds = round($cellValue * 86400);
                        $cellValue = gmdate('H:i:s', $seconds);
                    }
                }

                // Handle date format
                if (stripos($headers[$c-1], 'datum') !== false && is_numeric($cellValue)) {
                    $cellValue = gmdate('Y-m-d', ($cellValue - 25569) * 86400);
                }

                $rowData[$headers[$c-1]] = (string)$cellValue;
            }

            // Map to our system columns
            foreach ($columnMapping as $excelCol => $systemCol) {
                if (isset($rowData[$excelCol])) {
                    $mappedData[$systemCol] = $rowData[$excelCol];
                }
            }

            // Add original data for reference
            $mappedData['_original'] = $rowData;

            if (!empty(array_filter($mappedData))) { // Skip empty rows
                $toursData[] = $mappedData;
            }
        }

        $_SESSION['tours_data'] = $toursData;
        $_SESSION['original_tours_data'] = $toursData; // Keep original for comparison
        $showAssignment = true;

        // Debug: Show first row structure and session info
        if (!empty($toursData)) {
            error_log('Excel Headers: ' . print_r(array_keys($toursData[0]), true));
            error_log('First Row Data: ' . print_r($toursData[0], true));
            error_log('Session tours_data count: ' . count($_SESSION['tours_data']));
            error_log('Session ID: ' . session_id());
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle tour assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tours'])) {
    try {
        // Check session first
        if (!isset($_SESSION['tours_data']) || empty($_SESSION['tours_data'])) {
            throw new Exception('Nema podataka o turama u sesiji. Molimo ponovo učitajte Excel fajl.');
        }

        $toursData = $_SESSION['tours_data'];
        $assignmentsMade = false;
        $assignmentsCount = 0;

        // Debug: Log session and POST data
        error_log("Session tours count: " . count($toursData));
        error_log("Session ID: " . session_id());
        error_log("All POST data: " . print_r($_POST, true));

        // Fetch driver, vehicle and client names for display in session
        $driversMap = $pdo->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $vehiclesMap = $pdo->query("SELECT id, plate FROM vehicles ORDER BY plate")->fetchAll(PDO::FETCH_ASSOC);
        $clientsMap = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $driversById = array_column($driversMap, 'name', 'id');
        $vehiclesById = array_column($vehiclesMap, 'plate', 'id');
        $clientsById = array_column($clientsMap, 'name', 'id');

        foreach ($toursData as $index => &$tour) {
            $driverId = $_POST['driver_' . $index] ?? null;
            $vehicleId = $_POST['vehicle_' . $index] ?? null;
            $clientId = $_POST['client_' . $index] ?? null;
            $loadingTime = $_POST['loading_time_' . $index] ?? null;

            // Debug log - check all POST data for this tour
            error_log("Tour $index: driver=$driverId, vehicle=$vehicleId, time=$loadingTime");

            if (!empty($driverId) && !empty($vehicleId) && $driverId !== '' && $vehicleId !== '' && $driverId !== '0' && $vehicleId !== '0') {
                // Parse date
                $date = $tour['date'] ?? date('Y-m-d');
                if (is_numeric($date)) {
                    $date = gmdate('Y-m-d', ($date - 25569) * 86400);
                }

                // Combine date and time - handle time format
                $timeOnly = $loadingTime ?? '08:00';
                if (strlen($timeOnly) == 5) {
                    $timeOnly .= ':00'; // Add seconds if missing
                }
                $loadingDateTime = $date . ' ' . $timeOnly;

                // Prepare data with length validation
                $loadingLoc = substr($tour['loading_loc'] ?? '', 0, 1000);
                $unloadingLoc = substr($tour['unloading_loc'] ?? '', 0, 2000);
                $orsId = substr($tour['ors_id'] ?? '', 0, 100);
                $deliveryType = substr($tour['delivery_type'] ?? '', 0, 100);
                $route = substr($tour['route'] ?? '', 0, 500);
                $note = substr($tour['note'] ?? '', 0, 1000);

                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO tours (date, driver_id, vehicle_id, client_id, loading_time, loading_loc, unloading_loc, ors_id, delivery_type, route, km, turnover, note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $date,
                    $driverId,
                    $vehicleId,
                    $clientId,
                    $loadingDateTime,
                    $loadingLoc,
                    $unloadingLoc,
                    $orsId,
                    $deliveryType,
                    $route,
                    $tour['km'] ?? 0,
                    $tour['turnover'] ?? 0,
                    $note
                ]);

                // Mark tour as assigned and store display names
                $tour['was_assigned'] = true;
                $tour['assigned_driver_id'] = $driverId;
                $tour['assigned_vehicle_id'] = $vehicleId;
                $tour['assigned_client_id'] = $clientId;
                $tour['assigned_loading_time'] = $loadingDateTime;
                $tour['assigned_driver_name'] = $driversById[$driverId] ?? 'Nepoznat vozač';
                $tour['assigned_vehicle_plate'] = $vehiclesById[$vehicleId] ?? 'Nepoznato vozilo';
                $tour['assigned_client_name'] = $clientsById[$clientId] ?? 'Nepoznat klijent';

                $assignmentsMade = true;
                $assignmentsCount++;
            }
        }

        if ($assignmentsMade) {
            // Update session data with assignment info
            $_SESSION['tours_data'] = $toursData;

            // Store assignment data for export
            $_SESSION['assignment_data'] = [
                'tours' => $toursData,
                'assigned_count' => $assignmentsCount,
                'timestamp' => time()
            ];

            // Clean up file
            if (isset($_SESSION['assign_file'])) {
                @unlink($_SESSION['assign_file']);
                unset($_SESSION['assign_file']);
            }

            $success = "Uspešno je dodeljeno $assignmentsCount tura vozačima i vozilima.";
            $showAssignment = true;
        } else {
            // More detailed error message
            $totalTours = count($toursData);
            $error = "Nijedna tura nije dodeljena od ukupno $totalTours tura. Molimo izaberite vozača i vozilo za najmanje jednu turu.";

            // Debug: Show what was received
            $debugInfo = [];
            foreach ($toursData as $index => $tour) {
                $driverId = $_POST['driver_' . $index] ?? 'NIJE_POSTAVLJEN';
                $vehicleId = $_POST['vehicle_' . $index] ?? 'NIJE_POSTAVLJEN';
                $debugInfo[] = "Tura $index: vozač=$driverId, vozilo=$vehicleId";
            }
            error_log("Debug assignment info: " . implode(' | ', $debugInfo));
            error_log("Session content check: " . print_r($_SESSION, true));

            $showAssignment = true;
        }

    } catch (Exception $e) {
        $error = 'Greška pri dodeli tura: ' . $e->getMessage();
        error_log('Assignment error: ' . $e->getMessage());
        error_log('Error trace: ' . $e->getTraceAsString());
        $showAssignment = true;

        // Restore session data to show assignment form again
        if (isset($_SESSION['tours_data']) && !empty($_SESSION['tours_data'])) {
            $toursData = $_SESSION['tours_data'];
        }
    }
}

// Get drivers, vehicles and clients
$drivers = $pdo->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("SELECT id, plate FROM vehicles ORDER BY plate")->fetchAll(PDO::FETCH_ASSOC);
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Tour colors for consistent styling
$tourColors = [
    '#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6',
    '#1abc9c', '#e67e22', '#34495e', '#e91e63', '#673ab7',
    '#ff5722', '#795548', '#607d8b', '#8bc34a', '#ffeb3b'
];

// Load existing tours data only if not explicitly cleared
if (isset($_SESSION['tours_data']) && !empty($_SESSION['tours_data']) && !isset($_GET['clear']) && !isset($_POST['clear_data'])) {
    $toursData = $_SESSION['tours_data'];
    $showAssignment = true;
    error_log("Loading existing tours from session: " . count($toursData) . " tours");
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
    border-radius: 0.375rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    font-weight: 600;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
}

.upload-zone:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}

.upload-zone i {
    font-size: 3rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.tour-row:hover {
    background-color: #f8f9fa;
}

.form-select-sm, .form-control-sm {
    font-size: 0.875rem;
}

.modal-lg {
    max-width: 900px;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.alert-success {
    background-color: #d1e7dd;
    color: #0f5132;
}

.alert-danger {
    background-color: #f8d7da;
    color: #842029;
}

.tour-card {
    transition: all 0.3s ease;
    border-radius: 8px;
}

.tour-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.custom-marker {
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    transition: all 0.2s ease;
}

.custom-marker:hover {
    transform: scale(1.1);
}

.pulse-marker {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.field-modified {
    background-color: #ffebee !important;
    border-color: #f44336 !important;
    box-shadow: 0 0 0 0.2rem rgba(244, 67, 54, 0.25) !important;
}

.field-modified:focus {
    box-shadow: 0 0 0 0.2rem rgba(244, 67, 54, 0.5) !important;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-file-excel text-success me-2"></i>
                Import i dodela tura
            </h2>
            <?php if ($showAssignment): ?>
                <a href="import_assign_tours.php?clear=1" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>
                    Očisti i počni iznova
                </a>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div class="flex-grow-1">
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportModifiedExcel()">
                        <i class="fas fa-file-excel me-1"></i>
                        Export Excel sa izmenama
                    </button>
                    <a href="import_assign_tours.php?clear=1" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>
                        Nova dodela
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$showAssignment): ?>
            <!-- Upload Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-upload me-2"></i>
                    Upload Excel fajla
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="upload-zone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>Izaberite Excel fajl sa turama</h5>
                            <p class="text-muted mb-3">Podržani formati: .xlsx, .xls</p>
                            <input type="file" class="form-control form-control-lg" id="excel" name="excel" accept=".xlsx,.xls" required>
                        </div>
                        <div class="text-center mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                Upload i prikaži ture
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Assignment Form and Map Layout -->
            <div class="row">
                <!-- Left side - Assignment Form -->
                <div class="col-lg-7 col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-tasks me-2"></i>
                                Dodela tura vozačima i vozilima
                            </span>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge bg-info"><?= count($toursData) ?> tura za dodelu</span>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="showAllToursMap()">
                                    <i class="fas fa-map me-1"></i>
                                    Prikaži sve na mapi
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportModifiedExcel()">
                                    <i class="fas fa-file-excel me-1"></i>
                                    Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="post" id="assignmentForm">
                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" name="assign_tours" class="btn btn-success me-2">
                                            <i class="fas fa-check me-2"></i>
                                            Dodeli ture
                                        </button>
                                        <a href="import_assign_tours.php?clear=1" class="btn btn-outline-primary">
                                            <i class="fas fa-file-upload me-2"></i>
                                            Učitaj novi Excel
                                        </a>
                                    </div>
                                    <div class="text-muted">
                                        <small>Crveno označene vrednosti su izmenjene u odnosu na originalni Excel</small>
                                    </div>
                                </div>

                                <!-- Tours Grid -->
                                <div class="row"><?php $totalTours = count($toursData); $toursPerRow = 3; ?></div>

                                <?php foreach ($toursData as $index => $tour): ?>
                                    <?php
                                    // Use mapped columns
                                    $date = $tour['date'] ?? '';
                                    $orsId = $tour['ors_id'] ?? '';
                                    $deliveryType = $tour['delivery_type'] ?? '';
                                    $loadingLoc = $tour['loading_loc'] ?? '';
                                    $unloadingLoc = $tour['unloading_loc'] ?? '';
                                    $originalLicensePlate = $tour['license_plate'] ?? '';
                                    $originalLoadingTime = $tour['loading_time'] ?? '08:00';

                                    // Check if it's start of new row
                                    if ($index % $toursPerRow == 0): ?>
                                        <?php if ($index > 0): ?></div><?php endif; ?>
                                        <div class="row mb-3">
                                    <?php endif; ?>

                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card h-100 tour-card" style="border: 2px solid <?= $tourColors[$index % count($tourColors)] ?>">
                                            <div class="card-header py-2" style="background: linear-gradient(135deg, <?= $tourColors[$index % count($tourColors)] ?>20, <?= $tourColors[$index % count($tourColors)] ?>10);">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <span class="badge" style="background-color: <?= $tourColors[$index % count($tourColors)] ?>; color: white;">
                                                            T<?= $index + 1 ?>
                                                        </span>
                                                        <strong class="ms-2"><?= htmlspecialchars($orsId) ?></strong>
                                                    </h6>
                                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showTourOnMap(<?= $index ?>)" title="Prikaži na mapi">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-2 mb-2">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Datum:</small>
                                                        <strong class="small"><?= htmlspecialchars($date) ?></strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Tip:</small>
                                                        <span class="badge bg-secondary small"><?= htmlspecialchars($deliveryType) ?></span>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <small class="text-muted d-block">Utovar:</small>
                                                    <div class="small"><?= htmlspecialchars($loadingLoc) ?></div>
                                                </div>

                                                <div class="mb-2">
                                                    <small class="text-muted d-block">Istovar:</small>
                                                    <div class="small text-truncate" title="<?= htmlspecialchars($unloadingLoc) ?>">
                                                        <?= htmlspecialchars($unloadingLoc) ?>
                                                    </div>
                                                </div>

                                                <?php if ($originalLicensePlate): ?>
                                                    <div class="mb-2 p-2 bg-light rounded">
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <small class="text-muted d-block mb-1">Originalni podaci iz Excel-a:</small>
                                                                <div class="row">
                                                                    <div class="col-6">
                                                                        <strong class="text-primary">Registracija:</strong><br>
                                                                        <span class="badge bg-secondary"><?= htmlspecialchars($originalLicensePlate) ?></span>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <strong class="text-primary">Vreme utovara:</strong><br>
                                                                        <span class="badge bg-secondary"><?= htmlspecialchars($originalLoadingTime) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (isset($tour['was_assigned']) && $tour['was_assigned']): ?>
                                                    <div class="mb-2 p-2 bg-success bg-opacity-10 border border-success rounded">
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <small class="text-success d-block mb-1"><i class="fas fa-check-circle"></i> Dodeljeno:</small>
                                                                <div class="row">
                                                                    <div class="col-4">
                                                                        <strong class="text-success">Vozač:</strong><br>
                                                                        <span class="badge bg-success"><?= htmlspecialchars($tour['assigned_driver_name'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <strong class="text-success">Vozilo:</strong><br>
                                                                        <span class="badge bg-success"><?= htmlspecialchars($tour['assigned_vehicle_plate'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <strong class="text-success">Klijent:</strong><br>
                                                                        <span class="badge bg-success"><?= htmlspecialchars($tour['assigned_client_name'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                </div>
                                                                <?php if (isset($tour['assigned_loading_time'])): ?>
                                                                    <div class="row mt-1">
                                                                        <div class="col-12">
                                                                            <strong class="text-success">Novo vreme:</strong><br>
                                                                            <span class="badge bg-success"><?= htmlspecialchars(date('H:i', strtotime($tour['assigned_loading_time']))) ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <label class="form-label small">Vozač:</label>
                                                        <select name="driver_<?= $index ?>" class="form-select form-select-sm assignment-field" data-tour="<?= $index ?>">
                                                            <option value="">Izaberite vozača</option>
                                                            <?php foreach ($drivers as $driver): ?>
                                                                <option value="<?= $driver['id'] ?>" <?= (isset($tour['assigned_driver_id']) && $tour['assigned_driver_id'] == $driver['id']) ? 'selected' : '' ?>><?= htmlspecialchars($driver['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Vozilo:</label>
                                                        <select name="vehicle_<?= $index ?>" class="form-select form-select-sm assignment-field" data-tour="<?= $index ?>" data-original-plate="<?= htmlspecialchars($originalLicensePlate) ?>">
                                                            <option value="">Izaberite vozilo</option>
                                                            <?php foreach ($vehicles as $vehicle): ?>
                                                                <option value="<?= $vehicle['id'] ?>" data-plate="<?= htmlspecialchars($vehicle['plate']) ?>" <?= (isset($tour['assigned_vehicle_id']) && $tour['assigned_vehicle_id'] == $vehicle['id']) ? 'selected' : '' ?>><?= htmlspecialchars($vehicle['plate']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Klijent:</label>
                                                        <select name="client_<?= $index ?>" class="form-select form-select-sm assignment-field" data-tour="<?= $index ?>">
                                                            <option value="">Izaberite klijenta</option>
                                                            <?php foreach ($clients as $client): ?>
                                                                <option value="<?= $client['id'] ?>" <?= (isset($tour['assigned_client_id']) && $tour['assigned_client_id'] == $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Novo vreme utovara:</label>
                                                        <input type="time" name="loading_time_<?= $index ?>" class="form-control form-control-sm assignment-field"
                                                               value="<?= htmlspecialchars($originalLoadingTime) ?>"
                                                               data-tour="<?= $index ?>"
                                                               data-original-time="<?= htmlspecialchars($originalLoadingTime) ?>">
                                                        <small class="text-muted">Originalno: <?= htmlspecialchars($originalLoadingTime) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($index == count($toursData) - 1): ?></div><?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right side - Live Map -->
                <div class="col-lg-5 col-12">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-map me-2"></i>
                                Mapa tura
                            </span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetMapView()">
                                    <i class="fas fa-expand-arrows-alt me-1"></i>
                                    Reset
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="showAllToursMap()">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Veća mapa
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="mainMap" style="height: 600px; border-radius: 0 0 0.375rem 0.375rem;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Tour Map Modal -->
            <div class="modal fade" id="tourMapModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tourMapTitle">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Mapa ture
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="tourMap" style="height: 500px; border-radius: 0.375rem;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Tours Map Modal -->
            <div class="modal fade" id="allToursMapModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-globe me-2"></i>
                                Sve ture na mapi
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0">Legenda tura</h6>
                                        </div>
                                        <div class="card-body p-2" id="tourLegend" style="max-height: 450px; overflow-y: auto;">
                                            <!-- Tour legend will be populated here -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div id="allToursMap" style="height: 500px; border-radius: 0.375rem;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Include Bootstrap JS and Leaflet for maps -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map, tourMap, allToursMap, mainMap;
const toursData = <?= json_encode($toursData) ?>;

// Color palette for different tours
const tourColors = [
    '#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6',
    '#1abc9c', '#e67e22', '#34495e', '#e91e63', '#673ab7',
    '#ff5722', '#795548', '#607d8b', '#8bc34a', '#ffeb3b'
];

// Store markers for easy access
let tourMarkers = [];
let markerGroups = {};

function initMap(mapId) {
    const mapElement = document.getElementById(mapId);
    if (!mapElement) return null;

    const mapInstance = L.map(mapId).setView([44.8125, 20.4612], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(mapInstance);

    return mapInstance;
}

// Track modifications
let originalData = {};
let modifiedFields = new Set();

// Initialize main map when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($showAssignment): ?>
    // Initialize modification tracking
    initModificationTracking();
    // Initialize main map for tours
    mainMap = initMap('mainMap');
    if (mainMap) {
        loadMainMapTours();
    }
    <?php endif; ?>
});

function initModificationTracking() {
    // Store original data
    document.querySelectorAll('.assignment-field').forEach(field => {
        const tourIndex = field.dataset.tour;
        if (!originalData[tourIndex]) {
            originalData[tourIndex] = {};
        }

        if (field.type === 'time') {
            originalData[tourIndex].time = field.dataset.originalTime || field.value;
        } else if (field.name.includes('vehicle_')) {
            originalData[tourIndex].originalPlate = field.dataset.originalPlate || '';
        }
    });

    // Add change listeners
    document.querySelectorAll('.assignment-field').forEach(field => {
        field.addEventListener('change', function() {
            checkFieldModification(this);
        });
    });
}

function checkFieldModification(field) {
    const tourIndex = field.dataset.tour;
    const fieldKey = field.name + '_' + tourIndex;

    let isModified = false;

    if (field.type === 'time') {
        const originalTime = field.dataset.originalTime || '';
        isModified = field.value !== originalTime;
    } else if (field.name.includes('vehicle_')) {
        const selectedOption = field.options[field.selectedIndex];
        const selectedPlate = selectedOption ? selectedOption.dataset.plate : '';
        const originalPlate = field.dataset.originalPlate || '';
        isModified = selectedPlate !== originalPlate && originalPlate !== '';
    }

    if (isModified) {
        field.classList.add('field-modified');
        modifiedFields.add(fieldKey);
    } else {
        field.classList.remove('field-modified');
        modifiedFields.delete(fieldKey);
    }
}

function exportModifiedExcel() {
    // Collect all form data including modifications
    const formData = new FormData();
    formData.append('export_with_modifications', '1');

    // Add modification tracking data
    const modifications = {};
    document.querySelectorAll('.assignment-field').forEach(field => {
        const tourIndex = field.dataset.tour;
        if (!modifications[tourIndex]) {
            modifications[tourIndex] = {};
        }

        if (field.type === 'time') {
            modifications[tourIndex].newTime = field.value;
            modifications[tourIndex].originalTime = field.dataset.originalTime || '';
            modifications[tourIndex].timeModified = field.classList.contains('field-modified');
        } else if (field.name.includes('vehicle_')) {
            const selectedOption = field.options[field.selectedIndex];
            modifications[tourIndex].newPlate = selectedOption ? selectedOption.dataset.plate : '';
            modifications[tourIndex].originalPlate = field.dataset.originalPlate || '';
            modifications[tourIndex].plateModified = field.classList.contains('field-modified');
        } else if (field.name.includes('driver_')) {
            const selectedOption = field.options[field.selectedIndex];
            modifications[tourIndex].driverName = selectedOption ? selectedOption.text : '';
            modifications[tourIndex].driverId = field.value;
        }
    });

    formData.append('modifications', JSON.stringify(modifications));

    // Create a temporary form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_modified_tours.php';
    form.target = '_blank';

    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function resetMapView() {
    if (mainMap && tourMarkers.length > 0) {
        const group = new L.featureGroup(tourMarkers);
        mainMap.fitBounds(group.getBounds().pad(0.1));
    }
}

function focusOnTour(tourIndex) {
    if (mainMap && markerGroups[tourIndex]) {
        const markers = markerGroups[tourIndex];
        if (markers.length > 0) {
            const group = new L.featureGroup(markers);
            mainMap.fitBounds(group.getBounds().pad(0.2));

            // Temporarily pulse the markers
            markers.forEach(marker => {
                const element = marker.getElement();
                if (element) {
                    element.classList.add('pulse-marker');
                    setTimeout(() => {
                        element.classList.remove('pulse-marker');
                    }, 3000);
                }
            });
        }
    }
}

function showMap() {
    const modal = new bootstrap.Modal(document.getElementById('mapModal'));
    modal.show();

    setTimeout(() => {
        if (!map) {
            map = initMap('map');
        }

        if (map) {
            map.invalidateSize();
            loadAllTourLocations();
        }
    }, 300);
}

function showAllToursMap() {
    const modal = new bootstrap.Modal(document.getElementById('allToursMapModal'));
    modal.show();

    setTimeout(() => {
        if (!allToursMap) {
            allToursMap = initMap('allToursMap');
        }

        if (allToursMap) {
            allToursMap.invalidateSize();
            loadAllToursWithColors();
        }
    }, 300);
}

function showTourOnMap(tourIndex) {
    const tour = toursData[tourIndex];
    if (!tour) return;

    const modal = new bootstrap.Modal(document.getElementById('tourMapModal'));
    document.getElementById('tourMapTitle').innerHTML = `
        <i class="fas fa-map-marked-alt me-2"></i>
        Mapa ture - ${tour.ors_id || 'Tura ' + (tourIndex + 1)}
    `;

    // Add tour information to modal body before the map
    const modalBody = document.querySelector('#tourMapModal .modal-body');
    const existingInfo = modalBody.querySelector('.tour-info');
    if (existingInfo) {
        existingInfo.remove();
    }

    const tourInfoDiv = document.createElement('div');
    tourInfoDiv.className = 'tour-info mb-3 p-3 bg-light rounded';

    // Use mapped column names
    const orsId = tour.ors_id || 'N/A';
    const deliveryType = tour.delivery_type || 'N/A';
    const date = tour.date || 'N/A';
    const loadingLoc = tour.loading_loc || 'N/A';
    const licensePlate = tour.license_plate || 'N/A';
    const loadingTime = tour.loading_time || 'N/A';

    tourInfoDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <strong>ORS ID:</strong> ${orsId}<br>
                <strong>Tip dostave:</strong> ${deliveryType}<br>
                <strong>Datum:</strong> ${date}
            </div>
            <div class="col-md-6">
                <strong>Utovar:</strong> ${loadingLoc}<br>
                <strong>Vreme utovara:</strong> ${loadingTime}<br>
                <strong>Registracija:</strong> ${licensePlate}
            </div>
        </div>
    `;

    modalBody.insertBefore(tourInfoDiv, modalBody.firstChild);

    modal.show();

    setTimeout(() => {
        if (!tourMap) {
            tourMap = initMap('tourMap');
        }

        if (tourMap) {
            tourMap.invalidateSize();
            loadTourLocations(tour, tourMap);
        }
    }, 300);
}

async function loadAllTourLocations() {
    if (!map) return;

    const bounds = L.latLngBounds([]);

    for (const tour of toursData) {
        if (tour.unloading_loc) {
            const locations = tour.unloading_loc.split(/[;,\n]+/);

            for (const location of locations) {
                const trimmedLoc = location.trim();
                if (trimmedLoc) {
                    try {
                        const coordinates = await getObjectCoordinates(trimmedLoc);
                        if (coordinates.lat && coordinates.lng) {
                            const marker = L.marker([coordinates.lat, coordinates.lng])
                                .addTo(map)
                                .bindPopup(`
                                    <div style="font-family: Arial, sans-serif;">
                                        <strong style="color: #0d6efd;">${trimmedLoc}</strong><br>
                                        <small><strong>ORS:</strong> ${tour.ors_id || 'N/A'}</small><br>
                                        <small><strong>Tip:</strong> ${tour.delivery_type || 'N/A'}</small>
                                    </div>
                                `);
                            bounds.extend([coordinates.lat, coordinates.lng]);
                        }
                    } catch (error) {
                        console.warn('Could not get coordinates for:', trimmedLoc);
                    }
                }
            }
        }
    }

    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [20, 20] });
    }
}

async function loadMainMapTours() {
    if (!mainMap) return;

    // Clear existing layers
    mainMap.eachLayer((layer) => {
        if (layer instanceof L.Marker || layer instanceof L.LayerGroup) {
            mainMap.removeLayer(layer);
        }
    });

    tourMarkers = [];
    markerGroups = {};
    const bounds = L.latLngBounds([]);

    for (let tourIndex = 0; tourIndex < toursData.length; tourIndex++) {
        const tour = toursData[tourIndex];
        const color = tourColors[tourIndex % tourColors.length];
        const unloadingLoc = tour.unloading_loc || '';

        if (!unloadingLoc) continue;

        markerGroups[tourIndex] = [];
        const locations = unloadingLoc.split(/[;,\n\r]+/).map(loc => loc.trim()).filter(loc => loc);

        for (let i = 0; i < locations.length; i++) {
            const location = locations[i];
            if (location) {
                try {
                    const coordinates = await getObjectCoordinates(location);
                    if (coordinates.lat && coordinates.lng) {
                        // Create enhanced custom icon
                        const customIcon = L.divIcon({
                            className: 'custom-marker',
                            html: `
                                <div style="
                                    background: linear-gradient(135deg, ${color}, ${color}cc);
                                    color: white;
                                    border-radius: 12px;
                                    width: 40px;
                                    height: 40px;
                                    display: flex;
                                    flex-direction: column;
                                    align-items: center;
                                    justify-content: center;
                                    font-weight: bold;
                                    border: 3px solid white;
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                                    font-size: 10px;
                                    line-height: 1;
                                ">
                                    <div style="font-size: 8px;">T${tourIndex + 1}</div>
                                    <div style="font-size: 12px;">${i + 1}</div>
                                </div>
                            `,
                            iconSize: [40, 40],
                            iconAnchor: [20, 20],
                            popupAnchor: [0, -20]
                        });

                        const marker = L.marker([coordinates.lat, coordinates.lng], { icon: customIcon })
                            .addTo(mainMap)
                            .bindPopup(`
                                <div style="font-family: Arial, sans-serif; min-width: 260px;">
                                    <div style="background: linear-gradient(135deg, ${color}, ${color}cc); color: white; padding: 10px; margin: -10px -10px 12px -10px; font-weight: bold; border-radius: 6px 6px 0 0;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Tura ${tourIndex + 1} - Istovar ${i + 1}</span>
                                            <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;">${tour.ors_id || 'N/A'}</span>
                                        </div>
                                    </div>
                                    <div style="padding: 0 2px;">
                                        <div style="margin-bottom: 8px;">
                                            <strong style="color: #0d6efd; font-size: 14px;">${coordinates.sifra || location}</strong><br>
                                            <strong style="color: #333;">${coordinates.naziv || 'Nema naziv'}</strong>
                                        </div>
                                        <div style="margin-bottom: 8px;">
                                            <small style="color: #666;">
                                                <i class="fas fa-map-marker-alt" style="color: ${color}; margin-right: 4px;"></i>
                                                ${coordinates.adresa || 'Nema adresu'}
                                                ${coordinates.grad ? ', ' + coordinates.grad : ''}
                                            </small>
                                        </div>
                                        <div style="border-top: 1px solid #eee; padding-top: 8px;">
                                            <div class="row" style="font-size: 12px;">
                                                <div class="col-6">
                                                    <strong>Tip:</strong><br>
                                                    <span class="badge" style="background-color: ${color}40; color: ${color}; font-size: 10px;">${tour.delivery_type || 'N/A'}</span>
                                                </div>
                                                <div class="col-6">
                                                    <strong>Datum:</strong><br>
                                                    <small>${tour.date || 'N/A'}</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="margin-top: 8px; text-align: center;">
                                            <a href="https://maps.google.com/maps?q=${coordinates.lat},${coordinates.lng}" target="_blank"
                                               style="color: ${color}; text-decoration: none; font-size: 12px;">
                                                <i class="fas fa-external-link-alt"></i> Google Maps
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `);

                        tourMarkers.push(marker);
                        markerGroups[tourIndex].push(marker);
                        bounds.extend([coordinates.lat, coordinates.lng]);
                    }
                } catch (error) {
                    console.warn('Could not get coordinates for:', location);
                }
            }
        }
    }

    if (bounds.isValid()) {
        mainMap.fitBounds(bounds, { padding: [20, 20] });
    }
}

async function loadAllToursWithColors() {
    if (!allToursMap) return;

    // Clear existing layers
    allToursMap.eachLayer((layer) => {
        if (layer instanceof L.Marker || layer instanceof L.LayerGroup) {
            allToursMap.removeLayer(layer);
        }
    });

    const bounds = L.latLngBounds([]);
    const legend = document.getElementById('tourLegend');
    legend.innerHTML = '';

    for (let tourIndex = 0; tourIndex < toursData.length; tourIndex++) {
        const tour = toursData[tourIndex];
        const color = tourColors[tourIndex % tourColors.length];
        const unloadingLoc = tour.unloading_loc || '';

        if (!unloadingLoc) continue;

        // Add legend entry
        const legendItem = document.createElement('div');
        legendItem.className = 'mb-2 p-2 border-bottom';
        legendItem.innerHTML = `
            <div class="d-flex align-items-center">
                <div style="width: 20px; height: 20px; background: linear-gradient(135deg, ${color}, ${color}cc); border-radius: 50%; margin-right: 8px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                <div>
                    <strong>Tura ${tourIndex + 1}</strong><br>
                    <small class="text-muted">${tour.ors_id || 'N/A'}</small>
                </div>
            </div>
        `;
        legend.appendChild(legendItem);

        // Parse and add markers for this tour
        const locations = unloadingLoc.split(/[;,\n\r]+/).map(loc => loc.trim()).filter(loc => loc);

        for (let i = 0; i < locations.length; i++) {
            const location = locations[i];
            if (location) {
                try {
                    const coordinates = await getObjectCoordinates(location);
                    if (coordinates.lat && coordinates.lng) {
                        // Create enhanced custom icon for modal map
                        const customIcon = L.divIcon({
                            className: 'custom-marker',
                            html: `
                                <div style="
                                    background: linear-gradient(135deg, ${color}, ${color}cc);
                                    color: white;
                                    border-radius: 12px;
                                    width: 40px;
                                    height: 40px;
                                    display: flex;
                                    flex-direction: column;
                                    align-items: center;
                                    justify-content: center;
                                    font-weight: bold;
                                    border: 3px solid white;
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                                    font-size: 10px;
                                    line-height: 1;
                                ">
                                    <div style="font-size: 8px;">T${tourIndex + 1}</div>
                                    <div style="font-size: 12px;">${i + 1}</div>
                                </div>
                            `,
                            iconSize: [40, 40],
                            iconAnchor: [20, 20],
                            popupAnchor: [0, -20]
                        });

                        const marker = L.marker([coordinates.lat, coordinates.lng], { icon: customIcon })
                            .addTo(allToursMap)
                            .bindPopup(`
                                <div style="font-family: Arial, sans-serif; min-width: 220px;">
                                    <div style="background: linear-gradient(135deg, ${color}, ${color}cc); color: white; padding: 8px; margin: -10px -10px 10px -10px; font-weight: bold;">
                                        Tura ${tourIndex + 1} - Istovar ${i + 1}
                                    </div>
                                    <strong style="color: #0d6efd;">${coordinates.sifra || location}</strong><br>
                                    <strong>${coordinates.naziv || 'Nema naziv'}</strong><br>
                                    <small style="color: #666;">
                                        ${coordinates.adresa || 'Nema adresu'}
                                        ${coordinates.grad ? ', ' + coordinates.grad : ''}
                                    </small><br>
                                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee;">
                                        <small><strong>ORS:</strong> ${tour.ors_id || 'N/A'}</small><br>
                                        <small><strong>Tip:</strong> ${tour.delivery_type || 'N/A'}</small><br>
                                        <small><strong>Datum:</strong> ${tour.date || 'N/A'}</small>
                                    </div>
                                </div>
                            `);

                        bounds.extend([coordinates.lat, coordinates.lng]);
                    }
                } catch (error) {
                    console.warn('Could not get coordinates for:', location);
                }
            }
        }
    }

    if (bounds.isValid()) {
        allToursMap.fitBounds(bounds, { padding: [20, 20] });
    }
}

async function loadTourLocations(tour, mapInstance) {
    const unloadingLoc = tour.unloading_loc || '';

    if (!unloadingLoc || !mapInstance) {
        console.log('Nema istovara ili mapa nije inicijalizovana');
        console.log('Available tour data:', Object.keys(tour));
        return;
    }

    // Clear existing markers
    mapInstance.eachLayer((layer) => {
        if (layer instanceof L.Marker) {
            mapInstance.removeLayer(layer);
        }
    });

    // Parse unloading locations - support multiple separators
    const locations = unloadingLoc.split(/[;,\n\r]+/).map(loc => loc.trim()).filter(loc => loc);
    const bounds = L.latLngBounds([]);
    let foundLocations = 0;
    let missingLocations = [];

    console.log('Parsiranje istovara:', locations);

    for (let i = 0; i < locations.length; i++) {
        const location = locations[i];
        if (location) {
            try {
                const coordinates = await getObjectCoordinates(location);
                if (coordinates.lat && coordinates.lng) {
                    // Create custom icon with number
                    const customIcon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background: #dc3545; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${i + 1}</div>`,
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                        popupAnchor: [0, -15]
                    });

                    const marker = L.marker([coordinates.lat, coordinates.lng], { icon: customIcon })
                        .addTo(mapInstance)
                        .bindPopup(`
                            <div style="font-family: Arial, sans-serif; min-width: 200px;">
                                <div style="background: #dc3545; color: white; padding: 5px 10px; margin: -10px -10px 10px -10px; font-weight: bold;">
                                    Istovar ${i + 1}
                                </div>
                                <strong style="color: #0d6efd;">${coordinates.sifra || location}</strong><br>
                                <strong>${coordinates.naziv || 'Nema naziv'}</strong><br>
                                <small style="color: #666;">
                                    ${coordinates.adresa || 'Nema adresu'}
                                    ${coordinates.grad ? ', ' + coordinates.grad : ''}
                                </small><br>
                                <div style="margin-top: 8px;">
                                    <a href="https://maps.google.com/maps?q=${coordinates.lat},${coordinates.lng}" target="_blank" style="color: #28a745; text-decoration: none; font-size: 12px;">
                                        <i class="fas fa-external-link-alt"></i> Otvori u Google Maps
                                    </a>
                                </div>
                            </div>
                        `);

                    bounds.extend([coordinates.lat, coordinates.lng]);
                    foundLocations++;
                } else {
                    missingLocations.push(location);
                }
            } catch (error) {
                console.warn('Nema koordinate za:', location);
                missingLocations.push(location);
            }
        }
    }

    // Show summary
    const summaryDiv = document.querySelector('.tour-info');
    if (summaryDiv) {
        const existingSummary = summaryDiv.querySelector('.location-summary');
        if (existingSummary) {
            existingSummary.remove();
        }

        const locationSummary = document.createElement('div');
        locationSummary.className = 'location-summary mt-2 pt-2 border-top';
        locationSummary.innerHTML = `
            <small>
                <strong>Istovari:</strong> ${foundLocations} pronađeno, ${missingLocations.length} bez koordinata
                ${missingLocations.length > 0 ? '<br><span class="text-warning">Bez koordinata: ' + missingLocations.join(', ') + '</span>' : ''}
            </small>
        `;
        summaryDiv.appendChild(locationSummary);
    }

    if (bounds.isValid()) {
        mapInstance.fitBounds(bounds, { padding: [20, 20] });
    } else {
        console.log('Nijedna lokacija nije pronađena sa validnim koordinatama');
        // Center on Belgrade if no coordinates found
        mapInstance.setView([44.8125, 20.4612], 10);
    }
}

async function getObjectCoordinates(objectCode) {
    const response = await fetch('get_object_coordinates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'object_code=' + encodeURIComponent(objectCode)
    });

    const data = await response.json();
    if (!data.ok) {
        throw new Error(data.error || 'Unknown error');
    }

    return data;
}

// Add form validation before submission
document.addEventListener('DOMContentLoaded', function() {
    const assignmentForm = document.getElementById('assignmentForm');
    if (assignmentForm) {
        assignmentForm.addEventListener('submit', function(e) {
            let hasAssignments = false;

            // Check if any tours have both driver and vehicle selected
            const driverSelects = document.querySelectorAll('select[name^="driver_"]');
            driverSelects.forEach(function(driverSelect) {
                const index = driverSelect.name.replace('driver_', '');
                const vehicleSelect = document.querySelector(`select[name="vehicle_${index}"]`);

                if (driverSelect.value && vehicleSelect && vehicleSelect.value) {
                    hasAssignments = true;
                }
            });

            if (!hasAssignments) {
                alert('Molimo izaberite vozača i vozilo za najmanje jednu turu pre slanja forme.');
                e.preventDefault();
                return false;
            }

            console.log('Form validation passed, submitting...');
        });
    }
});
</script>

<?php require 'footer.php'; ?>