
<?php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Basic validation
    if ($username === '' || $password === '') {
        $error = 'Molim unesite važeće korisničko ime i lozinku.';
    } else {
        // Fetch user
        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['id'];
            
            // Remember credentials if checked
            if ($remember) {
                setcookie('remembered_username', $username, time() + (86400 * 30), '/'); // 30 days
            } else {
                setcookie('remembered_username', '', time() - 3600, '/'); // Delete cookie
            }
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Neispravno korisničko ime ili lozinka.';
        }
    }
}

// Get remembered username from cookie
$remembered_username = $_COOKIE['remembered_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8">
  <title>Login | TransportApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .login-card { max-width: 380px; margin: 80px auto; padding: 2rem; background: #fff; border-radius: .5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
<div class="login-card">
  <h3 class="text-center mb-4">Prijavite se</h3>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php">
    <div class="form-floating mb-3">
      <input type="text" name="username" class="form-control" id="user" placeholder="Korisničko ime" value="<?= htmlspecialchars($remembered_username) ?>" required>
      <label for="user">Korisničko ime</label>
    </div>
    <div class="form-floating mb-3">
      <input type="password" name="password" class="form-control" id="pass" placeholder="Lozinka" required>
      <label for="pass">Lozinka</label>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="remember" id="remember" <?= $remembered_username ? 'checked' : '' ?>>
      <label class="form-check-label" for="remember">
        Zapamti korisničko ime
      </label>
    </div>
    <button class="btn btn-primary w-100">Prijavi se</button>
  </form>
</div>
</body>
</html>
