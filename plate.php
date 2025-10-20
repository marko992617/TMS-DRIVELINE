<?php
// plate.php - obračun zarade vozača sa izmenljivim dnevnica i prilagođavanjem troškova/bonusa

require 'header.php';
require 'config.php';
require 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plate'])) {
    $driverId = intval($_POST['driver']);
    $dateFrom = $_POST['date_from'];
    $dateTo   = $_POST['date_to'];

    // 1) Update allowance per tour
    $totalAllowance = 0;
    if (!empty($_POST['allowance']) && is_array($_POST['allowance'])) {
        $updStmt = $pdo->prepare("UPDATE tours SET allowance = ? WHERE id = ?");
        foreach ($_POST['allowance'] as $tourId => $allow) {
            $allowVal = floatval($allow);
            $updStmt->execute([$allowVal, $tourId]);
            $totalAllowance += $allowVal;
        }
    }

    // 2) Compute total kilometers
    $kmStmt = $pdo->prepare("SELECT SUM(km) FROM tours WHERE driver_id = ? AND DATE(loading_time) BETWEEN ? AND ?");
    $kmStmt->execute([$driverId, $dateFrom, $dateTo]);
    $totalKm = intval($kmStmt->fetchColumn());

    // 3) Insert payroll
    $insStmt = $pdo->prepare("INSERT INTO payrolls 
        (driver_id, date_from, date_to, total_km, total_allowance, paid_amount) 
        VALUES (?, ?, ?, ?, ?, 0)");
    $insStmt->execute([$driverId, $dateFrom, $dateTo, $totalKm, $totalAllowance]);
    $payrollId = $pdo->lastInsertId();

    // 4) Insert adjustments (bonus and deductions)
    if (!empty($_POST['adj_type']) && is_array($_POST['adj_type'])) {
        $itemStmt = $pdo->prepare("INSERT INTO payroll_items (payroll_id, type, reason, amount) VALUES (?, ?, ?, ?)");
        foreach ($_POST['adj_type'] as $idx => $type) {
            $reason = $_POST['adj_reason'][$idx] ?? '';
            $amt = floatval($_POST['adj_amount'][$idx] ?? 0);
            if ($reason !== '' && $amt != 0) {
                $itemStmt->execute([$payrollId, $type, $reason, abs($amt)]);
                if ($type === 'BONUS') {
                    $totalAllowance += $amt;
                } else {
                    $totalAllowance -= $amt;
                }
            }
        }
    }

    // 5) Update paid amount if provided
    $paid = floatval($_POST['paid_amount'] ?? 0);
    $updPaid = $pdo->prepare("UPDATE payrolls SET paid_amount = ? WHERE id = ?");
    $updPaid->execute([$paid, $payrollId]);

    header("Location: plate.php?view={$payrollId}");
    exit;
}

