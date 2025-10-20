<?php
// report_finance.php - Kompletan finansijski izveštaj
require 'authorize.php';
require 'config.php';

// Fetch filters
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$vehicle_id = $_GET['vehicle_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$month = $_GET['month'] ?? '';
// Removed km_type from GET parameters - now only used in POST for updates

// Handle cost update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_costs'])) {
    $update_km_type = $_POST['km_type'] ?? 'driver';

    // Build the same query as for display to get tours that need updating
    $update_params = [];
    $update_sql = "SELECT t.id, t.km as tour_km, t.vehicle_id, t.estimated_km,
                          ds.end_km - ds.start_km as driver_km,
                          v.fuel_consumption, v.depreciation_per_km
                   FROM tours t
                   LEFT JOIN vehicles v ON t.vehicle_id=v.id
                   LEFT JOIN driver_submissions ds ON ds.tour_id=t.id
                   WHERE 1=1";

    if ($_POST['date_from'] ?? '') {
        $update_sql .= " AND DATE(t.loading_time) >= ?";
        $update_params[] = $_POST['date_from'];
    }
    if ($_POST['date_to'] ?? '') {
        $update_sql .= " AND DATE(t.loading_time) <= ?";
        $update_params[] = $_POST['date_to'];
    }
    if ($_POST['vehicle_id'] ?? '') {
        $update_sql .= " AND t.vehicle_id = ?";
        $update_params[] = $_POST['vehicle_id'];
    }
    if ($_POST['driver_id'] ?? '') {
        $update_sql .= " AND t.driver_id = ?";
        $update_params[] = $_POST['driver_id'];
    }
    if ($_POST['client_id'] ?? '') {
        $update_sql .= " AND t.client_id = ?";
        $update_params[] = $_POST['client_id'];
    }
    if ($_POST['month'] ?? '') {
        $update_sql .= " AND DATE_FORMAT(t.loading_time, '%Y-%m') = ?";
        $update_params[] = $_POST['month'];
    }

    // Get current fuel price from settings
    $stmtSetting = $pdo->prepare("SELECT `value` FROM settings WHERE `name` = ? LIMIT 1");
    $stmtSetting->execute(['fuel_price']);
    $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
    $currentFuelPrice = $rowSetting['value'] ?? 0;

    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute($update_params);
    $tours_to_update = $update_stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated_count = 0;
    foreach ($tours_to_update as $tour) {
        $tourKm = floatval($tour['tour_km']);
        $driverKm = floatval($tour['driver_km'] ?? 0);

        // Choose KM type based on user selection
        $kmForCosts = ($update_km_type == 'tour') ? $tourKm : $driverKm;

        // Calculate new costs
        $newFuelCost = round($kmForCosts * (float)$tour['fuel_consumption'] / 100 * $currentFuelPrice);
        $newAmortCost = round($kmForCosts * (float)$tour['depreciation_per_km']);

        // Update the tour
        $updateTourStmt = $pdo->prepare("UPDATE tours SET fuel_cost = ?, amortization = ? WHERE id = ?");
        $updateTourStmt->execute([$newFuelCost, $newAmortCost, $tour['id']]);
        $updated_count++;
    }

    // Redirect with success message
    $redirect_params = $_POST;
    unset($redirect_params['update_costs']);
    $redirect_params['updated'] = $updated_count;
    $redirect_params['km_type_used'] = $update_km_type;
    unset($redirect_params['km_type']); // Remove km_type from redirect
    header('Location: report_finance.php?' . http_build_query($redirect_params));
    exit;
}

require 'header.php';

// Fetch fuel price from settings
$stmtSetting = $pdo->prepare("SELECT `value` FROM settings WHERE `name` = ? LIMIT 1");
$stmtSetting->execute(['fuel_price']);
$rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
$fuelPrice = $rowSetting['value'] ?? 0;

