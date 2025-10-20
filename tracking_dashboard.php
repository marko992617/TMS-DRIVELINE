
<?php
require 'functions.php';
require 'header.php';

// Get status counts
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM tours 
    WHERE DATE(loading_time) >= DATE(NOW()) - INTERVAL 7 DAY
    GROUP BY status
");
$stmt->execute();
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent status changes
$stmt = $pdo->prepare("
    SELECT h.*, t.tracking_number, d.name as driver_name, v.plate as vehicle_plate
    FROM tour_status_history h
    JOIN tours t ON h.tour_id = t.id
    LEFT JOIN drivers d ON t.driver_id = d.id
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    ORDER BY h.changed_at DESC
    LIMIT 15
");
$stmt->execute();
$recentChanges = $stmt->fetchAll();

// Get tours by status
$selectedStatus = $_GET['status'] ?? '';
$toursQuery = "
    SELECT t.*, d.name as driver_name, v.plate as vehicle_plate
    FROM tours t
    LEFT JOIN drivers d ON t.driver_id = d.id
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    WHERE 1
";
$params = [];

if ($selectedStatus && $selectedStatus !== 'all') {
    $toursQuery .= " AND t.status = ?";
    $params[] = $selectedStatus;
}

$toursQuery .= " ORDER BY t.status_updated_at DESC LIMIT 50";
$stmt = $pdo->prepare($toursQuery);
$stmt->execute($params);
$tours = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 1rem 1rem;
        }
        .status-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.2s ease;
            height: 100%;
        }
        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .status-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .status-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .status-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }
        .main-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        .main-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
            padding: 1.25rem;
            border-radius: 1rem 1rem 0 0 !important;
        }
        .compact-table {
            font-size: 0.875rem;
        }
        .compact-table th {
            padding: 0.75rem 0.5rem;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }
        .compact-table td {
            padding: 0.5rem;
            vertical-align: middle;
        }
        .timeline-item {
            border-left: 3px solid #e9ecef;
            padding-left: 0.75rem;
            margin-bottom: 0.75rem;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.recent::before {
            background: #28a745;
        }
        .filter-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        .badge-status {
            font-size: 0.7rem;
            padding: 0.35rem 0.6rem;
        }
        .compact-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Tracking Dashboard</h2>
                <p class="mb-0 opacity-75">Pregled stanja tura u realnom vremenu</p>
            </div>
            <div class="col-md-4 text-end">
                <small class="opacity-75">
                    <i class="fas fa-sync-alt me-1"></i>
                    Auto-refresh: 30s
                </small>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Status Overview Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-clipboard-list status-icon text-secondary"></i>
                <div class="status-number text-secondary"><?= $statusCounts['primljen_nalog'] ?? 0 ?></div>
                <div class="status-label">Primljen nalog</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-check-circle status-icon text-primary"></i>
                <div class="status-number text-primary"><?= $statusCounts['spreman_za_utovar'] ?? 0 ?></div>
                <div class="status-label">Spreman</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-truck-loading status-icon text-info"></i>
                <div class="status-number text-info"><?= $statusCounts['na_utovaru'] ?? 0 ?></div>
                <div class="status-label">Na utovaru</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-route status-icon text-warning"></i>
                <div class="status-number text-warning"><?= $statusCounts['u_putu_ka_istovaru'] ?? 0 ?></div>
                <div class="status-label">U putu</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-truck status-icon text-warning"></i>
                <div class="status-number text-warning"><?= $statusCounts['na_istovaru'] ?? 0 ?></div>
                <div class="status-label">Na istovaru</div>
            </div>
        </div>
        <div class="col-lg-1 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-check-double status-icon text-success"></i>
                <div class="status-number text-success"><?= $statusCounts['zavrseno'] ?? 0 ?></div>
                <div class="status-label">Završeno</div>
            </div>
        </div>
        <div class="col-lg-1 col-md-3 col-sm-4 col-6">
            <div class="status-card text-center">
                <i class="fas fa-exclamation-triangle status-icon text-danger"></i>
                <div class="status-number text-danger"><?= $statusCounts['problem'] ?? 0 ?></div>
                <div class="status-label">Problem</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="main-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Aktivne Ture</h5>
                    <select class="filter-select" onchange="filterByStatus(this.value)">
                        <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Sve ture</option>
                        <option value="primljen_nalog" <?= $selectedStatus === 'primljen_nalog' ? 'selected' : '' ?>>Primljen nalog</option>
                        <option value="spreman_za_utovar" <?= $selectedStatus === 'spreman_za_utovar' ? 'selected' : '' ?>>Spreman za utovar</option>
                        <option value="na_utovaru" <?= $selectedStatus === 'na_utovaru' ? 'selected' : '' ?>>Na utovaru</option>
                        <option value="u_putu_ka_istovaru" <?= $selectedStatus === 'u_putu_ka_istovaru' ? 'selected' : '' ?>>U putu</option>
                        <option value="na_istovaru" <?= $selectedStatus === 'na_istovaru' ? 'selected' : '' ?>>Na istovaru</option>
                        <option value="zavrseno" <?= $selectedStatus === 'zavrseno' ? 'selected' : '' ?>>Završeno</option>
                        <option value="problem" <?= $selectedStatus === 'problem' ? 'selected' : '' ?>>Problem</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table compact-table mb-0">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Status</th>
                                <th>Vozač</th>
                                <th>Vozilo</th>
                                <th>Ažurirano</th>
                                <th width="80">Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tours)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-search me-2"></i>Nema tura za prikazivanje
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tours as $tour): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($tour['tracking_number']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-status bg-<?= getStatusColor($tour['status']) ?>">
                                            <?= getStatusLabel($tour['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($tour['driver_name'] ?? 'Nije dodeljen') ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($tour['vehicle_plate'] ?? 'Nije dodeljeno') ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($tour['status_updated_at']): ?>
                                                <?= date('d.m H:i', strtotime($tour['status_updated_at'])) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="tour_status.php?id=<?= $tour['id'] ?>" 
                                           class="btn btn-primary compact-btn">
                                            <i class="fas fa-tasks"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="main-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Nedavne Promene</h5>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($recentChanges)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">Nema nedavnih promena</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentChanges as $index => $change): ?>
                        <div class="timeline-item <?= $index < 3 ? 'recent' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-primary mb-1">
                                        <?= htmlspecialchars($change['tracking_number']) ?>
                                    </div>
                                    <span class="badge badge-status bg-<?= getStatusColor($change['status']) ?> mb-1">
                                        <?= getStatusLabel($change['status']) ?>
                                    </span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($change['changed_by']) ?>
                                    </div>
                                    <?php if ($change['notes']): ?>
                                        <div class="small text-secondary mt-1">
                                            <?= htmlspecialchars(substr($change['notes'], 0, 50)) ?>
                                            <?= strlen($change['notes']) > 50 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted ms-2">
                                    <?= date('H:i', strtotime($change['changed_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterByStatus(status) {
    window.location.href = 'tracking_dashboard.php?status=' + status;
}

// Auto refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);

// Add loading indicator for status changes
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.querySelector('.filter-select');
    filterSelect.addEventListener('change', function() {
        this.style.opacity = '0.5';
        this.disabled = true;
    });
});
</script>

</body>
</html>
