<?php
// Fetch upcoming expiries in next 20 days
$today = date('Y-m-d');
$limit = date('Y-m-d', strtotime('+20 days'));
$vehicles = $pdo->query("SELECT plate, registration_date, insurance_expiry, cmr_expiry, tachograph_expiry, sixmo_expiry
    FROM vehicles")->fetchAll(PDO::FETCH_ASSOC);
$reminders = [];
foreach ($vehicles as $v) {
    foreach (['registration_date'=>'Registracija', 'insurance_expiry'=>'Kasko', 'cmr_expiry'=>'CMR', 'tachograph_expiry'=>'Tahograf', 'sixmo_expiry'=>'6-mesečno'] as $field=>$label) {
        if ($v[$field] && $v[$field] <= $limit && $v[$field] >= $today) {
            $reminders[] = "$label vozila {$v['plate']} ističe $v[$field]";
        }
    }
}
if ($reminders):
?>
<div class="alert alert-warning">
    <h5>Podsetnici</h5>
    <ul>
      <?php foreach ($reminders as $rem): ?>
        <li><?= htmlspecialchars($rem) ?></li>
      <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
