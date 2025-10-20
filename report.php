<?php
require 'functions.php';
$filters = [
  'driver'  => $_GET['driver']  ?? null,
  'vehicle' => $_GET['vehicle'] ?? null,
  'from'    => $_GET['from']    ?? null,
  'to'      => $_GET['to']      ?? null,
];
$tours = getTours($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Izveštaji</title>
<link href="assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h3>Izveštaji</h3>
  <form method="get" class="row g-3">
    <div class="col"><input type="date" name="from" class="form-control" value="<?php echo $filters['from']; ?>"></div>
    <div class="col"><input type="date" name="to" class="form-control" value="<?php echo $filters['to']; ?>"></div>
    <div class="col"><select name="driver" class="form-select"><option value="">Svi vozači</option></select></div>
    <div class="col"><select name="vehicle" class="form-select"><option value="">Sva vozila</option></select></div>
    <div class="col"><button class="btn btn-primary">Filter</button></div>
    <div class="col"><a class="btn btn-danger" href="export_pdf.php?<?php echo http_build_query($filters); ?>">Export PDF</a></div>
    <div class="col"><a class="btn btn-success" href="export_excel.php?<?php echo http_build_query($filters); ?>">Export Excel</a></div>
  </form>
  <table class="table mt-3">
    <thead><tr><th>ID</th><th>Datum</th><th>Vozač</th><th>Vozilo</th><!-- other cols --></tr></thead>
    <tbody>
    <?php foreach ($tours as $tour): ?>
      <tr>
        <td><?= $tour['id'] ?></td>
        <td><?= $tour['date'] ?></td>
        <td><?= $tour['driver'] ?></td>
        <td><?= $tour['vehicle'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
