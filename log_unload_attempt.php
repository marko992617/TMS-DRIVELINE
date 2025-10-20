
<?php
// Pretpostavka: $pdo konekcija je već aktivna, $driver_id i $tour_id su poznati

$errors = [];
if (empty($_POST['km_end'])) $errors[] = 'Završna kilometraža';
if (empty($_POST['fuel_used'])) $errors[] = 'Potrošeno gorivo';
if (empty($_POST['invoice_number'])) $errors[] = 'Broj računa';
// Dodaj ostale obavezne provere po potrebi

$status = empty($errors) ? 'success' : 'fail';
$message = $status === 'success' ? 'Razduženje uspešno' : 'Greška pri razduženju';
$missing_fields = implode(', ', $errors);

// Loguj pokušaj razduženja u driver_unload_logs
$stmtLog = $pdo->prepare("INSERT INTO driver_unload_logs (driver_id, tour_id, status, message, missing_fields) VALUES (?, ?, ?, ?, ?)");
$stmtLog->execute([$driver_id, $tour_id, $status, $message, $missing_fields]);
?>
