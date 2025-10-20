<?php
// report_unsettled.php — Izveštaj nerazduženih tovarnih listova (robustan na šemu bez t.vehicle_plate)
date_default_timezone_set('Europe/Belgrade');
require __DIR__ . '/db.php';

function tableExists(PDO $pdo, $table){
    try { $q = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table)); return (bool)$q->fetchColumn(); }
    catch (Throwable $e){ return false; }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filteri
$date_from = isset($_GET['date_from']) && $_GET['date_from']!=='' ? $_GET['date_from'] : null;
$date_to   = isset($_GET['date_to'])   && $_GET['date_to']  !=='' ? $_GET['date_to']   : null;
$plate     = isset($_GET['plate'])     && $_GET['plate']    !=='' ? trim($_GET['plate']) : null;
$driver_q  = isset($_GET['driver'])    && $_GET['driver']   !=='' ? trim($_GET['driver']) : null;

$hasVehicles = tableExists($pdo, 'vehicles');
$hasDrivers  = tableExists($pdo, 'drivers');

// Dinamički SELECT/JOINS u odnosu na postojeće tabele
$select = [
    "t.id AS tour_id",
    "t.`date` AS tour_date",
    "t.delivery_type",
    "t.ors_id",
    $hasVehicles ? "v.plate AS vehicle_plate" : "NULL AS vehicle_plate",
    $hasDrivers ? "d.name AS driver_name" : "NULL AS driver_name",
    "ds.id AS submission_id",
    "ds.waybill_number"
];
$joins = [
    "LEFT JOIN driver_submissions ds ON ds.tour_id = t.id"
];
if ($hasVehicles) $joins[] = "LEFT JOIN vehicles v ON v.id = t.vehicle_id";
if ($hasDrivers)  $joins[] = "LEFT JOIN drivers d ON d.id = COALESCE(t.driver_id, ds.driver_id)";

$where = [];
$params = [];
if ($date_from) { $where[] = "t.`date` >= ?"; $params[] = $date_from; }
if ($date_to)   { $where[] = "t.`date` <= ?"; $params[] = $date_to; }
if ($plate && $hasVehicles) { $where[] = "v.plate = ?"; $params[] = $plate; }
if ($driver_q && $hasDrivers) { $where[] = "(d.name LIKE ? OR d.full_name LIKE ? OR d.username LIKE ?)"; $params[] = "%{$driver_q}%"; $params[] = "%{$driver_q}%"; $params[] = "%{$driver_q}%"; }

$sql = "SELECT " . implode(", ", $select) . " FROM tours t " . implode(" ", $joins);
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY t.`date` DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Izveštaj — nerazduženi tovarni listovi</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    .status-dot { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; font-weight:700; color:#fff; }
    .ok{ background:#10b981; } .bad{ background:#ef4444; }
    .tbl-wrap{ overflow:auto; }
    .sticky th{ position:sticky; top:0; background:#f8fafc; z-index:1; }
    .filter-card{ border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#fafafa; }
  </style>
</head>
<body class="container py-3">
  <h2 class="mb-3">Izveštaj — nerazduženi tovarni listovi</h2>

  <form class="filter-card mb-3" method="get">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Datum od</label>
        <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Datum do</label>
        <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Registarska oznaka</label>
        <input type="text" name="plate" class="form-control" placeholder="npr. BG-123-AB" value="<?= h($plate) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Vozač</label>
        <input type="text" name="driver" class="form-control" placeholder="ime vozača" value="<?= h($driver_q) ?>">
      </div>
    </div>
    <div class="mt-3">
      <button class="btn btn-dark" type="submit">Primeni filter</button>
      <a class="btn btn-outline-secondary" href="report_unsettled.php">Reset</a>
    </div>
    <?php if ($plate && !$hasVehicles): ?>
      <div class="alert alert-warning mt-2">Napomena: filter po registraciji je onemogućen jer tabela <code>vehicles</code> nije dostupna u bazi.</div>
    <?php endif; ?>
  </form>

  <div class="tbl-wrap">
    <table class="table table-sm table-hover align-middle">
      <thead class="sticky">
        <tr>
          <th>Status</th>
          <th>Datum</th>
          <th>Tip ture</th>
          <th>Reg. broj</th>
          <th>Vozač</th>
          <th>ORS ID</th>
          <th>Broj tovarnog lista</th>
          <th>Tura ID</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-muted">Nema podataka za izabrane filtere.</td></tr>
        <?php else: foreach ($rows as $r):
          $settled = !empty($r['submission_id']) && (string)$r['waybill_number'] !== '';
        ?>
          <tr>
            <td><?php if ($settled): ?><span class="status-dot ok" title="Razduženo">&#10003;</span><?php else: ?><span class="status-dot bad" title="Nerazduženo">&#10005;</span><?php endif; ?></td>
            <td><?= h($r['tour_date']) ?></td>
            <td><?= h($r['delivery_type']) ?></td>
            <td><?= h($r['vehicle_plate']) ?></td>
            <td><?= h($r['driver_name']) ?></td>
            <td><?= h($r['ors_id']) ?></td>
            <td><?= h($r['waybill_number']) ?></td>
            <td><?= (int)$r['tour_id'] ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="text-muted mt-2">
    <small>Pravilo statusa: zeleno = postoji unos vozača u <code>driver_submissions</code> za turu i unet je broj tovarnog lista; crveno = nema takvog unosa.</small>
  </div>
</body>
</html>
