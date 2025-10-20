
<?php
require 'header.php';
require 'functions.php';

date_default_timezone_set('Europe/Belgrade');
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$thisWeek = date('Y-m-d', strtotime('-7 days'));
$thisMonth = date('Y-m-d', strtotime('-30 days'));

// Fetch data for different periods
$toursToday = getTours(['from' => $today, 'to' => $today]);
$toursYesterday = getTours(['from' => $yesterday, 'to' => $yesterday]);
$toursThisWeek = getTours(['from' => $thisWeek, 'to' => $today]);
$toursThisMonth = getTours(['from' => $thisMonth, 'to' => $today]);

// Trigger estimated km update for tours without it (as fallback)
foreach($toursToday as &$tour) {
    if (empty($tour['estimated_km']) && !empty($tour['unloading_loc'])) {
        $estimatedKm = calculateEstimatedKmForTour($tour['id'], $tour['unloading_loc']);
        if ($estimatedKm > 0) {
            // Update in database for future use
            $stmt = $pdo->prepare("UPDATE tours SET estimated_km = ? WHERE id = ?");
            $stmt->execute([$estimatedKm, $tour['id']]);
            $tour['estimated_km'] = $estimatedKm;
        }
    }
}

// Statistics calculations
$totalDistance = array_sum(array_column($toursToday, 'km'));
$totalEstimatedKm = array_sum(array_column($toursToday, 'estimated_km'));
$weeklyDistance = array_sum(array_column($toursThisWeek, 'km'));
$monthlyDistance = array_sum(array_column($toursThisMonth, 'km'));

// Aggregate by vehicle and driver
$byVehicle = [];
$byDriver = [];
$estimatedKmByVehicle = [];

foreach($toursToday as $t) {
    $byVehicle[$t['vehicle']] = ($byVehicle[$t['vehicle']] ?? 0) + 1;
    $byDriver[$t['driver']] = ($byDriver[$t['driver']] ?? 0) + 1;
    $estimatedKm = $t['estimated_km'] ?? 0;
    $estimatedKmByVehicle[$t['vehicle']] = ($estimatedKmByVehicle[$t['vehicle']] ?? 0) + $estimatedKm;
}

// Get recent tours without estimated km
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE (estimated_km = 0 OR estimated_km IS NULL) AND unloading_loc IS NOT NULL AND unloading_loc != '' AND DATE(date) >= ?");
$stmt->execute([$thisWeek]);
$toursWithoutEstimatedKm = $stmt->fetchColumn();

// Get urgent notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM notifications 
    WHERE is_resolved = 0 AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute();
$urgentNotifications = $stmt->fetch()['count'];