// Fetch all vehicles for filter dropdown
$vehiclesStmt = $pdo->query("SELECT id, plate FROM vehicles ORDER BY plate");
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all drivers for filter dropdown
$driversStmt = $pdo->query("SELECT id, name FROM drivers ORDER BY name");
$drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all clients for filter dropdown
$clientsStmt = $pdo->query("SELECT id, name FROM clients ORDER BY name");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build query for tours with driver submissions
$params = [];
$sql = "SELECT t.id, t.loading_time,
               DATE(t.loading_time) AS date,
               TIME(t.loading_time) AS time,
               t.ors_id, t.delivery_type, t.turnover, t.km as tour_km,
               t.allowance, t.fuel_cost, t.amortization, t.estimated_km,
               d.name AS driver_name,
               v.plate AS vehicle_plate,
               c.name AS client_name,
               v.fuel_consumption, v.depreciation_per_km,
               ds.waybill_number,
               ds.start_km,
               ds.end_km,
               (ds.end_km - ds.start_km) as driver_km
        FROM tours t
        LEFT JOIN drivers d ON t.driver_id=d.id
        LEFT JOIN vehicles v ON t.vehicle_id=v.id
        LEFT JOIN clients c ON t.client_id=c.id
        LEFT JOIN driver_submissions ds ON ds.tour_id=t.id
        WHERE 1=1";

if ($date_from) {
    $sql .= " AND DATE(t.loading_time) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(t.loading_time) <= ?";
    $params[] = $date_to;
}
if ($vehicle_id) {
    $sql .= " AND t.vehicle_id = ?";
    $params[] = $vehicle_id;
}
if ($driver_id) {
    $sql .= " AND t.driver_id = ?";
    $params[] = $driver_id;
}
if ($client_id) {
    $sql .= " AND t.client_id = ?";
    $params[] = $client_id;
}
if ($month) {
    $sql .= " AND DATE_FORMAT(t.loading_time, '%Y-%m') = ?";
    $params[] = $month;
}

$sql .= " ORDER BY t.loading_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalTourKm = 0;
$totalDriverKm = 0;
$totalEstimatedKm = 0;
$totalTurnover = 0;
$totalFuel = 0;
$totalAmort = 0;
$totalAllowance = 0;
$totalContributions = 0;
$totalNet = 0;
?>

<style>
.filter-card { 
  background: white; 
  border: 1px solid var(--border-color);
  padding: 1.5rem; 
  border-radius: 0.75rem; 
  margin-bottom: 1.5rem; 
}
.table-modern th { 
  background-color: var(--secondary-color); 
  color: var(--text-primary);
  font-weight: 600;
}
.export-buttons { 
  margin-top: 1rem; 
}
.btn-export {
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  margin-right: 0.5rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}
.btn-export:hover {
  background: var(--hover-bg);
  text-decoration: none;
}
.btn-export i {
  margin-right: 0.5rem;
}
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin: 0;
  width: 100%;
}
.table-modern {
  border-collapse: separate;
  border-spacing: 0;
  width: 100%;
  table-layout: fixed;
}
.table-modern th,
.table-modern td {
  white-space: nowrap;
  padding: 6px 8px;
  font-size: 0.8rem;
  overflow: hidden;
  text-overflow: ellipsis;
}
.card-modern {
  margin: 0;
  border-radius: 0;
}
.container-fluid {
  max-width: 100%;
  padding-left: 10px;
  padding-right: 10px;
}

