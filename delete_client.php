
<?php
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: clients.php');
    exit;
}

$clientId = intval($_GET['id']);

// Proveri da li klijent ima ponude
$stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE client_id = ?");
$stmt->execute([$clientId]);
$quoteCount = $stmt->fetchColumn();

if ($quoteCount > 0) {
    header("Location: clients.php?error=Klijent ima " . $quoteCount . " ponuda i ne može biti obrisan");
    exit;
}

// Obriši klijenta
$stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
$stmt->execute([$clientId]);

header('Location: clients.php?success=Klijent je uspešno obrisan');
exit;
?>
