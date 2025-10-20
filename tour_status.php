<?php
require 'functions.php';

$tour_id = $_GET['id'] ?? null;
if (!$tour_id) {
    header('Location: tours.php');
    exit;
}

// Get tour details
$stmt = $pdo->prepare("SELECT t.*, d.name as driver_name, v.plate as vehicle_plate, c.name as client_name
                      FROM tours t 
                      LEFT JOIN drivers d ON t.driver_id = d.id 
                      LEFT JOIN vehicles v ON t.vehicle_id = v.id 
                      LEFT JOIN clients c ON t.client_id = c.id
                      WHERE t.id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();

if (!$tour) {
    header('Location: tours.php');
    exit;
}

require 'header.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    $problemDescription = $_POST['problem_description'] ?? null;
    $changedBy = $_SESSION['username'] ?? 'Admin';
    
    if ($newStatus === 'problem' && empty($problemDescription)) {
        $error = 'Opis problema je obavezan kada se postavlja status "Problem"';
    } else {
        if (updateTourStatus($tour_id, $newStatus, $changedBy, $notes, $problemDescription)) {
            $success = 'Status ture je uspešno ažuriran';
            // Refresh tour data
            $stmt->execute([$tour_id]);
            $tour = $stmt->fetch();
        } else {
            $error = 'Greška pri ažuriranju statusa';
        }
    }
}