/* Dodati CSS stilovi za novi dizajn vizuelnog pregleda */
.badge-modern {
  background: var(--primary-color);
  color: white;
  border-radius: 0.375rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.earnings-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2rem;
  border-radius: 1rem;
  margin-bottom: 2rem;
  text-align: center;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.earnings-header h4 {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.summary-card {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 5px 15px rgba(0,0,0,0.08);
  border: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  align-items: center;
}

.summary-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.summary-card.bg-success {
  background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;
}

.summary-card.bg-danger {
  background: linear-gradient(135deg, #ff6b6b 0%, #ffa726 100%) !important;
}

.summary-card.bg-warning {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
}

.summary-card.bg-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.summary-icon {
  font-size: 2.5rem;
  margin-right: 1rem;
  opacity: 0.8;
}

.summary-content h3 {
  margin: 0;
  font-size: 1.8rem;
  font-weight: 700;
  color: white;
}

.summary-content p {
  margin: 0;
  font-size: 0.9rem;
  color: rgba(255,255,255,0.9);
  font-weight: 500;
}

.vehicle-earnings-table {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.vehicle-earnings-table h5 {
  color: #2c3e50;
  margin-bottom: 1.5rem;
  font-weight: 600;
}

.vehicle-row:hover {
  background-color: #f8f9fa;
  transform: scale(1.01);
  transition: all 0.2s ease;
}

.table th {
  border-top: none;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 1rem 0.75rem;
}

.table td {
  padding: 0.75rem;
  vertical-align: middle;
  font-size: 0.9rem;
}
</style>

<div class="page-header">
    <div class="container-fluid px-4">
      <h1 class="page-title">Finansijski Izveštaj</h1>
      <p class="page-subtitle">Detaljni pregled prihoda i troškova</p>
    </div>
  </div>

  <div class="container-fluid px-2">
    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i>
        Uspešno ažurirani troškovi za <?= intval($_GET['updated']) ?> tura na osnovu <?= ($_GET['km_type_used'] == 'driver') ? 'vozačke' : 'obračunske' ?> kilometraže.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="filter-card">
      <form method="get" class="row g-3">
      <div class="col-md-2">
        <label class="form-label">Od datuma</label>
        <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($date_from)?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Do datuma</label>
        <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($date_to)?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Mesec</label>
        <input type="month" name="month" class="form-control" value="<?=htmlspecialchars($month)?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Vozilo</label>
        <select name="vehicle_id" class="form-select">
          <option value="">Sva vozila</option>
          <?php foreach($vehicles as $v): ?>
            <option value="<?=$v['id']?>" <?=$vehicle_id == $v['id'] ? 'selected' : ''?>>
              <?=htmlspecialchars($v['plate'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Vozač</label>
        <select name="driver_id" class="form-select">
          <option value="">Svi vozači</option>
          <?php foreach($drivers as $dr): ?>
            <option value="<?=$dr['id']?>" <?=$driver_id == $dr['id'] ? 'selected' : ''?>>
              <?=htmlspecialchars($dr['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Klijent</label>
        <select name="client_id" class="form-select">
          <option value="">Svi klijenti</option>
          <?php foreach($clients as $cl): ?>
            <option value="<?=$cl['id']?>" <?=$client_id == $cl['id'] ? 'selected' : ''?>>
              <?=htmlspecialchars($cl['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 align-self-end">
        <button type="submit" class="btn btn-primary">Filtriraj</button>
        <a href="report_finance.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>

    <div class="export-buttons">
      <a href="export_finance_pdf.php?<?=http_build_query($_GET)?>" class="btn-export text-danger" target="_blank">
        <i class="fas fa-file-pdf"></i> Export PDF
      </a>
      <a href="export_finance_excel.php?<?=http_build_query($_GET)?>" class="btn-export text-success">
        <i class="fas fa-file-excel"></i> Export Excel
      </a>
      <?php if (!empty($tours)): ?>
      <button type="button" class="btn-export text-warning" onclick="showUpdateCostsModal()">
        <i class="fas fa-sync-alt"></i> Ažuriraj troškove
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($tours)): ?>
    <div class="alert alert-info">Nema podataka za odabrani period.</div>
  <?php else: ?>
    <div class="card-modern">
      <div class="table-responsive">
        <table class="table table-modern table-striped" style="width: 100%; min-width: 1800px;">
        <thead>
          <tr>
            <th style="width: 90px;">Datum</th>
            <th style="width: 70px;">Vreme</th>
            <th style="width: 130px;">Vozač</th>
            <th style="width: 150px;">Klijent</th>
            <th style="width: 90px;">Vozilo</th>
            <th style="width: 90px;">ORS ID</th>
            <th style="width: 100px;">Tovarni list</th>
            <th style="width: 60px;">Tip</th>
            <th style="width: 100px;">KM obračun</th>
            <th style="width: 100px;">KM vozač</th>
            <th style="width: 100px;">Proc. km</th>
            <th style="width: 110px;">Prihod (RSD)</th>
            <th style="width: 100px;">Gorivo (RSD)</th>
            <th style="width: 120px;">Amortizacija (RSD)</th>
            <th style="width: 100px;">Dnevnica (RSD)</th>
            <th style="width: 100px;">Doprinosi (RSD)</th>
            <th style="width: 100px;">Neto (RSD)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($tours as $t): 
            $tourKm = floatval($t['tour_km']); // Kilometraža iz obračuna (uvoz)
            $driverKm = floatval($t['driver_km'] ?? 0); // Kilometraža koju je vozač unео
            $estimatedKm = floatval($t['estimated_km'] ?? 0); // Procenjena kilometraža
            $turnover = floatval($t['turnover']);
            $allowance = floatval($t['allowance']);
            $contributions = 0; // Driver contributions column doesn't exist

            // Use actual costs from database if they exist, otherwise calculate using driver km
            $fuelCost = isset($t['fuel_cost']) && $t['fuel_cost'] > 0 ? 
                        floatval($t['fuel_cost']) : 
                        round($driverKm * (float)$t['fuel_consumption'] / 100 * $fuelPrice);

            $amortCost = isset($t['amortization']) && $t['amortization'] > 0 ? 
                         floatval($t['amortization']) : 
                         round($driverKm * (float)$t['depreciation_per_km']);

            // Net calculation: turnover - fuel - amortization - allowance - contributions
            $net = $turnover - $fuelCost - $amortCost - $allowance - $contributions;

            // Add to totals
            $totalTourKm += $tourKm;
            $totalDriverKm += $driverKm;
            $totalEstimatedKm += $estimatedKm;
            $totalTurnover += $turnover;
            $totalAllowance += $allowance;
            $totalContributions += $contributions;
            $totalFuel += $fuelCost;
            $totalAmort += $amortCost;
            $totalNet += $net;
        ?>
          <tr>
            <td><?=htmlspecialchars($t['date'])?></td>
            <td><?=htmlspecialchars($t['time'])?></td>
            <td><?=htmlspecialchars($t['driver_name'])?></td>
            <td><?=htmlspecialchars($t['client_name'] ?? '-')?></td>
            <td><?=htmlspecialchars($t['vehicle_plate'])?></td>
            <td><?=htmlspecialchars($t['ors_id'])?></td>
            <td><?=htmlspecialchars($t['waybill_number'] ?? '-')?></td>
            <td><?=htmlspecialchars($t['delivery_type'])?></td>
            <td class="text-end"><?=number_format($tourKm,0,',','.')?></td>
            <td class="text-end"><?=number_format($driverKm,0,',','.')?></td>
            <td class="text-end"><?=$estimatedKm > 0 ? number_format($estimatedKm,0,',','.') : '-'?></td>
            <td class="text-end"><?=number_format($turnover,2,',','.')?></td>
            <td class="text-end"><?=number_format($fuelCost,0,',','.')?></td>
            <td class="text-end"><?=number_format($amortCost,0,',','.')?></td>
            <td class="text-end"><?=number_format($allowance,2,',','.')?></td>
            <td class="text-end"><?=number_format($contributions,2,',','.')?></td>
            <td class="text-end"><?=number_format($net,2,',','.')?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
          <tr>
            <th colspan="8" class="text-end">UKUPNO:</th>
            <th class="text-end"><?=number_format($totalTourKm,0,',','.')?></th>
            <th class="text-end"><?=number_format($totalDriverKm,0,',','.')?></th>
            <th class="text-end"><?=number_format($totalEstimatedKm,0,',','.')?></th>
            <th class="text-end"><?=number_format($totalTurnover,2,',','.')?></th>
            <th class="text-end"><?=number_format($totalFuel,0,',','.')?></th>
            <th class="text-end"><?=number_format($totalAmort,0,',','.')?></th>
            <th class="text-end"><?=number_format($totalAllowance,2,',','.')?></th>
            <th class="text-end"><?=number_format($totalContributions,2,',','.')?></th>
            <th class="text-end"><?=number_format($totalNet,2,',','.')?></th>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
  <?php endif; ?>
  </div>

<!-- Modal za ažuriranje troškova -->
<div class="modal fade" id="updateCostsModal" tabindex="-1" aria-labelledby="updateCostsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateCostsModalLabel">Ažuriranje troškova goriva i amortizacije</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Izaberite tip kilometraže na osnovu koje želite da ažurirate troškove:</p>
        <form method="post" id="updateCostsForm">
          <input type="hidden" name="update_costs" value="1">
          <?php foreach($_GET as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
          <?php endforeach; ?>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="km_type" id="kmTypeDriver" value="driver" checked>
              <label class="form-check-label" for="kmTypeDriver">
                <strong>Kilometraža vozača</strong> - koristi kilometražu koju je vozač uneo (razlika između početne i završne km)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="km_type" id="kmTypeTour" value="tour">
              <label class="form-check-label" for="kmTypeTour">
                <strong>Obračunska kilometraža</strong> - koristi kilometražu iz obračuna (uvoz iz sistema)
              </label>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Odustani</button>
        <button type="button" class="btn btn-warning" onclick="submitUpdateCosts()">
          <i class="fas fa-sync-alt"></i> Ažuriraj troškove
        </button>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($tours)): ?>
<!-- Vizuelni pregled zarade po vozilima -->
<div class="card-modern mt-4">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Vizuelni pregled zarade po vozilima</h5>
  </div>
  <div class="card-body">
    <?php
    // Grupisanje podataka po vozilima
    $vehicleData = [];
    foreach($tours as $t) {
      $vehiclePlate = $t['vehicle_plate'] ?? 'Nepoznato vozilo';
      $tourKm = floatval($t['tour_km']);
      $driverKm = floatval($t['driver_km'] ?? 0);
      $turnover = floatval($t['turnover']);
      $allowance = floatval($t['allowance']);

      // Use actual costs from database if they exist, otherwise calculate using driver km
      $fuelCost = isset($t['fuel_cost']) && $t['fuel_cost'] > 0 ? 
                  floatval($t['fuel_cost']) : 
                  round($driverKm * (float)$t['fuel_consumption'] / 100 * $fuelPrice);

      $amortCost = isset($t['amortization']) && $t['amortization'] > 0 ? 
                   floatval($t['amortization']) : 
                   round($driverKm * (float)$t['depreciation_per_km']);

      $contributions = 0; // Driver contributions column doesn't exist
      $net = $turnover - $fuelCost - $amortCost - $allowance - $contributions;

      if (!isset($vehicleData[$vehiclePlate])) {
        $vehicleData[$vehiclePlate] = [
          'turnover' => 0,
          'fuel' => 0,
          'amortization' => 0,
          'allowance' => 0,
          'contributions' => 0,
          'net' => 0,
          'tours_count' => 0
        ];
      }

      $vehicleData[$vehiclePlate]['turnover'] += $turnover;
      $vehicleData[$vehiclePlate]['fuel'] += $fuelCost;
      $vehicleData[$vehiclePlate]['amortization'] += $amortCost;
      $vehicleData[$vehiclePlate]['allowance'] += $allowance;
      $vehicleData[$vehiclePlate]['contributions'] += $contributions;
      $vehicleData[$vehiclePlate]['net'] += $net;
      $vehicleData[$vehiclePlate]['tours_count']++;
    }

    // Sortiranje po neto zaradi (opadajuće)
    uasort($vehicleData, function($a, $b) {
      return $b['net'] <=> $a['net'];
    });
    ?>

    <div class="row">
      <div class="col-lg-8">
        <canvas id="earningsChart" style="max-height: 400px;"></canvas>
      </div>
      <div class="col-lg-4">
        <h6>Sumarni pregled po vozilima:</h6>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
          <table class="table table-sm table-striped">
            <thead class="table-dark sticky-top">
              <tr>
                <th>Vozilo</th>
                <th>Ture</th>
                <th>Neto (RSD)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($vehicleData as $plate => $data): ?>
              <tr>
                <td><strong><?= htmlspecialchars($plate) ?></strong></td>
                <td><?= $data['tours_count'] ?></td>
                <td class="text-end <?= $data['net'] >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= number_format($data['net'], 0, ',', '.') ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($tours)): ?>
// Podaci za grafikon
const vehicleLabels = <?= json_encode(array_keys($vehicleData)) ?>;
const turnoverData = <?= json_encode(array_column($vehicleData, 'turnover')) ?>;
const fuelData = <?= json_encode(array_column($vehicleData, 'fuel')) ?>;
const amortData = <?= json_encode(array_column($vehicleData, 'amortization')) ?>;
const allowanceData = <?= json_encode(array_column($vehicleData, 'allowance')) ?>;
const contributionsData = <?= json_encode(array_column($vehicleData, 'contributions')) ?>;
const netData = <?= json_encode(array_column($vehicleData, 'net')) ?>;

const ctx = document.getElementById('earningsChart').getContext('2d');
const earningsChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: vehicleLabels,
    datasets: [
      {
        label: 'Prihod',
        data: turnoverData,
        backgroundColor: 'rgba(40, 167, 69, 0.8)',
        borderColor: 'rgba(40, 167, 69, 1)',
        borderWidth: 1
      },
      {
        label: 'Gorivo',
        data: fuelData.map(x => -x), // Negativan za prikaz kao trošak
        backgroundColor: 'rgba(220, 53, 69, 0.8)',
        borderColor: 'rgba(220, 53, 69, 1)',
        borderWidth: 1
      },
      {
        label: 'Amortizacija',
        data: amortData.map(x => -x), // Negativan za prikaz kao trošak
        backgroundColor: 'rgba(255, 193, 7, 0.8)',
        borderColor: 'rgba(255, 193, 7, 1)',
        borderWidth: 1
      },
      {
        label: 'Dnevnica',
        data: allowanceData.map(x => -x), // Negativan za prikaz kao trošak
        backgroundColor: 'rgba(108, 117, 125, 0.8)',
        borderColor: 'rgba(108, 117, 125, 1)',
        borderWidth: 1
      },
      {
        label: 'Neto zarada',
        data: netData,
        backgroundColor: 'rgba(13, 110, 253, 0.8)',
        borderColor: 'rgba(13, 110, 253, 1)',
        borderWidth: 2,
        type: 'line',
        fill: false
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Pregled prihoda i troškova po vozilima (RSD)'
      },
      legend: {
        display: true,
        position: 'top'
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            label += new Intl.NumberFormat('sr-RS').format(Math.abs(context.parsed.y)) + ' RSD';
            return label;
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: false,
        ticks: {
          callback: function(value) {
            return new Intl.NumberFormat('sr-RS').format(value) + ' RSD';
          }
        }
      },
      x: {
        ticks: {
          maxRotation: 45,
          minRotation: 0
        }
      }
    },
    interaction: {
      mode: 'index',
      intersect: false
    }
  }
});
<?php endif; ?>

function showUpdateCostsModal() {
  const modal = new bootstrap.Modal(document.getElementById('updateCostsModal'));
  modal.show();
}

function submitUpdateCosts() {
  const selectedKmType = document.querySelector('input[name="km_type"]:checked').value;
  const kmTypeText = selectedKmType === 'driver' ? 'vozačke' : 'obračunske';

  if (confirm('Da li ste sigurni da želite da ažurirate troškove goriva i amortizacije za sve prikazane ture na osnovu ' + kmTypeText + ' kilometraže?')) {
    document.getElementById('updateCostsForm').submit();
  }
}
</script>

<?php require 'footer.php'; ?>