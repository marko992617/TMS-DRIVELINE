<?php
// driver_dashboard.php - vozačev dashboard with custom warehouse and pallet icons and updated footer
session_start();
if (empty($_SESSION['driver_id'])) {
    header('Location: drivers_login.php');
    exit;
}
require 'config.php';
date_default_timezone_set('Europe/Belgrade');

$driverId = $_SESSION['driver_id'];
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Fetch tours with extra fields
$toursStmt = $pdo->prepare("
    SELECT 
      t.id,
      t.ors_id,
      t.delivery_type,
      v.plate AS vehicle_plate,
      DATE(t.loading_time) AS date,
      TIME(t.loading_time) AS time,
      t.loading_loc,
      t.unloading_loc,
      (SELECT COUNT(*) FROM driver_submissions ds WHERE ds.tour_id = t.id AND ds.driver_id = ?) AS submitted
    FROM tours t
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    WHERE t.driver_id = ? AND DATE(t.loading_time) = ?
    ORDER BY t.loading_time
");
$toursStmt->execute([$driverId, $driverId, $selectedDate]);
$tours = $toursStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Vozača</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="driver_dashboard.php">TMS Vozač</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#driverNav" aria-controls="driverNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="driverNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link active" href="driver_dashboard.php">Ture</a></li>
        
        <li class="nav-item"><a class="nav-link" href="drivers_logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="container my-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Ture za <?php echo htmlspecialchars($selectedDate); ?></h2>
    <form class="d-flex" method="get">
      <input class="form-control me-2" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
      <button class="btn btn-outline-secondary" type="submit">Prikaži</button>
    </form>
  </div>
  <?php if (empty($tours)): ?>
    <div class="alert alert-info">Nema tura za ovaj datum.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach($tours as $t): ?>
        <div class="list-group-item">
          <div class="d-flex w-100 justify-content-between">
            <h5 class="mb-1"><?php echo htmlspecialchars($t['date'].' '.$t['time']); ?></h5>
            <small><?php echo $t['vehicle_plate'] ? htmlspecialchars($t['vehicle_plate']) : ''; ?></small>
          </div>
          <p class="mb-1">
            <i class="fas fa-warehouse text-primary"></i>
            <strong>Utovar:</strong> <?php echo htmlspecialchars($t['loading_loc']); ?><br>
            <i class="fas fa-pallet text-success"></i>
            <strong>Istovar:</strong> <?php echo htmlspecialchars($t['unloading_loc']); ?>
          </p>
          <small class="text-muted">
            <strong>ORS ID:</strong> <?php echo htmlspecialchars($t['ors_id']); ?> |
            <strong>Tip:</strong> <?php echo htmlspecialchars($t['delivery_type']); ?>
          </small>
          <div class="mt-2 text-end">
            <?php if ($t['submitted']): ?>
              <a href="driver_tour.php?tour_id=<?=$t['id']?>&date=<?=$selectedDate?>" class="btn btn-sm btn-secondary">Pregled</a>
            <?php else: ?>
              <a href="driver_tour.php?tour_id=<?=$t['id']?>&date=<?=$selectedDate?>" class="btn btn-sm btn-primary">Unos</a>
              <a href="driver_map_tour.php?tour_id=<?=$t['id']?>&date=<?=$selectedDate?>" class="btn btn-sm btn-primary">Mapa ture</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<footer class="bg-light text-center py-3 mt-auto">
  <small>&copy; <?=date('Y')?> TransportAPP - Kreirao i implementirao - Copyright Marko Mladenović</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
