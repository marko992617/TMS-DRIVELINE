<?php
require 'header.php';
require 'functions.php';

// Get filters
// Set Belgrade timezone for proper date display
date_default_timezone_set('Europe/Belgrade');
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$searchAll = isset($_GET['search_all']) && $_GET['search_all'] == '1';
$searchQuery = trim($_GET['search'] ?? '');

// Fetch tours with proper JOINs for drivers, vehicles and clients
$sql = "SELECT t.*, 
               d.name AS driver_name, 
               v.plate AS vehicle_plate,
               c.name AS client_name,
               COALESCE(ds.waybill_number, t.waybill_number) as waybill_number,
               ds.image_path,
               CASE 
                   WHEN ds.waybill_number IS NOT NULL THEN 'new'
                   WHEN t.waybill_number IS NOT NULL THEN 'old'
                   ELSE NULL
               END as waybill_source
        FROM tours t
        LEFT JOIN drivers d ON t.driver_id = d.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN clients c ON t.client_id = c.id
        LEFT JOIN driver_submissions ds ON ds.tour_id = t.id
        WHERE 1=1";

$params = [];

if (!$searchAll) {
    $sql .= " AND DATE(t.loading_time) = ?";
    $params[] = $selectedDate;
}

// Add search functionality
if (!empty($searchQuery)) {
    $sql .= " AND (
        t.id LIKE ? OR
        d.name LIKE ? OR
        v.plate LIKE ? OR
        t.loading_loc LIKE ? OR
        t.unloading_loc LIKE ? OR
        t.route LIKE ? OR
        t.delivery_type LIKE ? OR
        t.ors_id LIKE ? OR
        t.note LIKE ? OR
        ds.waybill_number LIKE ?
    )";
    $searchParam = "%{$searchQuery}%";
    for ($i = 0; $i < 10; $i++) {
        $params[] = $searchParam;
    }
}

$sql .= " ORDER BY DATE(t.loading_time) DESC, t.loading_time ASC";

