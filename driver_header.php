<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Driver Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (isset($_SESSION['driver_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="driver_dashboard.php">Driver Panel</a>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item"><a class="nav-link" href="driver_dashboard.php">Početna</a></li>
    <li class="nav-item"><a class="nav-link" href="driver_unsettled.php">Nerazdužene ture</a></li>
    <li class="nav-item"><a class="nav-link" href="logout.php">Odjava</a></li>
  </ul>
</nav>
<div class="container mt-4">
<?php endif; ?>
