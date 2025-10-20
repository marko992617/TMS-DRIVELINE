
<?php
ob_start();
date_default_timezone_set('Europe/Belgrade');
require __DIR__ . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'header.php';
require 'config.php';
require 'functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function readSpreadsheet($file) {
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $data = [];
    $highestRow = $sheet->getHighestRow();
    for ($r = 2; $r <= $highestRow; $r++) {
        $row = [];
        for ($c = 1; $c <= 20; $c++) {
            $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r);
            $row[] = $cell->getCalculatedValue();
        }
        if (!empty(array_filter($row))) {
            $data[] = $row;
        }
    }
    return $data;
}

function extractWaybillNumber($fullWaybill) {
    if (empty($fullWaybill)) return '';
    $parts = explode('/', $fullWaybill);
    return trim($parts[0]);
}

function normalizeLocation($location) {
    return trim(strtolower($location));
}

$step = $_GET['step'] ?? ($_POST['step'] ?? 'upload');

// Korak 1: Upload fajla
if ($step === 'upload') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $dest = __DIR__ . '/uploads/import_' . uniqid() . '.xlsx';
            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0755, true);
            }
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $_SESSION['import_file'] = $dest;
                header('Location: import_calculation.php?step=headers');
                exit;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
      <meta charset="UTF-8">
      <title>Import obračunskih podataka</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="p-3">
      <div class="container">
        <h3 class="mb-4">
          <i class="fas fa-file-excel text-success me-2"></i>
          Import obračunskih podataka
        </h3>
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Excel fajl sa obračunskim podacima:</label>
            <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload me-2"></i>Učitaj fajl
          </button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Korak 2: Mapiranje kolona
