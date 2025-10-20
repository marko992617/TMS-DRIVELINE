
<?php
require_once '../config.php'; // ili include db konekcije direktno

$stmt = $pdo->query("SELECT * FROM driver_unload_logs ORDER BY timestamp DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Log razduženja vozača</title>
  <style>
    body { font-family: sans-serif; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f3f3f3; }
    .fail { background-color: #fdd; }
    .success { background-color: #dfd; }
  </style>
</head>
<body>
  <h2>Log razduženja vozača</h2>
  <table>
    <tr>
      <th>ID</th><th>Vozač</th><th>Tura</th><th>Vreme</th><th>Status</th><th>Poruka</th><th>Nedostaje</th>
    </tr>
    <?php foreach ($logs as $log): ?>
      <tr class="<?= htmlspecialchars($log['status']) ?>">
        <td><?= $log['id'] ?></td>
        <td><?= $log['driver_id'] ?></td>
        <td><?= $log['tour_id'] ?></td>
        <td><?= $log['timestamp'] ?></td>
        <td><?= $log['status'] ?></td>
        <td><?= $log['message'] ?></td>
        <td><?= $log['missing_fields'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
