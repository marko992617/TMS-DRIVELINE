<?php
// maintenance.php - Vehicle maintenance records with edit, delete, reporting, and chart
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require 'config.php';
require 'functions.php';

date_default_timezone_set('Europe/Belgrade');

// Check authentication before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Handle delete intervention
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare('DELETE FROM maintenance WHERE id = ?')->execute([$id]);
    header('Location: maintenance.php'); exit;
}

// Handle edit initiation
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare('SELECT * FROM maintenance WHERE id = ?');
    $stmt->execute([$editId]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle update intervention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maint'])) {
    $id = intval($_POST['id']);
    $vehicle_id = intval($_POST['vehicle_id']);
    $service_date = $_POST['service_date'];
    $intervention = $_POST['intervention'];
    $mileage = intval($_POST['mileage']);
    $note = $_POST['note'];
    $labor_cost = floatval(str_replace(',', '.', $_POST['labor_cost']));
    $parts_cost = floatval(str_replace(',', '.', $_POST['parts_cost']));
    $labor_payment_method = $_POST['labor_payment_method'];
    $parts_payment_method = $_POST['parts_payment_method'];

    $pdo->prepare('UPDATE maintenance SET vehicle_id=?, service_date=?, intervention=?, mileage=?, note=?, labor_cost=?, parts_cost=?, labor_payment_method=?, parts_payment_method=? WHERE id=?')
        ->execute([$vehicle_id, $service_date, $intervention, $mileage, $note, $labor_cost, $parts_cost, $labor_payment_method, $parts_payment_method, $id]);
    header('Location: maintenance.php'); exit;
}

// Handle new intervention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maint'])) {
    $vehicle_id = intval($_POST['vehicle_id']);
    $service_date = $_POST['service_date'];
    $intervention = $_POST['intervention'];
    $mileage = intval($_POST['mileage']);
    $note = $_POST['note'];
    $labor_cost = floatval(str_replace(',', '.', $_POST['labor_cost']));
    $parts_cost = floatval(str_replace(',', '.', $_POST['parts_cost']));
    $labor_payment_method = $_POST['labor_payment_method'];
    $parts_payment_method = $_POST['parts_payment_method'];

    $pdo->prepare('INSERT INTO maintenance (vehicle_id, service_date, intervention, mileage, note, labor_cost, parts_cost, labor_payment_method, parts_payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$vehicle_id, $service_date, $intervention, $mileage, $note, $labor_cost, $parts_cost, $labor_payment_method, $parts_payment_method]);
    header('Location: maintenance.php'); exit;
}

// Fetch vehicles
$vehicles = $pdo->query('SELECT id, plate FROM vehicles ORDER BY plate')->fetchAll(PDO::FETCH_ASSOC);

// Reporting filters
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$filterVehicle = $_GET['vehicle'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query for report
$sql = 'SELECT m.*, v.plate FROM maintenance m JOIN vehicles v ON m.vehicle_id=v.id WHERE 1';
$params = [];

if ($from) { 
    $sql .= ' AND service_date >= ?'; 
    $params[] = $from; 
}
if ($to) { 
    $sql .= ' AND service_date <= ?'; 
    $params[] = $to; 
}
if ($filterVehicle !== 'all') { 
    $sql .= ' AND vehicle_id = ?'; 
    $params[] = intval($filterVehicle); 
}

// Add search functionality
if (!empty($searchQuery)) {
    $sql .= ' AND (
        v.plate LIKE ? OR
        m.intervention LIKE ? OR
        m.note LIKE ? OR
        m.labor_payment_method LIKE ? OR
        m.parts_payment_method LIKE ?
    )';
    $searchParam = "%{$searchQuery}%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchParam;
    }
}

$sql .= ' ORDER BY service_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals by vehicle
$totalsByVehicle = [];
foreach ($history as $h) {
    $vehicleId = $h['vehicle_id'];
    if (!isset($totalsByVehicle[$vehicleId])) {
        $totalsByVehicle[$vehicleId] = [
            'plate' => $h['plate'],
            'total_labor' => 0,
            'total_parts' => 0,
            'total_cost' => 0,
            'count' => 0
        ];
    }
    $totalsByVehicle[$vehicleId]['total_labor'] += $h['labor_cost'];
    $totalsByVehicle[$vehicleId]['total_parts'] += $h['parts_cost'];
    $totalsByVehicle[$vehicleId]['total_cost'] += ($h['labor_cost'] + $h['parts_cost']);
    $totalsByVehicle[$vehicleId]['count']++;
}

