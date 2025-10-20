
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
    header('Location: quotes.php');
    exit;
}

$quoteId = intval($_GET['id']);

// Obriši ponudu
$stmt = $pdo->prepare("DELETE FROM quotes WHERE id = ?");
$stmt->execute([$quoteId]);

header('Location: quotes.php?success=Ponuda je uspešno obrisana');
exit;
?>