// Get vehicle maintenance alerts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM vehicles 
    WHERE (registration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
           insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
           cmr_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
           tachograph_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
           sixmo_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
");
$stmt->execute();
$maintenanceAlerts = $stmt->fetch()['count'];



?>

<style>
:root {
  --primary-color: #10a37f;
  --secondary-color: #f7f7f8;
  --text-primary: #2d3748;
  --text-secondary: #6b7280;
  --border-color: #e5e7eb;
  --success-color: #22c55e;
  --warning-color: #f59e0b;
  --danger-color: #ef4444;
  --info-color: #3b82f6;
}

.page-header {
  background: white;
  border-bottom: 1px solid var(--border-color);
  padding: 2rem 0;
  margin-bottom: 2rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.page-subtitle {
  color: var(--text-secondary);
  font-size: 1rem;
  margin: 0.5rem 0 0 0;
}

.alert-modern {
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem 1.5rem;
  margin-bottom: 1rem;
  background: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.alert-warning { border-left: 4px solid var(--warning-color); }
.alert-danger { border-left: 4px solid var(--danger-color); }
.alert-info { border-left: 4px solid var(--info-color); }

.stat-card {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem;
  transition: all 0.2s ease;
  height: 100%;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  color: var(--primary-color);
  line-height: 1;
  margin-bottom: 0.25rem;
}

.stat-label {
  color: var(--text-primary);
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 0.25rem;
}

.stat-meta {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.card-modern {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.card-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
}

.card-title i {
  color: var(--primary-color);
  margin-right: 0.5rem;
  width: 1.25rem;
}

.btn-modern {
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  font-weight: 500;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  background: white;
  color: var(--text-primary);
}

.btn-modern:hover {
  background: var(--secondary-color);
  border-color: var(--primary-color);
  color: var(--text-primary);
  text-decoration: none;
}

.btn-modern i {
  margin-right: 0.5rem;
}

.table-modern {
  border: none;
}

.table-modern th {
  background: var(--secondary-color);
  border: none;
  font-weight: 600;
  color: var(--text-primary);
  padding: 0.75rem;
}

.table-modern td {
  border: none;
  border-bottom: 1px solid var(--border-color);
  padding: 0.75rem;
  color: var(--text-primary);
}

.badge-modern {
  background: var(--primary-color);
  color: white;
  border-radius: 0.375rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.driver-card {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 0.75rem;
  text-align: center;
  transition: all 0.2s ease;
  height: 100%;
}

.driver-card:hover {
  border-color: var(--primary-color);
  background: var(--secondary-color);
}

.driver-avatar {
  width: 3rem;
  height: 3rem;
  background: var(--primary-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.75rem;
  color: white;
  font-size: 1.25rem;
}

.driver-name {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.chart-container {
  position: relative;
  height: 250px;
  background: white;
  border-radius: 0.75rem;
  padding: 0.75rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--text-secondary);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}
</style>

<div class="page-header">
    <div class="content-container px-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Pregled aktivnosti za <?php echo date('d.m.Y', strtotime($today)); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-muted">
                    <i class="fas fa-clock me-2"></i>
                    Ažurirano: <?php echo date('H:i'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-container px-3">

<!-- Alerts Section -->
<?php if ($urgentNotifications > 0 || $maintenanceAlerts > 0 || $toursWithoutEstimatedKm > 0): ?>
<div class="row mb-3">
    <div class="col-12">
        <?php if ($urgentNotifications > 0): ?>
        <div class="alert-modern alert-warning">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-3 text-warning"></i>
                <div>
                    <strong>Imate <?php echo $urgentNotifications; ?> hitnih obaveštenja!</strong>
                    <div class="text-muted small">Dokumenti koji ističu u narednih 7 dana.</div>
                </div>
            </div>
            <a href="notifications.php" class="btn-modern btn-sm">Pogledaj</a>
        </div>
        <?php endif; ?>
        
        <?php if ($maintenanceAlerts > 0): ?>
        <div class="alert-modern alert-danger">
            <div class="d-flex align-items-center">
                <i class="fas fa-wrench me-3 text-danger"></i>
                <div>
                    <strong><?php echo $maintenanceAlerts; ?> vozila zahteva pažnju!</strong>
                    <div class="text-muted small">Blizu su datumi isteka dokumenata.</div>
                </div>
            </div>
            <a href="vehicles.php" class="btn-modern btn-sm">Pregled vozila</a>
        </div>
        <?php endif; ?>
        
        <?php if ($toursWithoutEstimatedKm > 0): ?>
        <div class="alert-modern alert-info">
            <div class="d-flex align-items-center">
                <i class="fas fa-route me-3 text-info"></i>
                <div>
                    <strong><?php echo $toursWithoutEstimatedKm; ?> tura bez procenjene kilometraže</strong>
                    <div class="text-muted small">Iz poslednih 7 dana.</div>
                </div>
            </div>
            <button onclick="updateEstimatedKm()" class="btn-modern btn-sm">Ažuriraj</button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Main Statistics -->
<div class="row mb-3">
    <div class="col-lg-3 col-md-6 mb-2">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($toursToday); ?></div>
            <div class="stat-label">Ture danas</div>
            <div class="stat-meta">Juče: <?php echo count($toursYesterday); ?></div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-2">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalDistance); ?></div>
            <div class="stat-label">km danas</div>
            <div class="stat-meta">Procenjena: <?php echo number_format($totalEstimatedKm); ?> km</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-2">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($byVehicle); ?></div>
            <div class="stat-label">Aktivnih vozila</div>
            <div class="stat-meta">Na terenu</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-2">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($byDriver); ?></div>
            <div class="stat-label">Aktivnih vozača</div>
            <div class="stat-meta">U radu</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-3">
    <div class="col-md-8">
        <div class="card-modern">
            <h5 class="card-title">
                <i class="fas fa-bolt"></i>
                Brze akcije
            </h5>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <a href="add_tour.php" class="btn-modern w-100 text-center">
                        <i class="fas fa-plus"></i>
                        Nova tura
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="import_assign_tours.php" class="btn-modern w-100 text-center">
                        <i class="fas fa-upload"></i>
                        Import tura
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="map_vehicle.php" class="btn-modern w-100 text-center">
                        <i class="fas fa-map"></i>
                        Mapa
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="report.php" class="btn-modern w-100 text-center">
                        <i class="fas fa-chart-bar"></i>
                        Izveštaji
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card-modern">
            <h5 class="card-title">
                <i class="fas fa-cog"></i>
                Upravljanje
            </h5>
            <div class="d-grid gap-2">
                <a href="vehicles.php" class="btn-modern">
                    <i class="fas fa-truck"></i>Vozila
                </a>
                <a href="drivers.php" class="btn-modern">
                    <i class="fas fa-users"></i>Vozači
                </a>
                <a href="maintenance.php" class="btn-modern">
                    <i class="fas fa-wrench"></i>Održavanje
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card-modern">
            <h5 class="card-title">
                <i class="fas fa-chart-doughnut"></i>
                Ture po vozilima danas
            </h5>
            <div class="chart-container">
                <canvas id="chartTours"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card-modern">
            <h5 class="card-title">
                <i class="fas fa-table"></i>
                Detaljni pregled
            </h5>
            <div style="max-height: 300px; overflow-y: auto;">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Vozilo</th>
                            <th class="text-center"># Tura</th>
                            <th class="text-end">Proc. km</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($byVehicle) > 0): ?>
                            <?php foreach($byVehicle as $veh => $cnt): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($veh); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge-modern"><?php echo $cnt; ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="text-success fw-bold">
                                        <?php echo number_format($estimatedKmByVehicle[$veh] ?? 0); ?> km
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">
                                    <div class="empty-state">
                                        <i class="fas fa-truck"></i>
                                        <p>Nema aktivnih vozila danas</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Drivers Section -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card-modern">
            <h5 class="card-title">
                <i class="fas fa-users"></i>
                Aktivnost vozača danas
                <?php if (count($byDriver) > 6): ?>
                    <small class="text-muted">(prikazano prvih 6)</small>
                <?php endif; ?>
            </h5>
            <?php if (count($byDriver) > 0): ?>
                <div class="row g-3">
                    <?php 
                    $driversToShow = array_slice($byDriver, 0, 6, true);
                    foreach($driversToShow as $drv => $cnt): 
                    ?>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="driver-card">
                            <div class="driver-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="driver-name" title="<?php echo htmlspecialchars($drv); ?>">
                                <?php echo strlen($drv) > 12 ? substr(htmlspecialchars($drv), 0, 12) . '...' : htmlspecialchars($drv); ?>
                            </div>
                            <span class="badge-modern"><?php echo $cnt; ?> tura</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($byDriver) > 6): ?>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="driver-card" style="border-style: dashed;">
                            <div class="driver-avatar" style="background: var(--text-secondary);">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="driver-name">
                                +<?php echo count($byDriver) - 6; ?> više
                            </div>
                            <a href="drivers.php" class="btn-modern btn-sm">Svi vozači</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Nema aktivnih vozača danas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart for tours by vehicle
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode($byVehicle); ?>;
    
    if (Object.keys(chartData).length > 0) {
        const ctx = document.getElementById('chartTours').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($byVehicle)); ?>,
                datasets: [{
                    label: 'Broj tura',
                    data: <?php echo json_encode(array_values($byVehicle)); ?>,
                    backgroundColor: [
                        '#10a37f',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444',
                        '#22c55e',
                        '#8b5cf6',
                        '#f97316',
                        '#06b6d4'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'white',
                        titleColor: '#2d3748',
                        bodyColor: '#2d3748',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' tura';
                            }
                        }
                    }
                }
            }
        });
    } else {
        const chartContainer = document.getElementById('chartTours').parentElement;
        chartContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-chart-pie"></i>
                <p>Nema podataka za prikaz</p>
            </div>
        `;
    }
});

// Function to update estimated km
function updateEstimatedKm() {
    if (confirm('Da li želite da pokrenete ažuriranje procenjene kilometraže?')) {
        fetch('update_estimated_km.php')
            .then(response => response.text())
            .then(data => {
                alert('Ažuriranje završeno!');
                location.reload();
            })
            .catch(error => {
                alert('Greška pri ažuriranju: ' + error);
            });
    }
}

// Auto refresh every 5 minutes
setTimeout(() => {
    location.reload();
}, 300000);
</script>

<?php require 'footer.php'; ?>