global $pdo;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ture - TMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            color: #212121;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 24px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #212121;
            margin: 0;
        }

        .header-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10a37f;
            color: white;
        }

        .btn-primary:hover {
            background: #0d8968;
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
        }

        /* Search Section */
        .search-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
        }

        .form-input {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #10a37f;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        /* Tours List */
        .tours-section {
            margin: 30px 0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #212121;
        }

        .results-count {
            font-size: 14px;
            color: #6b7280;
        }

        .tours-list {
            display: grid;
            gap: 12px;
        }

        .tour-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }

        .tour-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #d1d5db;
        }

        .tour-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .tour-number {
            font-weight: 600;
            font-size: 16px;
            color: #212121;
        }

        .tour-status {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            text-transform: uppercase;
        }

        .tour-details {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 20px;
            align-items: center;
            margin-bottom: 12px;
        }

        .tour-locations {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .location {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .location-icon {
            width: 16px;
            text-align: center;
        }

        .location-from { color: #059669; }
        .location-to { color: #d97706; }

        .tour-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 14px;
        }

        .meta-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .meta-value {
            color: #374151;
        }

        .tour-assignment {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .assignment-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-secondary { background: #f3f4f6; color: #6b7280; }

        .tour-actions {
            display: flex;
            gap: 6px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-edit:hover {
            background: #fde68a;
            color: #92400e;
        }

        .btn-status {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-status:hover {
            background: #bfdbfe;
            color: #1e40af;
        }

        .btn-delete {
            background: #fecaca;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fca5a5;
            color: #dc2626;
        }
        
        /* Waybill Link Styling */
        .waybill-link {
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .waybill-link:hover {
            color: #b91c1c;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .waybill-link i {
            font-size: 12px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .empty-state i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .tour-details {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .tour-actions {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <div class="header-content">
            <h1>Ture</h1>
            <div class="header-buttons">
                <a href="add_tour.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Nova tura
                </a>
                <a href="import_assign_tours.php" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i>
                    Import
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="form-group">
                <label class="form-label">Datum</label>
                <input type="date" name="date" class="form-input" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Pretraži</label>
                <input type="text" name="search" class="form-input" 
                       value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="ID, vozač, vozilo, lokacija...">
            </div>
            
            <div class="header-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Pretraži
                </button>
                <button type="submit" name="search_all" value="1" class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    Sve
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" style="color: #10a37f"><?= count($tours) ?></div>
            <div class="stat-label">Ukupno tura</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #059669">
                <?= count(array_filter($tours, function($t) { return !empty($t['driver_name']); })) ?>
            </div>
            <div class="stat-label">Sa vozačem</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #0ea5e9">
                <?= count(array_filter($tours, function($t) { return !empty($t['vehicle_plate']); })) ?>
            </div>
            <div class="stat-label">Sa vozilom</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #d97706">
                <?= count(array_filter($tours, function($t) { return !empty($t['client_name']); })) ?>
            </div>
            <div class="stat-label">Sa klijentom</div>
        </div>
    </div>

    <!-- Tours Section -->
    <div class="tours-section">
        <div class="section-header">
            <h2 class="section-title">Lista tura</h2>
            <div class="results-count"><?= count($tours) ?> rezultata</div>
        </div>
        
        <?php if (empty($tours)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Nema rezultata</h3>
                <?php if (!empty($searchQuery)): ?>
                    <p>Nema tura koje odgovaraju pretrazi "<?= htmlspecialchars($searchQuery) ?>".</p>
                <?php elseif ($searchAll): ?>
                    <p>Nema tura u bazi podataka.</p>
                <?php else: ?>
                    <p>Nema tura za izabrani datum.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tours-list">
                <?php foreach ($tours as $t): ?>
                <div class="tour-card">
                    <div class="tour-header">
                        <div class="tour-number">
                            #<?= htmlspecialchars($t['tracking_number'] ?? 'T' . date('Y') . str_pad($t['id'], 6, '0', STR_PAD_LEFT)) ?>
                        </div>
                        <div class="tour-status" style="background: <?= getStatusHexColor($t['status'] ?? 'primljen_nalog') ?>">
                            <?= getStatusLabel($t['status'] ?? 'primljen_nalog') ?>
                        </div>
                    </div>
                    
                    <div class="tour-details">
                        <div class="tour-locations">
                            <div class="location location-from">
                                <i class="fas fa-circle location-icon"></i>
                                <?= htmlspecialchars($t['loading_loc']) ?>
                            </div>
                            <div class="location location-to">
                                <i class="fas fa-flag-checkered location-icon"></i>
                                <?= htmlspecialchars($t['unloading_loc']) ?>
                            </div>
                        </div>
                        
                        <div class="tour-meta">
                            <div class="meta-label">Datum i vreme</div>
                            <div class="meta-value">
                                <?= date('d.m.Y', strtotime($t['loading_time'])) ?><br>
                                <small style="color: #6b7280"><?= date('H:i', strtotime($t['loading_time'])) ?></small>
                            </div>
                        </div>
                        
                        <?php if (!empty($t['waybill_number'])): ?>
                        <div class="tour-meta">
                            <div class="meta-label">Tovarni list</div>
                            <div class="meta-value">
                                <?php if ($t['waybill_source'] === 'new' && !empty($t['image_path']) && file_exists(__DIR__ . '/' . $t['image_path'])): ?>
                                    <!-- Novi sistem: razduženo sa slikom i PDF -->
                                    <a href="<?= htmlspecialchars($t['image_path']) ?>" class="waybill-link" target="_blank" title="Otvori PDF tovarnog lista">
                                        <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($t['waybill_number']) ?>
                                    </a>
                                    <small style="color: #059669; display: block; margin-top: 4px;">
                                        <i class="fas fa-check-circle"></i> Razduženo sa slikom
                                    </small>
                                <?php elseif ($t['waybill_source'] === 'old'): ?>
                                    <!-- Stari sistem: direktno u tours tabeli -->
                                    <a href="export_waybill.php?id=<?= $t['id'] ?>" class="waybill-link" target="_blank" title="Izvezi tovarni list">
                                        <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($t['waybill_number']) ?>
                                    </a>
                                    <small style="color: #2563eb; display: block; margin-top: 4px;">
                                        <i class="fas fa-info-circle"></i> Razduženo (klikni za PDF)
                                    </small>
                                <?php else: ?>
                                    <!-- Ima broj ali nema PDF -->
                                    <span style="color: #6b7280;">
                                        <i class="fas fa-file-alt"></i> <?= htmlspecialchars($t['waybill_number']) ?>
                                    </span>
                                    <small style="color: #d97706; display: block; margin-top: 4px;">
                                        <i class="fas fa-clock"></i> Čeka razduženje
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="tour-assignment">
                            <?php if (!empty($t['driver_name'])): ?>
                                <div class="assignment-badge badge-success">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($t['driver_name']) ?>
                                </div>
                            <?php else: ?>
                                <div class="assignment-badge badge-warning">Bez vozača</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($t['vehicle_plate'])): ?>
                                <div class="assignment-badge badge-success">
                                    <i class="fas fa-truck"></i> <?= htmlspecialchars($t['vehicle_plate']) ?>
                                </div>
                            <?php else: ?>
                                <div class="assignment-badge badge-warning">Bez vozila</div>
                            <?php endif; ?>

                            <?php if (!empty($t['client_name'])): ?>
                                <div class="assignment-badge badge-secondary">
                                    <i class="fas fa-building"></i> <?= htmlspecialchars($t['client_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tour-actions">
                            <a href="tour_status.php?id=<?= $t['id'] ?>" class="action-btn btn-status" title="Status">
                                <i class="fas fa-tasks"></i>
                            </a>
                            <a href="edit_tour.php?id=<?= $t['id'] ?>" class="action-btn btn-edit" title="Izmeni">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete_tour.php?id=<?= $t['id'] ?>" class="action-btn btn-delete" 
                               onclick="return confirm('Da li ste sigurni da želite da obrišete ovu turu?');" title="Obriši">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($t['note'])): ?>
                    <div style="margin-top: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #6b7280;">
                        <i class="fas fa-comment"></i> <?= htmlspecialchars($t['note']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

<?php require 'footer.php'; ?>