// Export to Excel
if (isset($_GET['export']) && $_GET['export']=='excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $sheet->fromArray(['ID','Vozilo','Datum','Intervencija','Kilometraža','Napomena','Radovi (RSD)','Način plaćanja radova','Delovi (RSD)','Način plaćanja delova','Ukupno (RSD)'], NULL, 'A1');

    $row = 2;
    $totalLabor = 0;
    $totalParts = 0;
    foreach ($history as $h) {
        $total = $h['labor_cost'] + $h['parts_cost'];
        $totalLabor += $h['labor_cost'];
        $totalParts += $h['parts_cost'];

        $sheet->fromArray([
            $h['id'],
            $h['plate'],
            $h['service_date'],
            $h['intervention'],
            $h['mileage'],
            $h['note'],
            number_format($h['labor_cost'], 2, ',', ''),
            $h['labor_payment_method'] ?? '',
            number_format($h['parts_cost'], 2, ',', ''),
            $h['parts_payment_method'] ?? '',
            number_format($total, 2, ',', '')
        ], NULL, "A$row");
        $row++;
    }

    // Add totals row
    $row++;
    $sheet->fromArray(['','','','','','UKUPNO:',
        number_format($totalLabor, 2, ',', ''),
        number_format($totalParts, 2, ',', ''),
        number_format($totalLabor + $totalParts, 2, ',', ''),
        ''
    ], NULL, "A$row");

    // Add summary by vehicle
    $row += 3;
    $sheet->fromArray(['SUMARNO PO VOZILIMA'], NULL, "A$row");
    $row++;
    $sheet->fromArray(['Vozilo','Broj intervencija','Radovi (RSD)','Delovi (RSD)','Ukupno (RSD)'], NULL, "A$row");
    $row++;

    foreach ($totalsByVehicle as $summary) {
        $sheet->fromArray([
            $summary['plate'],
            $summary['count'],
            number_format($summary['total_labor'], 2, ',', ''),
            number_format($summary['total_parts'], 2, ',', ''),
            number_format($summary['total_cost'], 2, ',', '')
        ], NULL, "A$row");
        $row++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="maintenance_report.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output'); 
    exit;
}

// Export PDF - MUST be before any HTML output
if (isset($_GET['export']) && $_GET['export']=='pdf') {
    try {
        // Proveri da li Dompdf klasa postoji
        if (!class_exists('Dompdf\Dompdf')) {
            die('Dompdf nije instaliran. Pokušajte ponovo ili kontaktirajte administratora.');
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 15px; font-size: 11px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company { font-size: 14px; font-weight: bold; }
        .address { font-size: 10px; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; font-size: 9px; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f5f5f5; }
        .summary-section { margin-top: 20px; }
        .summary-title { font-size: 12px; font-weight: bold; margin-bottom: 8px; }
        </style></head><body>';

        $html .= '<div class="header">
            <div class="company">VAŠ POTRČKO DOO BEOGRAD</div>
            <div class="address">Aleksandra Stamboliškog 6A</div>
            <div class="address">PIB 107418055 | MB 20798513</div>
        </div>';

        $html .= '<div class="title">IZVEŠTAJ ODRŽAVANJA VOZILA</div>';

        if ($from || $to || $filterVehicle !== 'all' || $searchQuery) {
            $html .= '<div style="margin-bottom: 10px; font-size: 10px;"><strong>Filteri:</strong> ';
            if ($from) $html .= 'Od: ' . htmlspecialchars($from) . ' ';
            if ($to) $html .= 'Do: ' . htmlspecialchars($to) . ' ';
            if ($filterVehicle !== 'all') {
                $vehicleName = '';
                foreach ($vehicles as $v) {
                    if ($v['id'] == $filterVehicle) {
                        $vehicleName = $v['plate'];
                        break;
                    }
                }
                $html .= 'Vozilo: ' . htmlspecialchars($vehicleName) . ' ';
            }
            if ($searchQuery) $html .= 'Pretraga: ' . htmlspecialchars($searchQuery);
            $html .= '</div>';
        }

        $html .= '<table>
        <thead>
            <tr>
                <th>Vozilo</th>
                <th>Datum</th>
                <th>Intervencija</th>
                <th>Km</th>
                <th>Napomena</th>
                <th>Radovi</th>
                <th>Način plaćanja radova</th>
                <th>Delovi</th>
                <th>Način plaćanja delova</th>
                <th>Ukupno</th>
            </tr>
        </thead>
        <tbody>';

        $totalLabor = 0;
        $totalParts = 0;

        foreach ($history as $h) {
            $total = $h['labor_cost'] + $h['parts_cost'];
            $totalLabor += $h['labor_cost'];
            $totalParts += $h['parts_cost'];

            $html .= '<tr>
                <td>' . htmlspecialchars($h['plate'] ?? '') . '</td>
                <td>' . htmlspecialchars($h['service_date'] ?? '') . '</td>
                <td>' . htmlspecialchars($h['intervention'] ?? '') . '</td>
                <td>' . htmlspecialchars($h['mileage'] ?? '') . '</td>
                <td>' . htmlspecialchars($h['note'] ?? '') . '</td>
                <td>' . number_format($h['labor_cost'], 2, ',', '') . '</td>
                <td>' . htmlspecialchars($h['labor_payment_method'] ?? '') . '</td>
                <td>' . number_format($h['parts_cost'], 2, ',', '') . '</td>
                <td>' . htmlspecialchars($h['parts_payment_method'] ?? '') . '</td>
                <td>' . number_format($total, 2, ',', '') . '</td>
            </tr>';
        }

        $html .= '<tr class="total-row">
            <td colspan="5"><strong>UKUPNO:</strong></td>
            <td><strong>' . number_format($totalLabor, 2, ',', '') . '</strong></td>
            <td></td>
            <td><strong>' . number_format($totalParts, 2, ',', '') . '</strong></td>
            <td></td>
            <td><strong>' . number_format($totalLabor + $totalParts, 2, ',', '') . '</strong></td>
        </tr>';

        $html .= '</tbody></table>';

        // Summary by vehicle
        if (!empty($totalsByVehicle)) {
            $html .= '<div class="summary-section">
                <div class="summary-title">SUMARNO PO VOZILIMA</div>
                <table>
                    <thead>
                        <tr>
                            <th>Vozilo</th>
                            <th>Broj intervencija</th>
                            <th>Radovi (RSD)</th>
                            <th>Delovi (RSD)</th>
                            <th>Ukupno (RSD)</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($totalsByVehicle as $summary) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($summary['plate'] ?? '') . '</td>
                    <td>' . $summary['count'] . '</td>
                    <td>' . number_format($summary['total_labor'], 2, ',', '') . '</td>
                    <td>' . number_format($summary['total_parts'], 2, ',', '') . '</td>
                    <td>' . number_format($summary['total_cost'], 2, ',', '') . '</td>
                </tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '<div style="margin-top: 20px; font-size: 8px;">
            Datum štampe: ' . date('d.m.Y H:i') . '
        </div>';

        $html .= '</body></html>';

        // Kreiraj Dompdf objekat sa opcijama
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Očisti sve output buffere
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Postavi headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="maintenance_report.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $dompdf->output();
        exit;

    } catch (Exception $e) {
        die('Greška pri kreiranju PDF-a: ' . $e->getMessage());
    }
}

// All redirects and exports complete - now safe to output HTML
require 'header.php';
?>
<div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
  <h3>Održavanje vozila</h3>

  <!-- Filters and Search -->
  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-2">
      <label class="form-label">Datum od</label>
      <input type="date" name="from" value="<?=htmlspecialchars($from)?>" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Datum do</label>
      <input type="date" name="to" value="<?=htmlspecialchars($to)?>" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Vozilo</label>
      <select name="vehicle" class="form-select">
        <option value="all">Sva vozila</option>
        <?php foreach($vehicles as $v): ?>
        <option value="<?=$v['id']?>" <?=($filterVehicle==$v['id'])?'selected':''?>><?=htmlspecialchars($v['plate'])?></option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Pretraga</label>
      <input type="text" name="search" value="<?=htmlspecialchars($searchQuery)?>" class="form-control" placeholder="Vozilo, intervencija, napomena...">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary me-2" type="submit">Prikaži</button>
      <a href="?<?=http_build_query(array_merge($_GET,['export'=>'excel']))?>" class="btn btn-outline-success me-2">Excel</a>
      <a href="?<?=http_build_query(array_merge($_GET,['export'=>'pdf']))?>" class="btn btn-outline-danger me-2">PDF</a>
      <a href="maintenance.php" class="btn btn-outline-secondary">Reset</a>
    </div>
  </form>

  <!-- Add/Edit Form - Redesigned Inline Layout -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3"><?=isset($editRecord)?'Izmeni':'Dodaj'?> intervenciju</h5>
      <form method="POST">
        <?php if(isset($editRecord)): ?>
        <input type="hidden" name="update_maint" value="1">
        <input type="hidden" name="id" value="<?=$editRecord['id']?>">
        <?php else: ?>
        <input type="hidden" name="add_maint" value="1">
        <?php endif; ?>

        <div class="row g-2 mb-2">
          <div class="col-auto" style="width: 150px;">
            <label class="form-label small mb-1">Vozilo *</label>
            <select name="vehicle_id" class="form-select form-select-sm" required>
              <option value="">Izaberi</option>
              <?php foreach($vehicles as $v): ?>
              <option value="<?=$v['id']?>" <?=isset($editRecord)&&$editRecord['vehicle_id']==$v['id']?'selected':''?>><?=htmlspecialchars($v['plate'])?></option>
              <?php endforeach;?>
            </select>
          </div>

          <div class="col-auto" style="width: 140px;">
            <label class="form-label small mb-1">Datum *</label>
            <input name="service_date" type="date" class="form-control form-control-sm" value="<?=$editRecord['service_date']??date('Y-m-d')?>" required>
          </div>

          <div class="col-auto" style="width: 100px;">
            <label class="form-label small mb-1">Kilometraža</label>
            <input name="mileage" type="number" class="form-control form-control-sm" placeholder="0" value="<?=$editRecord['mileage']??''?>">
          </div>

          <div class="col">
            <label class="form-label small mb-1">Intervencija *</label>
            <input name="intervention" type="text" class="form-control form-control-sm" placeholder="Opis intervencije" value="<?=$editRecord['intervention']??''?>" required>
          </div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-auto" style="width: 120px;">
            <label class="form-label small mb-1">Radovi (RSD)</label>
            <input name="labor_cost" type="text" class="form-control form-control-sm" placeholder="0.00" value="<?=$editRecord['labor_cost']??''?>">
          </div>

          <div class="col-auto" style="width: 120px;">
            <label class="form-label small mb-1">Plaćanje</label>
            <select name="labor_payment_method" class="form-select form-select-sm">
              <?php foreach(['Keš','Virman'] as $m): ?>
              <option value="<?=$m?>" <?=isset($editRecord)&&$editRecord['labor_payment_method']==$m?'selected':''?>><?=$m?></option>
              <?php endforeach;?>
            </select>
          </div>

          <div class="col-auto" style="width: 120px;">
            <label class="form-label small mb-1">Delovi (RSD)</label>
            <input name="parts_cost" type="text" class="form-control form-control-sm" placeholder="0.00" value="<?=$editRecord['parts_cost']??''?>">
          </div>

          <div class="col-auto" style="width: 120px;">
            <label class="form-label small mb-1">Plaćanje</label>
            <select name="parts_payment_method" class="form-select form-select-sm">
              <?php foreach(['Keš','Virman'] as $m): ?>
              <option value="<?=$m?>" <?=isset($editRecord)&&$editRecord['parts_payment_method']==$m?'selected':''?>><?=$m?></option>
              <?php endforeach;?>
            </select>
          </div>

          <div class="col">
            <label class="form-label small mb-1">Napomena</label>
            <input name="note" type="text" class="form-control form-control-sm" placeholder="Dodatne napomene..." value="<?=$editRecord['note']??''?>">
          </div>

          <div class="col-auto d-flex align-items-end">
            <button class="btn btn-<?=isset($editRecord)?'warning':'success'?> btn-sm"><?=isset($editRecord)?'Sačuvaj':'Dodaj'?></button>
            <?php if(isset($editRecord)): ?><a href="maintenance.php" class="btn btn-secondary btn-sm ms-1">Otkaži</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Summary Cards -->
  <?php if (!empty($totalsByVehicle)): ?>
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Ukupno intervencija</h5>
          <h3 class="text-primary"><?= count($history) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Ukupno radovi</h5>
          <h3 class="text-success"><?= number_format(array_sum(array_column($history, 'labor_cost')), 2, ',', '.') ?> RSD</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Ukupno delovi</h5>
          <h3 class="text-warning"><?= number_format(array_sum(array_column($history, 'parts_cost')), 2, ',', '.') ?> RSD</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Ukupno troškovi</h5>
          <h3 class="text-danger"><?= number_format(array_sum(array_column($history, 'labor_cost')) + array_sum(array_column($history, 'parts_cost')), 2, ',', '.') ?> RSD</h3>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- History Table -->
  <div class="table-responsive">
    <table class="table table-striped mb-4">
      <thead>
        <tr>
          <th>Vozilo</th>
          <th>Datum</th>
          <th>Intervencija</th>
          <th>Km</th>
          <th>Napomena</th>
          <th>Radovi</th>
          <th>Način plaćanja radova</th>
          <th>Delovi</th>
          <th>Način plaćanja delova</th>
          <th>Ukupno</th>
          <th>Akcije</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="11" class="text-center">Nema podataka za prikaz.</td></tr>
        <?php else: ?>
          <?php foreach($history as $h): ?>
          <tr>
            <td><?=htmlspecialchars($h['plate'] ?? '')?></td>
            <td><?=htmlspecialchars($h['service_date'] ?? '')?></td>
            <td><?=htmlspecialchars($h['intervention'] ?? '')?></td>
            <td><?=htmlspecialchars($h['mileage'] ?? '')?></td>
            <td><?=htmlspecialchars($h['note'] ?? '')?></td>
            <td><?=number_format($h['labor_cost'],2,',','.')?> RSD</td>
            <td><span class="badge bg-<?=($h['labor_payment_method']??'')=='Keš'?'success':'primary'?>"><?=htmlspecialchars($h['labor_payment_method'] ?? '')?></span></td>
            <td><?=number_format($h['parts_cost'],2,',','.')?> RSD</td>
            <td><span class="badge bg-<?=($h['parts_payment_method']??'')=='Keš'?'success':'primary'?>"><?=htmlspecialchars($h['parts_payment_method'] ?? '')?></span></td>
            <td><strong><?=number_format($h['labor_cost']+$h['parts_cost'],2,',','.')?> RSD</strong></td>
            <td>
              <a href="?edit=<?=$h['id']?>" class="btn btn-sm btn-warning">Izmeni</a>
              <a href="?delete=<?=$h['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Da li ste sigurni da želite da obrišete ovu intervenciju?');">Obriši</a>
            </td>
          </tr>
          <?php endforeach;?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Summary by Vehicle -->
  <?php if (!empty($totalsByVehicle)): ?>
  <h5>Sumarno po vozilima</h5>
  <div class="table-responsive">
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Vozilo</th>
          <th>Broj intervencija</th>
          <th>Radovi (RSD)</th>
          <th>Delovi (RSD)</th>
          <th>Ukupno (RSD)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($totalsByVehicle as $summary): ?>
        <tr>
          <td><strong><?=htmlspecialchars($summary['plate'] ?? '')?></strong></td>
          <td><?=$summary['count']?></td>
          <td><?=number_format($summary['total_labor'],2,',','.')?></td>
          <td><?=number_format($summary['total_parts'],2,',','.')?></td>
          <td><strong><?=number_format($summary['total_cost'],2,',','.')?></strong></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Chart -->
  <h5>Ukupni troškovi održavanja po vozilu</h5>
  <canvas id="chartMaintenance" width="400" height="200"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const ctx = document.getElementById('chartMaintenance').getContext('2d');
  const data = {
    labels: <?=json_encode(array_column($vehicles,'plate'))?>,
    datasets: [{
      label: 'Ukupno (RSD)',
      data: <?=json_encode(array_map(function($v){ global $pdo; $stmt=$pdo->prepare('SELECT SUM(labor_cost+parts_cost) FROM maintenance WHERE vehicle_id=?'); $stmt->execute([$v['id']]); return floatval($stmt->fetchColumn()); }, $vehicles))?>,
      backgroundColor: 'rgba(54, 162, 235, 0.5)',
      borderColor: 'rgba(54, 162, 235, 1)',
      borderWidth: 1
    }]
  };
  new Chart(ctx, { 
    type: 'bar', 
    data: data,
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return value.toLocaleString('sr-RS') + ' RSD';
            }
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': ' + context.parsed.y.toLocaleString('sr-RS') + ' RSD';
            }
          }
        }
      }
    }
  });
</script>

<?php require 'footer.php'; ?>