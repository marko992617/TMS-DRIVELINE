
<?php
require 'config.php';
require 'functions.php';
require 'vendor/autoload.php';
use Dompdf\Dompdf;

if (session_status() === PHP_SESSION_NONE) session_start();

// Check authentication before any output
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle save payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll'])) {
    $driverId = $_POST['driver'];
    $dateFrom = $_POST['date_from'];
    $dateTo   = $_POST['date_to'];

    // Update allowances per tour in tours table
    $totalKm = 0;
    $totalAllowance = 0;
    if (isset($_POST['allowance']) && is_array($_POST['allowance'])) {
        $upd = $pdo->prepare("UPDATE tours SET allowance = ? WHERE id = ?");
        foreach ($_POST['allowance'] as $tourId => $allow) {
            $upd->execute([$allow, $tourId]);
            $totalAllowance += floatval($allow);
        }
    }

    // Compute totalKm anew
    $stmtK = $pdo->prepare("SELECT SUM(km) FROM tours WHERE driver_id=? AND DATE(loading_time) BETWEEN ? AND ?");
    $stmtK->execute([$driverId,$dateFrom,$dateTo]);
    $totalKm = intval($stmtK->fetchColumn());

    // Insert payroll record
    $ins = $pdo->prepare("INSERT INTO payrolls
        (driver_id,date_from,date_to,total_km,total_allowance,paid_amount)
        VALUES(?,?,?,?,?,?)");
    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
    $ins->execute([$driverId, $dateFrom, $dateTo, $totalKm, $totalAllowance, $paidAmount]);
    $payrollId = $pdo->lastInsertId();

    // Insert bonus items
    if (!empty($_POST['bonus_reason']) && is_array($_POST['bonus_reason'])) {
        $it = $pdo->prepare("INSERT INTO payroll_items (payroll_id,type,reason,amount) VALUES (?,?,?,?)");
        foreach ($_POST['bonus_reason'] as $idx => $reason) {
            $amt = floatval($_POST['bonus_amount'][$idx] ?? 0);
            if ($reason !== '' && $amt) {
                $it->execute([$payrollId,'BONUS',$reason,$amt]);
            }
        }
    }
    
    // Insert deduction items
    if (!empty($_POST['deduction_reason']) && is_array($_POST['deduction_reason'])) {
        $it = $pdo->prepare("INSERT INTO payroll_items (payroll_id,type,reason,amount) VALUES (?,?,?,?)");
        foreach ($_POST['deduction_reason'] as $idx => $reason) {
            $amt = floatval($_POST['deduction_amount'][$idx] ?? 0);
            if ($reason !== '' && $amt) {
                $it->execute([$payrollId,'ODBITAK',$reason,$amt]);
            }
        }
    }

    // Redirect to view
    header("Location: payroll.php?view=$payrollId");
    exit;
}

