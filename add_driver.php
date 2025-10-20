<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require 'functions.php';
if ($_SERVER['REQUEST_METHOD']=='POST') {
    addDriver($_POST['name']);
    header('Location: drivers.php');
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Dodaj Vozača</title>
<link href="assets/css/style.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body><div class="container mt-4">
  <form method="POST" class="card p-4">
    <h5 class="card-title">Dodaj Vozača</h5>
    <input name="name" class="form-control mb-3" placeholder="Ime" required>
    <button class="btn btn-primary">Sačuvaj</button>
  </form>
</div></body></html>