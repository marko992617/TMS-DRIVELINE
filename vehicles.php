<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require 'functions.php';
$vehicles = getVehicles();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Vozila</title>
<link href="assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body>
<div class="container mt-4">
  <h3>Vozila <a href="add_vehicle.php" class="btn btn-sm btn-success">Dodaj</a></h3>
  <table class="table"><thead><tr><th>ID</th><th>Tablice</th></tr></thead><tbody>
    <?php foreach ($vehicles as $v): ?>
      <tr><td><?= $v['id'] ?></td><td><?= $v['plate'] ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div></body></html>