// Fetch drivers
$drivers = $pdo->query("SELECT id,name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// View saved payroll
$viewId = $_GET['view'] ?? null;
if ($viewId) {
    // Fetch payroll data
    $stmt = $pdo->prepare("SELECT p.*, d.name AS driver_name
        FROM payrolls p JOIN drivers d ON p.driver_id=d.id
        WHERE p.id = ?");
    $stmt->execute([$viewId]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payroll) {
        echo '<div class="alert alert-danger">Obračun ne postoji.</div>';
        require 'footer.php';
        exit;
    }

    // Fetch tours for this payroll
    $stmtT = $pdo->prepare("SELECT id, DATE(loading_time) AS date, delivery_type, km, allowance
        FROM tours
        WHERE driver_id=? AND DATE(loading_time) BETWEEN ? AND ?
        ORDER BY loading_time ASC");
    $stmtT->execute([$payroll['driver_id'],$payroll['date_from'],$payroll['date_to']]);
    $tours = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payroll items (bonuses/deductions)
    $stmtI = $pdo->prepare("SELECT type,reason,amount FROM payroll_items WHERE payroll_id=?");
    $stmtI->execute([$viewId]);
    $items = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // Generate PDF if requested - MUST be before any HTML output
    if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
        // Start output buffering and clean any existing output
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 20px; font-size: 11px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .company { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .address { font-size: 10px; color: #555; }
        .title { font-size: 14px; font-weight: bold; margin: 20px 0; text-align: center; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; font-size: 10px; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .text-end { text-align: right; }
        .totals { background-color: #f5f5f5; font-weight: bold; }
        .info-section { margin-bottom: 15px; }
        .info-section p { margin: 5px 0; }
        </style></head><body>';
        
        $html .= '<div class="header">
            <div class="company">VAŠ POTRČKO DOO BEOGRAD</div>
            <div class="address">Aleksandra Stamboliškog 6A</div>
            <div class="address">PIB 107418055 | MB 20798513</div>
        </div>';
        
        $html .= '<div class="title">OBRAČUN PLATA</div>';
        $html .= '<div class="info-section">';
        $html .= '<p><strong>Vozač:</strong> ' . htmlspecialchars($payroll['driver_name']) . '</p>';
        $html .= '<p><strong>Period:</strong> ' . $payroll['date_from'] . ' - ' . $payroll['date_to'] . '</p>';
        $html .= '<p><strong>Ukupno kilometara:</strong> ' . number_format($payroll['total_km'], 0, ',', '.') . ' km</p>';
        $html .= '<p><strong>Datum kreiranja:</strong> ' . date('d.m.Y H:i', strtotime($payroll['created_at'])) . '</p>';
        $html .= '</div>';
        
        $html .= '<table><thead><tr>
            <th>Datum</th><th>Tip ture</th><th>KM</th><th>Dnevnica (RSD)</th>
        </tr></thead><tbody>';
        
        foreach ($tours as $t) {
            $html .= '<tr>
                <td>' . $t['date'] . '</td>
                <td>' . htmlspecialchars($t['delivery_type']) . '</td>
                <td class="text-end">' . number_format($t['km'], 0, ',', '.') . '</td>
                <td class="text-end">' . number_format($t['allowance'], 2, ',', '.') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        if (!empty($items)) {
            $html .= '<h4>Dodatne stavke:</h4><table><thead><tr>
                <th>Tip</th><th>Razlog</th><th>Iznos (RSD)</th>
            </tr></thead><tbody>';
            
            foreach ($items as $item) {
                $html .= '<tr>
                    <td>' . $item['type'] . '</td>
                    <td>' . htmlspecialchars($item['reason']) . '</td>
                    <td class="text-end">' . number_format($item['amount'], 2, ',', '.') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }
        
        $totalBonus = array_sum(array_column(array_filter($items, fn($i) => $i['type'] == 'BONUS'), 'amount'));
        $totalDeductions = array_sum(array_column(array_filter($items, fn($i) => $i['type'] == 'ODBITAK'), 'amount'));
        $finalAmount = $payroll['total_allowance'] + $totalBonus - $totalDeductions;
        
        $html .= '<table class="totals"><tr>
            <td><strong>Ukupna dnevnica:</strong></td>
            <td class="text-end"><strong>' . number_format($payroll['total_allowance'], 2, ',', '.') . ' RSD</strong></td>
        </tr>';
        if ($totalBonus > 0) {
            $html .= '<tr><td>Bonus:</td><td class="text-end">+' . number_format($totalBonus, 2, ',', '.') . ' RSD</td></tr>';
        }
        if ($totalDeductions > 0) {
            $html .= '<tr><td>Odbitci:</td><td class="text-end">-' . number_format($totalDeductions, 2, ',', '.') . ' RSD</td></tr>';
        }
        $html .= '<tr>
            <td><strong>Za isplatu:</strong></td>
            <td class="text-end"><strong>' . number_format($finalAmount, 2, ',', '.') . ' RSD</strong></td>
        </tr>';
        if ($payroll['paid_amount'] > 0) {
            $html .= '<tr><td>Uplaćeno:</td><td class="text-end">' . number_format($payroll['paid_amount'], 2, ',', '.') . ' RSD</td></tr>';
            $remaining = $finalAmount - $payroll['paid_amount'];
            $html .= '<tr><td>Ostalo za isplatu:</td><td class="text-end">' . number_format($remaining, 2, ',', '.') . ' RSD</td></tr>';
        }
        $html .= '</table>';
        
        $html .= '</body></html>';
        
        // Create Dompdf with proper options
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Clean up output buffer and stream PDF
        ob_end_clean();
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Use dompdf's stream method which handles headers properly
        $dompdf->stream("obracun_{$payroll['driver_name']}_{$payroll['date_from']}.pdf", array("Attachment" => false));
        exit;
    }

    // Include header only for HTML view
    require 'header.php';

    // HTML view of payroll
    ?>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Pregled obračuna #<?= $viewId ?></h3>
            <div>
                <a href="?export=pdf&view=<?= $viewId ?>" class="btn btn-danger" target="_blank">
                    <i class="fas fa-file-pdf"></i> Izvezi PDF
                </a>
                <a href="payroll.php" class="btn btn-secondary">Nazad na obračune</a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Osnovni podaci</strong></div>
                    <div class="card-body">
                        <p><strong>Vozač:</strong> <?= htmlspecialchars($payroll['driver_name']) ?></p>
                        <p><strong>Period:</strong> <?= $payroll['date_from'] ?> - <?= $payroll['date_to'] ?></p>
                        <p><strong>Ukupno km:</strong> <?= number_format($payroll['total_km'], 0, ',', '.') ?></p>
                        <p><strong>Kreiran:</strong> <?= date('d.m.Y H:i', strtotime($payroll['created_at'])) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Ture u periodu</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Tip ture</th>
                                        <th class="text-end">KM</th>
                                        <th class="text-end">Dnevnica (RSD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tours as $t): ?>
                                    <tr>
                                        <td><?= $t['date'] ?></td>
                                        <td><?= htmlspecialchars($t['delivery_type']) ?></td>
                                        <td class="text-end"><?= number_format($t['km'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($t['allowance'], 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">Ukupno:</th>
                                        <th class="text-end"><?= number_format($payroll['total_km'], 0, ',', '.') ?></th>
                                        <th class="text-end"><?= number_format($payroll['total_allowance'], 2, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($items)): ?>
                <div class="card mt-3">
                    <div class="card-header"><strong>Dodatne stavke</strong></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Tip</th><th>Razlog</th><th class="text-end">Iznos (RSD)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr class="<?= $item['type'] == 'BONUS' ? 'table-success' : 'table-danger' ?>">
                                    <td><?= $item['type'] ?></td>
                                    <td><?= htmlspecialchars($item['reason']) ?></td>
                                    <td class="text-end">
                                        <?= $item['type'] == 'BONUS' ? '+' : '-' ?><?= number_format($item['amount'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card mt-3">
                    <div class="card-header"><strong>Finansijski pregled</strong></div>
                    <div class="card-body">
                        <?php 
                        $totalBonus = array_sum(array_column(array_filter($items, fn($i) => $i['type'] == 'BONUS'), 'amount'));
                        $totalDeductions = array_sum(array_column(array_filter($items, fn($i) => $i['type'] == 'ODBITAK'), 'amount'));
                        $finalAmount = $payroll['total_allowance'] + $totalBonus - $totalDeductions;
                        ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Osnovna dnevnica:</strong> <?= number_format($payroll['total_allowance'], 2, ',', '.') ?> RSD</p>
                                <?php if ($totalBonus > 0): ?>
                                <p class="text-success"><strong>Bonusi:</strong> +<?= number_format($totalBonus, 2, ',', '.') ?> RSD</p>
                                <?php endif; ?>
                                <?php if ($totalDeductions > 0): ?>
                                <p class="text-danger"><strong>Odbitci:</strong> -<?= number_format($totalDeductions, 2, ',', '.') ?> RSD</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5><strong>Za isplatu: <?= number_format($finalAmount, 2, ',', '.') ?> RSD</strong></h5>
                                <?php if ($payroll['paid_amount'] > 0): ?>
                                <p><strong>Uplaćeno:</strong> <?= number_format($payroll['paid_amount'], 2, ',', '.') ?> RSD</p>
                                <p><strong>Ostalo:</strong> <?= number_format($finalAmount - $payroll['paid_amount'], 2, ',', '.') ?> RSD</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require 'footer.php';
    exit;
}

// Include header for main page
require 'header.php';

// Main payroll page - list existing payrolls and create new ones
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-t');
$driverId = $_GET['driver'] ?? '';

// Fetch existing payrolls
$existingPayrolls = $pdo->query("SELECT p.id, p.driver_id, p.date_from, p.date_to, p.total_km, p.total_allowance, p.paid_amount, p.created_at, d.name AS driver_name
    FROM payrolls p 
    JOIN drivers d ON p.driver_id = d.id 
    ORDER BY p.created_at DESC 
    LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Obračun plata</h3>
    </div>
    
    <div class="row">
        <!-- Left side - Create new payroll -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><strong>Kreiraj novi obračun</strong></div>
                <div class="card-body">
                    <form method="get" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label>Vozač</label>
                            <select name="driver" class="form-select">
                                <option value="">Izaberi vozača...</option>
                                <?php foreach($drivers as $d): ?>
                                <option value="<?= $d['id']?>" <?= $driverId==$d['id']?'selected':'' ?>><?=htmlspecialchars($d['name'])?></option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Od datuma</label>
                            <input type="date" name="date_from" class="form-control" value="<?=$dateFrom?>">
                        </div>
                        <div class="col-md-3">
                            <label>Do datuma</label>
                            <input type="date" name="date_to" class="form-control" value="<?=$dateTo?>">
                        </div>
                        <div class="col-md-2 align-self-end">
                            <button class="btn btn-primary w-100">Učitaj ture</button>
                        </div>
                    </form>

                    <?php if ($driverId): 
                        // Load tours for the period
                        $stmt = $pdo->prepare("SELECT id,DATE(loading_time) AS date,delivery_type,km,allowance,loading_loc,unloading_loc FROM tours WHERE driver_id=? AND DATE(loading_time) BETWEEN ? AND ? ORDER BY loading_time");
                        $stmt->execute([$driverId,$dateFrom,$dateTo]);
                        $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if ($tours): ?>
                        <form method="post">
                            <input type="hidden" name="driver" value="<?= $driverId ?>">
                            <input type="hidden" name="date_from" value="<?= $dateFrom ?>">
                            <input type="hidden" name="date_to" value="<?= $dateTo ?>">
                            
                            <h5>Ture za obračun (<?= count($tours) ?> tura)</h5>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Datum</th>
                                            <th>Tip ture</th>
                                            <th>Relacija</th>
                                            <th class="text-end">KM</th>
                                            <th style="width: 120px;">Dnevnica (RSD)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tours as $t): ?>
                                        <tr>
                                            <td><?= $t['date'] ?></td>
                                            <td><?= $t['delivery_type'] ?></td>
                                            <td title="<?= htmlspecialchars($t['loading_loc']) ?> → <?= htmlspecialchars($t['unloading_loc']) ?>">
                                                <?= substr($t['loading_loc'], 0, 15) ?>... → <?= substr($t['unloading_loc'], 0, 15) ?>...
                                            </td>
                                            <td class="text-end"><?= $t['km'] ?></td>
                                            <td>
                                                <input type="number" step="0.01" name="allowance[<?= $t['id'] ?>]" 
                                                       value="<?= $t['allowance'] ?>" class="form-control form-control-sm">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Bonusi</h6>
                                    <button type="button" id="add_bonus" class="btn btn-success btn-sm mb-2">+ Dodaj bonus</button>
                                    <div id="bonus_items"></div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Odbitci</h6>
                                    <button type="button" id="add_deduction" class="btn btn-warning btn-sm mb-2">+ Dodaj odbitak</button>
                                    <div id="deduction_items"></div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <label>Uplaćeno na račun (RSD)</label>
                                    <input name="paid_amount" type="number" step="0.01" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-8 align-self-end">
                                    <button type="submit" name="save_payroll" class="btn btn-success">
                                        <i class="fas fa-save"></i> Sačuvaj obračun
                                    </button>
                                </div>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-info">Nema tura za izabrani period.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right side - Existing payrolls -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><strong>Poslednji obračuni</strong></div>
                <div class="card-body">
                    <?php if (empty($existingPayrolls)): ?>
                    <p class="text-muted">Nema sačuvanih obračuna.</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($existingPayrolls as $p): ?>
                        <div class="list-group-item p-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($p['driver_name']) ?></h6>
                                    <small><?= $p['date_from'] ?> - <?= $p['date_to'] ?></small><br>
                                    <small class="text-muted">
                                        <?= number_format($p['total_km'], 0, ',', '.') ?> km | 
                                        <?= number_format($p['total_allowance'], 0, ',', '.') ?> RSD
                                    </small>
                                </div>
                                <div class="btn-group-vertical btn-group-sm">
                                    <a href="?view=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm">Pregled</a>
                                    <a href="?view=<?= $p['id'] ?>&export=pdf" class="btn btn-outline-danger btn-sm" target="_blank">PDF</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add bonus item
document.getElementById('add_bonus').onclick = () => {
    const div = document.getElementById('bonus_items');
    const html = `
        <div class="input-group input-group-sm mb-2">
            <input name="bonus_reason[]" placeholder="Razlog bonusa" class="form-control">
            <input name="bonus_amount[]" type="number" step="0.01" placeholder="Iznos" class="form-control">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">×</button>
        </div>`;
    div.insertAdjacentHTML('beforeend', html);
};

// Add deduction item  
document.getElementById('add_deduction').onclick = () => {
    const div = document.getElementById('deduction_items');
    const html = `
        <div class="input-group input-group-sm mb-2">
            <input name="deduction_reason[]" placeholder="Razlog odbitka" class="form-control">
            <input name="deduction_amount[]" type="number" step="0.01" placeholder="Iznos" class="form-control">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">×</button>
        </div>`;
    div.insertAdjacentHTML('beforeend', html);
};
</script>

<?php require 'footer.php'; ?>
