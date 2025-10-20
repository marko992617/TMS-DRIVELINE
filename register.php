<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?,?)");
    $stmt->execute([$u, $p]);
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Register</title>
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <form method="POST" class="p-4 border rounded">
    <h4>Registracija</h4>
    <div class="mb-2"><input name="username" class="form-control" placeholder="Korisničko ime" required></div>
    <div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Lozinka" required></div>
    <button class="btn btn-primary w-100">Registruj se</button>
    <p class="mt-2 text-center"><a href="login.php">Već imate nalog? Prijavite se</a></p>
  </form>
</body>
</html>