if ($step === 'headers') {
    if (!isset($_SESSION['import_file'])) {
        header('Location: import_calculation.php');
        exit;
    }
    $rows = readSpreadsheet($_SESSION['import_file']);
    if (empty($rows)) {
        echo "Nema podataka u fajlu.";
        exit;
    }
    $headers = $rows[0] ?? [];
    
    // Automatsko mapiranje kolona na osnovu naziva
    function autoMapColumns($headers) {
        $mapping = [
            'date' => null,
            'km' => null, 
            'turnover' => null,
            'maxir_id' => null,
            'vehicle_plate' => null,
            'unloading' => null,
            'delivery_type' => null
        ];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));
            
            if ($mapping['date'] === null && (
                strpos($normalizedHeader, 'datum') !== false ||
                strpos($normalizedHeader, 'date') !== false
            )) {
                $mapping['date'] = $index;
            }
            
            if ($mapping['km'] === null && (
                strpos($normalizedHeader, 'km') !== false ||
                strpos($normalizedHeader, 'kilom') !== false ||
                strpos($normalizedHeader, 'kilometraža') !== false
            )) {
                $mapping['km'] = $index;
            }
            
            if ($mapping['turnover'] === null && (
                strpos($normalizedHeader, 'prihod') !== false ||
                strpos($normalizedHeader, 'promet') !== false ||
                strpos($normalizedHeader, 'iznos') !== false ||
                strpos($normalizedHeader, 'vrednost') !== false ||
                strpos($normalizedHeader, 'turnover') !== false
            )) {
                $mapping['turnover'] = $index;
            }
            
            if ($mapping['maxir_id'] === null && (
                strpos($normalizedHeader, 'maxir') !== false ||
                strpos($normalizedHeader, 'id') !== false ||
                strpos($normalizedHeader, 'broj') !== false ||
                strpos($normalizedHeader, 'šifra') !== false
            )) {
                $mapping['maxir_id'] = $index;
            }
            
            if ($mapping['vehicle_plate'] === null && (
                strpos($normalizedHeader, 'registr') !== false ||
                strpos($normalizedHeader, 'vozilo') !== false ||
                strpos($normalizedHeader, 'plate') !== false ||
                strpos($normalizedHeader, 'reg') !== false ||
                strpos($normalizedHeader, 'tablice') !== false
            )) {
                $mapping['vehicle_plate'] = $index;
            }
            
            if ($mapping['unloading'] === null && (
                strpos($normalizedHeader, 'istovar') !== false ||
                strpos($normalizedHeader, 'unload') !== false ||
                strpos($normalizedHeader, 'destinacija') !== false ||
                strpos($normalizedHeader, 'odredište') !== false ||
                strpos($normalizedHeader, 'radnja') !== false
            )) {
                $mapping['unloading'] = $index;
            }
            
            if ($mapping['delivery_type'] === null && (
                strpos($normalizedHeader, 'tip') !== false ||
                strpos($normalizedHeader, 'isporuke') !== false ||
                strpos($normalizedHeader, 'delivery') !== false ||
                strpos($normalizedHeader, 'type') !== false
            )) {
                $mapping['delivery_type'] = $index;
            }
        }
        
        return $mapping;
    }
    
    $autoMapping = autoMapColumns($headers);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['import_map'] = $_POST;
        header('Location: import_calculation.php?step=auto_match');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
      <meta charset="UTF-8">
      <title>Mapiranje kolona</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="p-3">
      <div class="container">
        <h3 class="mb-4">Mapiranje kolona</h3>
        
        <form method="post">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za datum:</label>
                <select name="date" class="form-select" required>
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['date'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za kilometražu:</label>
                <select name="km" class="form-select" required>
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['km'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za prihod/promet:</label>
                <select name="turnover" class="form-select" required>
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['turnover'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za MAXIR ID:</label>
                <select name="maxir_id" class="form-select" required>
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['maxir_id'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za registraciju vozila:</label>
                <select name="vehicle_plate" class="form-select">
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['vehicle_plate'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za istovar:</label>
                <select name="unloading" class="form-select">
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['unloading'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Kolona za tip isporuke:</label>
                <select name="delivery_type" class="form-select">
                  <option value="">Izaberite kolonu</option>
                  <?php foreach ($headers as $i => $h): ?>
                    <option value="<?= $i ?>" <?= $autoMapping['delivery_type'] === $i ? 'selected' : '' ?>><?= $i ?>: <?= htmlspecialchars($h) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-arrow-right me-2"></i>Nastavi na automatsko spajanje
          </button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// KORAK 3: Automatsko spajanje postojećih tura
if ($step === 'auto_match') {
    if (!isset($_SESSION['import_file']) || !isset($_SESSION['import_map'])) {
        header('Location: import_calculation.php');
        exit;
    }
    
    $rows = readSpreadsheet($_SESSION['import_file']);
    $map = $_SESSION['import_map'];
    
    // Grupisanje podataka po datumu
    $excelTours = [];
    $toursByDate = [];
    
    foreach ($rows as $idx => $r) {
        if ($idx === 0) continue; // Skip header
        
        $dateValue = $r[$map['date']] ?? '';
        if (is_numeric($dateValue)) {
            $date = date('Y-m-d', ($dateValue - 25569) * 86400);
        } else {
            $date = date('Y-m-d', strtotime($dateValue));
        }
        
        $km = intval($r[$map['km']] ?? 0);
        $turnover = floatval(str_replace(',', '.', $r[$map['turnover']] ?? 0));
        $maxirId = trim($r[$map['maxir_id']] ?? '');
        $vehiclePlate = trim($r[$map['vehicle_plate']] ?? '');
        $unloadingLoc = trim($r[$map['unloading']] ?? '');
        $deliveryType = trim($r[$map['delivery_type']] ?? '');
        
        $excelTour = [
            'date' => $date,
            'km' => $km,
            'turnover' => $turnover,
            'maxir_id' => $maxirId,
            'vehicle_plate' => $vehiclePlate,
            'unloading_loc' => $unloadingLoc,
            'delivery_type' => $deliveryType,
            'row_index' => $idx
        ];
        
        $excelTours[] = $excelTour;
        $toursByDate[$date][] = $excelTour;
    }
    
    // Dobavljanje sistemskih tura iz obračunskog perioda
    $minDate = min(array_keys($toursByDate));
    $maxDate = max(array_keys($toursByDate));
    
    $stmtSystemTours = $pdo->prepare("
        SELECT t.id, t.km, t.turnover, t.loading_loc, t.unloading_loc, t.delivery_type, t.vehicle_id,
               ds.waybill_number, 
               v.plate as vehicle_plate,
               DATE(t.loading_time) as tour_date
        FROM tours t 
        LEFT JOIN driver_submissions ds ON ds.tour_id = t.id 
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        WHERE DATE(t.loading_time) BETWEEN ? AND ?
        ORDER BY t.loading_time
    ");
    $stmtSystemTours->execute([$minDate, $maxDate]);
    $systemTours = $stmtSystemTours->fetchAll(PDO::FETCH_ASSOC);
    
    // Automatsko spajanje
    $autoMatches = [];
    $usedSystemTours = [];
    
    // 1. Spajanje po tovarnom listu
    foreach ($excelTours as $excelIndex => $excelTour) {
        $maxirId = $excelTour['maxir_id'];
        if (empty($maxirId)) continue;
        
        foreach ($systemTours as $systemTour) {
            if (in_array($systemTour['id'], $usedSystemTours)) continue;
            
            $waybillNumber = extractWaybillNumber($systemTour['waybill_number']);
            if (!empty($waybillNumber) && $waybillNumber === $maxirId) {
                $autoMatches[$excelTour['date']][$excelIndex] = $systemTour['id'];
                $usedSystemTours[] = $systemTour['id'];
                break;
            }
        }
    }
    
    // 2. Spajanje po istovarima za neconnected ture
    foreach ($excelTours as $excelIndex => $excelTour) {
        if (isset($autoMatches[$excelTour['date']][$excelIndex])) continue;
        
        $excelUnloading = normalizeLocation($excelTour['unloading_loc']);
        if (empty($excelUnloading)) continue;
        
        foreach ($systemTours as $systemTour) {
            if (in_array($systemTour['id'], $usedSystemTours)) continue;
            
            $systemUnloading = normalizeLocation($systemTour['unloading_loc']);
            if (!empty($systemUnloading) && strpos($systemUnloading, $excelUnloading) !== false) {
                $autoMatches[$excelTour['date']][$excelIndex] = $systemTour['id'];
                $usedSystemTours[] = $systemTour['id'];
                break;
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_auto_matches'])) {
        // Ažuriraj postojeće ture - SAMO prihod i kilometraža
        $updatedCount = 0;
        foreach ($autoMatches as $date => $group) {
            foreach ($group as $excelIdx => $tourId) {
                if (!$tourId) continue;
                
                $excelTour = $excelTours[$excelIdx];
                
                // Update samo km i turnover, ne menjamo ostalo
                $upd = $pdo->prepare("UPDATE tours SET km=?, turnover=? WHERE id=?");
                $upd->execute([$excelTour['km'], $excelTour['turnover'], $tourId]);
                $updatedCount++;
            }
        }
        
        // Sačuvaj podatke za sledeći korak
        $_SESSION['auto_matches'] = $autoMatches;
        $_SESSION['excel_tours'] = $excelTours;
        $_SESSION['system_tours'] = $systemTours;
        $_SESSION['used_system_tours'] = $usedSystemTours;
        $_SESSION['updated_count'] = $updatedCount;
        
        header('Location: import_calculation.php?step=manual_match');
        exit;
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
      <meta charset="UTF-8">
      <title>Automatsko spajanje tura</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
      <style>
        .auto-matched { background-color: #d1e7dd; }
      </style>
    </head>
    <body class="p-3">
      <div class="container-fluid">
        <h3 class="mb-4">KORAK 1: Automatsko spajanje postojećih tura</h3>
        
        <div class="alert alert-info">
          <strong>Automatsko spajanje:</strong> Prvo po broju tovarnog lista, zatim po istovarima.
          <br><strong>Ažuriranje:</strong> Menjaju se SAMO prihod i kilometraža postojećih tura.
        </div>
        
        <form method="post">
          <?php if (!empty($autoMatches)): ?>
          <div class="card mb-4">
            <div class="card-header bg-success text-white">
              <h5 class="mb-0">
                <i class="fas fa-magic me-2"></i>Automatski spojene ture
                <span class="badge bg-light text-dark"><?= array_sum(array_map('count', $autoMatches)) ?></span>
              </h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Datum</th>
                      <th>MAXIR ID</th>
                      <th>KM (Excel)</th>
                      <th>Prihod (Excel)</th>
                      <th>Registracija</th>
                      <th>Tip isporuke</th>
                      <th>Istovar</th>
                      <th>↔</th>
                      <th>Sistemska tura</th>
                      <th>Tovarni list</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($autoMatches as $date => $matches): ?>
                      <?php foreach ($matches as $excelIndex => $tourId): ?>
                        <?php 
                        $excelTour = $excelTours[$excelIndex];
                        $systemTour = array_values(array_filter($systemTours, function($t) use ($tourId) { return $t['id'] == $tourId; }))[0];
                        ?>
                        <tr class="auto-matched">
                          <td><?= $date ?></td>
                          <td><strong><?= htmlspecialchars($excelTour['maxir_id']) ?></strong></td>
                          <td><?= $excelTour['km'] ?></td>
                          <td><?= number_format($excelTour['turnover'], 2) ?></td>
                          <td><?= htmlspecialchars($excelTour['vehicle_plate']) ?></td>
                          <td><span class="badge bg-secondary"><?= htmlspecialchars($excelTour['delivery_type'] ?? 'N/A') ?></span></td>
                          <td><small><?= htmlspecialchars(substr($excelTour['unloading_loc'], 0, 30)) ?></small></td>
                          <td><i class="fas fa-arrows-alt-h text-success"></i></td>
                          <td><strong>ID <?= $systemTour['id'] ?></strong></td>
                          <td><?= htmlspecialchars(extractWaybillNumber($systemTour['waybill_number'])) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <div class="d-flex justify-content-end">
            <button type="submit" name="save_auto_matches" class="btn btn-success btn-lg">
              <i class="fas fa-save me-2"></i>Sačuvaj i nastavi na ručno spajanje
            </button>
          </div>
          
          <?php else: ?>
          <div class="alert alert-warning">
            <h5>Nema automatski spojenih tura</h5>
            <p>Sistem nije uspeo da automatski spoji ture po tovarnom listu ili istovarima.</p>
            <button type="submit" name="save_auto_matches" class="btn btn-warning">
              <i class="fas fa-arrow-right me-2"></i>Nastavi na ručno spajanje
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// KORAK 4: Ručno spajanje nepovezanih sistemskih tura
if ($step === 'manual_match') {
    $autoMatches = $_SESSION['auto_matches'] ?? [];
    $excelTours = $_SESSION['excel_tours'] ?? [];
    $systemTours = $_SESSION['system_tours'] ?? [];
    $usedSystemTours = $_SESSION['used_system_tours'] ?? [];
    $updatedCount = $_SESSION['updated_count'] ?? 0;
    
    // Pronađi nepovezane sistemske ture
    $unmatchedSystemTours = [];
    foreach ($systemTours as $systemTour) {
        if (!in_array($systemTour['id'], $usedSystemTours)) {
            $unmatchedSystemTours[] = $systemTour;
        }
    }
    
    // Pronađi nepovezane Excel ture
    $unmatchedExcelTours = [];
    foreach ($excelTours as $excelIndex => $excelTour) {
        $date = $excelTour['date'];
        $isMatched = isset($autoMatches[$date][$excelIndex]);
        
        if (!$isMatched) {
            $unmatchedExcelTours[] = $excelTour + ['excel_index' => $excelIndex];
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manual_matches'])) {
        $manualMatches = $_POST['manual_match'] ?? [];
        $manualUpdatedCount = 0;
        
        // Ažuriraj ručno spojene ture
        foreach ($manualMatches as $tourId => $excelIndex) {
            if (!empty($tourId) && !empty($excelIndex)) {
                $excelTour = $excelTours[$excelIndex];
                
                // Update samo km i turnover
                $upd = $pdo->prepare("UPDATE tours SET km=?, turnover=? WHERE id=?");
                $upd->execute([$excelTour['km'], $excelTour['turnover'], $tourId]);
                $manualUpdatedCount++;
                
                // Dodaj u used tours
                $usedSystemTours[] = $tourId;
            }
        }
        
        // Sačuvaj za sledeći korak
        $_SESSION['final_used_system_tours'] = $usedSystemTours;
        $_SESSION['final_unmatched_excel'] = array_filter($unmatchedExcelTours, function($tour) use ($manualMatches) {
            return !in_array($tour['excel_index'], $manualMatches);
        });
        $_SESSION['total_updated_count'] = $updatedCount + $manualUpdatedCount;
        
        header('Location: import_calculation.php?step=import_new');
        exit;
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
      <meta charset="UTF-8">
      <title>Ručno spajanje tura</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
      <style>
        .manual-matched { background-color: #fff3cd; }
      </style>
    </head>
    <body class="p-3">
      <div class="container-fluid">
        <h3 class="mb-4">KORAK 2: Ručno spajanje nepovezanih tura</h3>
        
        <div class="alert alert-success mb-4">
          <i class="fas fa-check-circle me-2"></i>
          <strong>Korak 1 završen:</strong> Automatski je ažurirano <?= $updatedCount ?> postojećih tura.
        </div>
        
        <form method="post">
          <div class="row">
            <div class="col-lg-6">
              <div class="card">
                <div class="card-header bg-warning text-dark">
                  <h5 class="mb-0">
                    <i class="fas fa-database me-2"></i>Nepovezane sistemske ture
                    <span class="badge bg-dark"><?= count($unmatchedSystemTours) ?></span>
                  </h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                  <?php if (empty($unmatchedSystemTours)): ?>
                    <p class="text-muted">Sve sistemske ture su povezane.</p>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead class="table-light sticky-top">
                          <tr>
                            <th>Datum</th>
                            <th>ID</th>
                            <th>Tovarni list</th>
                            <th>Tip isporuke</th>
                            <th>Registracija</th>
                            <th>Istovar</th>
                            <th>Spoji sa Excel</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($unmatchedSystemTours as $systemTour): ?>
                            <tr>
                              <td><?= $systemTour['tour_date'] ?></td>
                              <td><strong><?= $systemTour['id'] ?></strong></td>
                              <td><?= htmlspecialchars(extractWaybillNumber($systemTour['waybill_number']) ?? 'N/A') ?></td>
                              <td><span class="badge bg-secondary"><?= htmlspecialchars($systemTour['delivery_type'] ?? 'N/A') ?></span></td>
                              <td><span class="badge bg-primary"><?= htmlspecialchars($systemTour['vehicle_plate'] ?? 'N/A') ?></span></td>
                              <td><small><?= htmlspecialchars(substr($systemTour['unloading_loc'], 0, 25)) ?></small></td>
                              <td>
                                <select name="manual_match[<?= $systemTour['id'] ?>]" class="form-select form-select-sm">
                                  <option value="">Izaberite Excel red</option>
                                  <?php foreach ($unmatchedExcelTours as $excelTour): ?>
                                    <option value="<?= $excelTour['excel_index'] ?>">
                                      <?= $excelTour['date'] ?> - <?= htmlspecialchars($excelTour['maxir_id']) ?> - <?= htmlspecialchars(substr($excelTour['unloading_loc'], 0, 20)) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
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
            
            <div class="col-lg-6">
              <div class="card">
                <div class="card-header bg-info text-white">
                  <h5 class="mb-0">
                    <i class="fas fa-file-excel me-2"></i>Nepovezani Excel redovi
                    <span class="badge bg-light text-dark"><?= count($unmatchedExcelTours) ?></span>
                  </h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                  <?php if (empty($unmatchedExcelTours)): ?>
                    <p class="text-muted">Svi Excel redovi su povezani.</p>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead class="table-light sticky-top">
                          <tr>
                            <th>Datum</th>
                            <th>MAXIR ID</th>
                            <th>KM</th>
                            <th>Prihod</th>
                            <th>Registracija</th>
                            <th>Tip isporuke</th>
                            <th>Istovar</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($unmatchedExcelTours as $excelTour): ?>
                            <tr>
                              <td><?= $excelTour['date'] ?></td>
                              <td><strong><?= htmlspecialchars($excelTour['maxir_id']) ?></strong></td>
                              <td><?= $excelTour['km'] ?></td>
                              <td><?= number_format($excelTour['turnover'], 2) ?></td>
                              <td><?= htmlspecialchars($excelTour['vehicle_plate']) ?></td>
                              <td><span class="badge bg-secondary"><?= htmlspecialchars($excelTour['delivery_type'] ?? 'N/A') ?></span></td>
                              <td><small><?= htmlspecialchars(substr($excelTour['unloading_loc'], 0, 25)) ?></small></td>
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
          
          <div class="d-flex justify-content-end mt-4">
            <button type="submit" name="save_manual_matches" class="btn btn-warning btn-lg">
              <i class="fas fa-save me-2"></i>Sačuvaj i nastavi na uvoz novih tura
            </button>
          </div>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// KORAK 5: Import nepovezanih Excel redova kao VANREDNE ture
if ($step === 'import_new') {
    $finalUnmatchedExcel = $_SESSION['final_unmatched_excel'] ?? [];
    $totalUpdatedCount = $_SESSION['total_updated_count'] ?? 0;
    
    // Dobij vozače i vozila za dropdown
    $drivers = $pdo->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $vehicles = $pdo->query("SELECT id, plate FROM vehicles ORDER BY plate")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_new_tours'])) {
        $newToursCount = 0;
        
        foreach ($_POST['new_tour'] as $index => $tourData) {
            if (empty($tourData['driver_id']) || empty($tourData['vehicle_id'])) continue;
            
            $unmatchedTour = $finalUnmatchedExcel[$index];
            
            // Insert nova VANREDNA tura
            $stmt = $pdo->prepare("
                INSERT INTO tours (date, driver_id, vehicle_id, loading_time, loading_loc, unloading_loc, 
                                 km, turnover, note, delivery_type, ors_id, route)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $loadingTime = $unmatchedTour['date'] . ' ' . ($tourData['loading_time'] ?? '08:00:00');
            
            $stmt->execute([
                $unmatchedTour['date'],
                $tourData['driver_id'],
                $tourData['vehicle_id'],
                $loadingTime,
                'VANREDNA TURA - Import',
                $unmatchedTour['unloading_loc'],
                $unmatchedTour['km'],
                $unmatchedTour['turnover'],
                'VANREDNA TURA - Uveženo iz Excel-a - MAXIR ID: ' . $unmatchedTour['maxir_id'],
                'VANREDNA',
                $unmatchedTour['maxir_id'],
                '' // Prazna vrednost za route polje
            ]);
            $newToursCount++;
        }
        
        // Očisti sesiju
        unset($_SESSION['import_file'], $_SESSION['import_map'], $_SESSION['auto_matches'], 
              $_SESSION['excel_tours'], $_SESSION['system_tours'], $_SESSION['used_system_tours'],
              $_SESSION['final_used_system_tours'], $_SESSION['final_unmatched_excel'], 
              $_SESSION['updated_count'], $_SESSION['total_updated_count']);
        
        $success = "Import završen! Ažurirano $totalUpdatedCount postojećih tura i dodano $newToursCount novih VANREDNIH tura.";
    }
    
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
      <meta charset="UTF-8">
      <title>Import novih tura</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="p-3">
      <div class="container">
        <h3 class="mb-4">KORAK 3: Import nepovezanih Excel redova kao VANREDNE ture</h3>
        
        <div class="alert alert-success mb-4">
          <i class="fas fa-check-circle me-2"></i>
          <strong>Koraci 1 i 2 završeni:</strong> Ukupno je ažurirano <?= $totalUpdatedCount ?> postojećih tura.
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-primary">
          <i class="fas fa-info-circle me-2"></i><?= $success ?>
          <div class="mt-3">
            <a href="import_calculation.php" class="btn btn-primary me-2">
              <i class="fas fa-plus me-2"></i>Novi import
            </a>
            <a href="tours.php" class="btn btn-outline-primary">
              <i class="fas fa-list me-2"></i>Pregled tura
            </a>
          </div>
        </div>
        <?php elseif (!empty($finalUnmatchedExcel)): ?>
        <div class="card">
          <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
              <i class="fas fa-exclamation-triangle me-2"></i>
              Nepovezani Excel redovi - Import kao VANREDNE ture
              <span class="badge bg-dark"><?= count($finalUnmatchedExcel) ?></span>
            </h5>
          </div>
          <div class="card-body">
            <div class="alert alert-info">
              <strong>NAPOMENA:</strong> Ove ture će biti označene kao <strong style="color: orange;">VANREDNE</strong> i biće vizuelno istaknute u pregledu tura.
            </div>
            
            <form method="post">
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Datum</th>
                      <th>MAXIR ID</th>
                      <th>KM</th>
                      <th>Prihod</th>
                      <th>Registracija (Excel)</th>
                      <th>Tip isporuke</th>
                      <th>Istovar</th>
                      <th>Dodeli vozača</th>
                      <th>Dodeli vozilo</th>
                      <th>Vreme utovara</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($finalUnmatchedExcel as $index => $tour): ?>
                    <tr>
                      <td><?= $tour['date'] ?></td>
                      <td><strong><?= htmlspecialchars($tour['maxir_id']) ?></strong></td>
                      <td><?= $tour['km'] ?></td>
                      <td><?= number_format($tour['turnover'], 2) ?></td>
                      <td><span class="badge bg-secondary"><?= htmlspecialchars($tour['vehicle_plate']) ?></span></td>
                      <td><span class="badge bg-warning text-dark">VANREDNA</span></td>
                      <td><small><?= htmlspecialchars($tour['unloading_loc']) ?></small></td>
                      <td>
                        <select name="new_tour[<?= $index ?>][driver_id]" class="form-select form-select-sm">
                          <option value="">Izaberite vozača</option>
                          <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <select name="new_tour[<?= $index ?>][vehicle_id]" class="form-select form-select-sm">
                          <option value="">Izaberite vozilo</option>
                          <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['plate']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <input type="time" name="new_tour[<?= $index ?>][loading_time]" class="form-control form-control-sm" value="08:00">
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              
              <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                  <small>Izaberite vozača i vozilo za ture koje želite da uvezete kao VANREDNE ture.</small>
                </div>
                <button type="submit" name="import_new_tours" class="btn btn-warning btn-lg">
                  <i class="fas fa-plus me-2"></i>Uvezi kao VANREDNE ture
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          <strong>Nema nepovezanih Excel redova.</strong> Svi redovi su uspešno povezani sa postojećim turama.
          <div class="mt-3">
            <a href="import_calculation.php" class="btn btn-primary me-2">
              <i class="fas fa-plus me-2"></i>Novi import
            </a>
            <a href="tours.php" class="btn btn-outline-primary">
              <i class="fas fa-list me-2"></i>Pregled tura
            </a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </body>
    </html>
    <?php
    exit;
}
?>