$statusHistory = getTourStatusHistory($tour_id);
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Ture - <?= htmlspecialchars($tour['tracking_number']) ?></title>
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

        .back-btn {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8f9fa;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #212121;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 24px;
        }

        .tour-status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: white;
            text-transform: uppercase;
            margin-left: auto;
        }

        .tour-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
        }

        .info-value {
            font-size: 16px;
            color: #212121;
        }

        .locations-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .location {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 12px 0;
            font-size: 15px;
        }

        .location-icon {
            width: 20px;
            text-align: center;
        }

        .location-from { color: #059669; }
        .location-to { color: #d97706; }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-danger {
            background: #fecaca;
            color: #dc2626;
            border-color: #fca5a5;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10a37f;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10a37f;
            color: white;
        }

        .btn-primary:hover {
            background: #0d8968;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        /* Status History */
        .timeline {
            position: relative;
        }

        .timeline-item {
            padding: 16px 0;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .timeline-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            text-transform: uppercase;
        }

        .timeline-date {
            font-size: 12px;
            color: #6b7280;
        }

        .timeline-user {
            font-size: 13px;
            color: #6b7280;
            margin: 4px 0;
        }

        .timeline-notes {
            font-size: 14px;
            color: #374151;
            margin-top: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 32px;
            margin-bottom: 12px;
            color: #d1d5db;
        }

        /* Hidden section */
        .hidden {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .tour-info-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <div class="header-content">
            <h1>Status Ture #<?= htmlspecialchars($tour['tracking_number'] ?? $tour['id']) ?></h1>
            <a href="tours.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Nazad na listu
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="main-content">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Main Tour Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-route"></i>
                        Informacije o turi
                    </h2>
                    <div class="tour-status-badge" style="background: <?= getStatusHexColor($tour['status']) ?>">
                        <?= getStatusLabel($tour['status']) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tour-info-grid">
                        <div class="info-item">
                            <div class="info-label">Datum</div>
                            <div class="info-value"><?= date('d.m.Y', strtotime($tour['date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Vreme utovara</div>
                            <div class="info-value"><?= $tour['loading_time'] ? date('H:i', strtotime($tour['loading_time'])) : 'N/A' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Vozač</div>
                            <div class="info-value"><?= htmlspecialchars($tour['driver_name'] ?? 'Nije dodeljen') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Vozilo</div>
                            <div class="info-value"><?= htmlspecialchars($tour['vehicle_plate'] ?? 'Nije dodeljeno') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Klijent</div>
                            <div class="info-value"><?= htmlspecialchars($tour['client_name'] ?? 'Nije dodeljen') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">ORS ID</div>
                            <div class="info-value"><?= htmlspecialchars($tour['ors_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tip dostave</div>
                            <div class="info-value"><?= htmlspecialchars($tour['delivery_type'] ?? 'N/A') ?></div>
                        </div>
                    </div>

                    <div class="locations-section">
                        <div class="location location-from">
                            <i class="fas fa-circle location-icon"></i>
                            <strong>Utovar:</strong> <?= htmlspecialchars($tour['loading_loc'] ?? '') ?>
                        </div>
                        <div class="location location-to">
                            <i class="fas fa-flag-checkered location-icon"></i>
                            <strong>Istovar:</strong> <?= nl2br(htmlspecialchars($tour['unloading_loc'] ?? '')) ?>
                        </div>
                    </div>

                    <?php if ($tour['status'] === 'problem' && $tour['problem_description']): ?>
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Opis problema:</strong><br>
                            <?= nl2br(htmlspecialchars($tour['problem_description'])) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Novi status</label>
                            <select name="status" class="form-select" id="statusSelect" required>
                                <option value="">Izaberite status</option>
                                <option value="primljen_nalog" <?= $tour['status'] === 'primljen_nalog' ? 'selected' : '' ?>>Primljen nalog</option>
                                <option value="spreman_za_utovar" <?= $tour['status'] === 'spreman_za_utovar' ? 'selected' : '' ?>>Spreman za utovar</option>
                                <option value="na_utovaru" <?= $tour['status'] === 'na_utovaru' ? 'selected' : '' ?>>Na utovaru</option>
                                <option value="u_putu_ka_istovaru" <?= $tour['status'] === 'u_putu_ka_istovaru' ? 'selected' : '' ?>>U putu ka istovaru</option>
                                <option value="na_istovaru" <?= $tour['status'] === 'na_istovaru' ? 'selected' : '' ?>>Na istovaru</option>
                                <option value="zavrseno" <?= $tour['status'] === 'zavrseno' ? 'selected' : '' ?>>Završeno</option>
                                <option value="problem" <?= $tour['status'] === 'problem' ? 'selected' : '' ?>>Problem</option>
                            </select>
                        </div>

                        <div class="form-group hidden" id="problemDescriptionDiv">
                            <label class="form-label">Opis problema</label>
                            <textarea name="problem_description" class="form-textarea" rows="3" placeholder="Opišite problem..."><?= htmlspecialchars($tour['problem_description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Napomena</label>
                            <textarea name="notes" class="form-textarea" rows="2" placeholder="Dodatne napomene..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Ažuriraj Status
                            </button>
                            <a href="edit_tour.php?id=<?= $tour['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-edit"></i>
                                Izmeni turu
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Status History -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Istorija statusa
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($statusHistory)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clock"></i>
                            <p>Nema zabeleženih promena statusa.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-header">
                                        <span class="timeline-badge" style="background: <?= getStatusHexColor($history['status']) ?>">
                                            <?= getStatusLabel($history['status']) ?>
                                        </span>
                                        <span class="timeline-date">
                                            <?= date('d.m.Y H:i', strtotime($history['changed_at'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($history['changed_by']): ?>
                                        <div class="timeline-user">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($history['changed_by']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($history['notes']): ?>
                                        <div class="timeline-notes">
                                            <?= nl2br(htmlspecialchars($history['notes'])) ?>
                                        </div>
                                    <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('statusSelect');
    const problemDiv = document.getElementById('problemDescriptionDiv');
    
    function toggleProblemDescription() {
        if (statusSelect.value === 'problem') {
            problemDiv.classList.remove('hidden');
            problemDiv.querySelector('textarea').required = true;
        } else {
            problemDiv.classList.add('hidden');
            problemDiv.querySelector('textarea').required = false;
        }
    }
    
    statusSelect.addEventListener('change', toggleProblemDescription);
    toggleProblemDescription(); // Initial check
});
</script>

</body>
</html>

<?php require 'footer.php'; ?>