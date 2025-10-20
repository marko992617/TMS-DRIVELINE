<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/vendor/autoload.php'; // Dompdf
use Dompdf\Dompdf;

$driverId = $_POST['driver_id'];
$from = $_POST['from_date'];
$to = $_POST['to_date'];

$stmt = $db->prepare("SELECT t.date, t.revenue, a.daily_allowance, a.adjustment_amount, a.bank_payment
    FROM tours t
    LEFT JOIN payroll_adjustments a ON a.driver_id = ? AND a.tour_date = t.date
    WHERE t.driver_id = ? AND t.date BETWEEN ? AND ?
    ORDER BY t.date");
$stmt->execute([$driverId, $driverId, $from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<h1>Platni spisak</h1>
<p>Vozač: <?= htmlspecialchars(getDriverName($driverId)) ?></p>
<p>Period: <?= htmlspecialchars($from) ?> - <?= htmlspecialchars($to) ?></p>
<table border="1" cellpadding="5" cellspacing="0">
  <tr><th>Datum</th><th>Prihod</th><th>Dnevnica</th><th>Ispravka</th><th>Uplata na račun</th><th>Ukupno</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['date']) ?></td>
      <td><?= htmlspecialchars($r['revenue']) ?></td>
      <td><?= htmlspecialchars($r['daily_allowance']) ?></td>
      <td><?= htmlspecialchars($r['adjustment_amount']) ?></td>
      <td><?= htmlspecialchars($r['bank_payment']) ?></td>
      <td><?= htmlspecialchars($r['revenue'] + $r['daily_allowance'] + $r['adjustment_amount'] - $r['bank_payment']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$output = $dompdf->output();
$filename = "payroll_{$driverId}_{$from}_{$to}.pdf";
$dir = __DIR__ . '/payroll';
if (!is_dir($dir)) mkdir($dir, 0777, true);
file_put_contents("$dir/$filename", $output);

// Redirect to PDF
header("Location: payroll/$filename");
exit;

function getDriverName($id) {
    global $db;
    $s = $db->prepare("SELECT name FROM drivers WHERE id = ?");
    $s->execute([$id]);
    return $s->fetchColumn();
}
