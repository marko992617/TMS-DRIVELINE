<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TransportApp - <?= ucfirst(str_replace('.php','',$current)) ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">TransportApp</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $current=='dashboard.php'?'active':'' ?>" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='add_tour.php'?'active':'' ?>" href="add_tour.php">Dodaj turu</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='tours.php'?'active':'' ?>" href="tours.php">Pregled tura</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='import_tours.php'?'active':'' ?>" href="import_tours.php">Import tura</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='bulk_process.php'?'active':'' ?>" href="bulk_process.php">Bulk obrada</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='send_tours.php'?'active':'' ?>" href="send_tours.php">Slanje tura</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='maintenance.php'?'active':'' ?>" href="maintenance.php">Održavanje vozila</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='fin_report.php'?'active':'' ?>" href="fin_report.php">Fin. izveštaj</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='settings.php'?'active':'' ?>" href="settings.php">Podešavanja</a></li>
        <li class="nav-item"><a class="nav-link <?= $current=='plate.php'?'active':'' ?>" href="plate.php">Plate</a></li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Log out</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="container mt-4">
