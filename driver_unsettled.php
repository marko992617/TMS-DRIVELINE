<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/auth_driver.php';

$driverId = require_driver();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',           // zašto: cookie važi na svim stranicama
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',         // zašto: izbegava prekid sesije pri prelazu linkovima
    ]);
    session_start();
}

date_default_timezone_set($_ENV['APP_TZ'] ?? 'Europe/Belgrade');

// (opciono) .env
$root = __DIR__;
$dotenv = $root . '/.env';
if (is_file($dotenv)) {
    foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k,$v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = trim($v, "\"'");
    }
}

$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_NAME = $_ENV['DB_NAME'] ?? 'vaspot_tms';
$DB_USER = $_ENV['DB_USER'] ?? 'vaspot_tms';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_CHAR = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opt);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB connection error.';
    exit;
}

?>
// file: auth_driver.php
// Zašto: jedinstvena tačka istine za sesiju vozača (različiti ključevi u starom kodu).
declare(strict_types=1);

function current_driver_id(): ?int {
    // podrži obe varijante: direktni driver_id ili user_id sa ulogom driver
    if (isset($_SESSION['driver_id']) && is_numeric($_SESSION['driver_id'])) {
        return (int)$_SESSION['driver_id'];
    }
    if (
        isset($_SESSION['user_id'], $_SESSION['role']) &&
        $_SESSION['role'] === 'driver' &&
        is_numeric($_SESSION['user_id'])
    ) {
        return (int)$_SESSION['user_id'];
    }
    // fallback: neki sistemi stavljaju u $_SESSION['user']['id']
    if (
        isset($_SESSION['user']['id'], $_SESSION['user']['role']) &&
        $_SESSION['user']['role'] === 'driver' &&
        is_numeric($_SESSION['user']['id'])
    ) {
        return (int)$_SESSION['user']['id'];
    }
    return null;
}

function require_driver(): int {
    $id = current_driver_id();
    if ($id === null) {
        // ako imate stranicu za login vozača, možete umesto 403 da uradite redirect:
        // header('Location: driver_login.php'); exit;
        http_response_code(403);
        echo 'Nije prijavljen vozač.';
        exit;
    }
    return $id;
}

<?php
// file: driver_unsettled.php
// purpose: iste sesije i izgled kao dashboard; prikazuje nerazdužene ture od 15.08.2025.
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_driver.php';

$driverId = require_driver();

// datum početka (default: 2025-08-15)
$rawFrom = trim($_GET['from'] ?? '2025-08-15');
$from = null;
foreach (['Y-m-d','d.m.Y'] as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $rawFrom);
    if ($dt && $dt->format($fmt) === $rawFrom) { $from = $dt->format('Y-m-d'); break; }
}
if (!$from) { $from = '2025-08-15'; }

$sql = <<<SQL
SELECT 
    t.id,
    t.date,
    t.loading_time,
    t.loading_loc,
    t.unloading_loc,
    t.route,
    v.plate AS vehicle_plate
FROM tours t
LEFT JOIN driver_submissions ds 
    ON ds.tour_id = t.id AND ds.driver_id = :driver_id
LEFT JOIN vehicles v 
    ON v.id = t.vehicle_id
WHERE 
    t.driver_id = :driver_id
    AND t.date >= :from_date
    AND ds.id IS NULL
ORDER BY t.date DESC, t.loading_time DESC
SQL;

$st = $pdo->prepare($sql);
$st->execute(['driver_id' => $driverId, 'from_date' => $from]);
$tours = $st->fetchAll();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmtDate(?string $d): string { return $d ? (new DateTime($d))->format('d.m.Y') : ''; }
function fmtTime(?string $dt): string { return $dt ? (new DateTime($dt))->format('H:i') : ''; }

$count = count($tours);
?><!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <title>Nerazdužene ture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Pretpostavka: driver_dashboard.php već vuče Bootstrap i app.css globalno -->
</head>
<body>
<div class="container my-4">
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0">Nerazdužene ture</h5>
                        <small class="text-muted">Bez predaje od <?=h((new DateTime($from))->format('d.m.Y'))?></small>
                    </div>
                    <form class="d-flex align-items-center gap-2" method="get">
                        <label class="form-label mb-0 me-2">Od datuma:</label>
                        <input type="date" class="form-control" name="from" value="<?=h($from)?>" />
                        <button class="btn btn-primary">Primeni</button>
                    </form>
                </div>
                <div class="card-body">
                    <?php if ($count === 0): ?>
                        <div class="alert alert-info mb-0">Sve ture su razdužene od izabranog datuma.</div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-muted">Ukupno: <strong><?=$count?></strong></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Vreme</th>
                                    <th>Vozilo</th>
                                    <th>Ruta</th>
                                    <th>Utovar</th>
                                    <th>Istovar</th>
                                    <th class="text-end">Akcija</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tours as $t): ?>
                                    <tr>
                                        <td><?=h(fmtDate($t['date']))?></td>
                                        <td><?=h(fmtTime($t['loading_time']))?></td>
                                        <td><?=h($t['vehicle_plate'] ?? '—')?></td>
                                        <td><?=h($t['route'])?></td>
                                        <td><?=h($t['loading_loc'])?></td>
                                        <td><?=h($t['unloading_loc'])?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="driver_tour.php?tour_id=<?=urlencode((string)$t['id'])?>">Otvori</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted small">Prikaz uključuje samo ture bez unosa u evidenciji vozača.</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php
// --- PATCH PRIMER ako treba i na dashboardu (na vrh driver_dashboard.php) ---
// require __DIR__ . '/config.php';
// require __DIR__ . '/auth_driver.php';
// $driverId = require_driver();