// Fetch drivers for selection
$drivers = $pdo->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle view mode
$viewId = $_GET['view'] ?? null;
if ($viewId) {
    // Display saved payroll
    $stmt = $pdo->prepare("SELECT p.*, d.name AS driver_name FROM payrolls p JOIN drivers d ON p.driver_id = d.id WHERE p.id = ?");
    $stmt->execute([$viewId]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<div class="container"><h3>Obračun za ' . htmlspecialchars($pay['driver_name']) . '</h3>';
    echo '<p>Period: ' . htmlspecialchars($pay['date_from']) . ' - ' . htmlspecialchars($pay['date_to']) . '</p>';
    // Fetch tours
    $ts = $pdo->prepare("SELECT DATE(loading_time) AS date, delivery_type, km, allowance FROM tours WHERE driver_id = ? AND DATE(loading_time) BETWEEN ? AND ?");
    $ts->execute([$pay['driver_id'], $pay['date_from'], $pay['date_to']]);
    $tours = $ts->fetchAll(PDO::FETCH_ASSOC);
    echo '<table class="table"><thead><tr><th>Datum</th><th>Tip ture</th><th>Km</th><th>Dnevnica</th></tr></thead><tbody>';
    foreach ($tours as $t) {
        echo '<tr><td>' . htmlspecialchars($t['date']) . '</td><td>' . htmlspecialchars($t['delivery_type']) . '</td><td>' . htmlspecialchars($t['km']) . '</td><td>' . htmlspecialchars($t['allowance']) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p>Ukupno km: ' . $pay['total_km'] . '</p>';
    echo '<p>Ukupno dnevnica: ' . number_format($pay['total_allowance'], 2, ',', ' ') . '</p>';
    // Fetch items
    $is = $pdo->prepare("SELECT type, reason, amount FROM payroll_items WHERE payroll_id = ?");
    $is->execute([$viewId]);
    $items = $is->fetchAll(PDO::FETCH_ASSOC);
    if ($items) {
        echo '<h5>Dodatne stavke</h5><ul>';
        foreach ($items as $it) {
            $sign = $it['type'] === 'BONUS' ? '+' : '-';
            echo '<li>' . htmlspecialchars($it['type']) . ' ' . htmlspecialchars($it['reason']) . ': ' . $sign . number_format($it['amount'], 2, ',', ' ') . '</li>';
        }
        echo '</ul>';
    }
    echo '<p>Uplaćeno na račun: ' . number_format($pay['paid_amount'], 2, ',', ' ') . '</p>';
    echo '<p><strong>Za isplatu: ' . number_format($pay['total_allowance'] - array_reduce($items, fn($c,$i)=> $c + ($i['type']==='BONUS'? -$i['amount']:$i['amount']), 0) - $pay['paid_amount'], 2, ',', ' ') . '</strong></p>';
    echo '</div>';
    require 'footer.php';
    exit;
}

// Default filter form
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$driverId = $_GET['driver'] ?? '';
?>
<div class="container">
  <h3>Obračun zarade</h3>
  <form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label>Vozač</label>
      <select name="driver" class="form-select">
        <option value="">-- izaberi --</option>
        <?php foreach ($drivers as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $driverId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label>Od</label><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>"></div>
    <div class="col-md-3"><label>Do</label><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>"></div>
    <div class="col-md-3 align-self-end"><button class="btn btn-primary">Učitaj ture</button></div>
  </form>

<?php if ($driverId): 
    $ts = $pdo->prepare("SELECT id, DATE(loading_time) AS date, delivery_type, km, allowance FROM tours WHERE driver_id = ? AND DATE(loading_time) BETWEEN ? AND ? ORDER BY loading_time");
    $ts->execute([$driverId, $dateFrom, $dateTo]);
    $tours = $ts->fetchAll(PDO::FETCH_ASSOC);
    if ($tours): ?>
    <form method="post">
      <input type="hidden" name="driver" value="<?= $driverId ?>">
      <input type="hidden" name="date_from" value="<?= $dateFrom ?>">
      <input type="hidden" name="date_to" value="<?= $dateTo ?>">
      <table class="table"><thead><tr><th>Datum</th><th>Tip ture</th><th>Km</th><th>Dnevnica</th></tr></thead><tbody>
        <?php foreach ($tours as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['date']) ?></td>
            <td><?= htmlspecialchars($t['delivery_type']) ?></td>
            <td><?= htmlspecialchars($t['km']) ?></td>
            <td><input type="number" step="0.01" name="allowance[<?= $t['id'] ?>]" value="<?= $t['allowance'] ?>" class="form-control"></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
      <h5>Dodatne stavke (bonus/odbitak)</h5>
      <button type="button" id="add_adj" class="btn btn-secondary mb-2">Dodaj stavku</button>
      <div id="adjustments"></div>
      <div class="mb-3"><label>Uplaćeno na račun</label><input type="number" step="0.01" name="paid_amount" class="form-control"></div>
      <button type="submit" name="save_plate" class="btn btn-success">Sačuvaj obračun</button>
    </form>
<?php endif; endif; ?>

<script>
// Add dynamic adjustment fields
document.getElementById('add_adj').addEventListener('click', function() {
  const container = document.getElementById('adjustments');
  container.insertAdjacentHTML('beforeend',
    '<div class="row g-2 mb-2">'+
      '<div class="col-md-2"><select name="adj_type[]" class="form-select"><option value="BONUS">BONUS</option><option value="ODBITAK">ODBITAK</option></select></div>'+
      '<div class="col-md-6"><input name="adj_reason[]" class="form-control" placeholder="Opis"></div>'+
      '<div class="col-md-4"><input name="adj_amount[]" type="number" step="0.01" class="form-control" placeholder="Iznos"></div>'+
    '</div>');
});
</script>

<?php require 'footer.php'; ?>