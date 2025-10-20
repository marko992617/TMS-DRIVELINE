<?php
// drivers_login.php - modern styled login for drivers
session_start();
require 'config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT id, password_hash, name FROM drivers WHERE username = ?");
    $stmt->execute([$user]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($driver && password_verify($pass, $driver['password_hash'])) {
        $_SESSION['driver_id'] = $driver['id'];
        $_SESSION['driver_name'] = $driver['name'];
        header('Location: driver_dashboard.php');
        exit;
    } else {
        $error = 'Neispravno korisničko ime ili lozinka.';
    }
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login za Vozače</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
  <div class="card shadow-sm" style="max-width: 400px; width: 100%;">
    <div class="card-body">
      <h3 class="card-title text-center mb-4">Prijava Vozača</h3>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?=htmlspecialchars($error)?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label for="username" class="form-label">Korisničko ime</label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Lozinka</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-primary">Prijavi se</button>
        </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>