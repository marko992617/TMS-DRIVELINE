
<?php
require 'config.php';
date_default_timezone_set('Europe/Belgrade');

// Handle form submissions
if ($_POST) {
    if (isset($_POST['resolve_notification'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_resolved = 1, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$_POST['notification_id']]);
        header('Location: notifications.php');
        exit;
    }
    
    if (isset($_POST['add_note'])) {
        $stmt = $pdo->prepare("INSERT INTO notes (title, content, priority, due_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['priority'],
            $_POST['due_date'] ?: null
        ]);
        header('Location: notifications.php');
        exit;
    }
    
    if (isset($_POST['complete_note'])) {
        $stmt = $pdo->prepare("UPDATE notes SET is_completed = 1, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$_POST['note_id']]);
        header('Location: notifications.php');
        exit;
    }
}

require 'header.php';

// Get active notifications (due in next 30 days or overdue)
$stmt = $pdo->prepare("
    SELECT n.*, v.plate 
    FROM notifications n 
    LEFT JOIN vehicles v ON n.vehicle_id = v.id 
    WHERE n.is_resolved = 0 
    AND n.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY n.due_date ASC
");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get pending notes
$stmt = $pdo->prepare("
    SELECT * FROM notes 
    WHERE is_completed = 0 
    ORDER BY 
        CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        due_date ASC
");
$stmt->execute();
$notes = $stmt->fetchAll();

// Check for expiring documents and create notifications
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();
foreach ($vehicles as $vehicle) {
    $checks = [
        ['field' => 'registration_date', 'type' => 'Registracija', 'interval' => 365],
        ['field' => 'insurance_expiry', 'type' => 'Osiguranje', 'interval' => 365],
        ['field' => 'cmr_expiry', 'type' => 'CMR', 'interval' => 365],
        ['field' => 'tachograph_expiry', 'type' => 'Tahograf', 'interval' => 730],
        ['field' => 'sixmo_expiry', 'type' => 'Šestomesečni pregled', 'interval' => 183]
    ];
    
    foreach ($checks as $check) {
        if ($vehicle[$check['field']]) {
            $expiryDate = new DateTime($vehicle[$check['field']]);
            $today = new DateTime();
            $daysUntilExpiry = $today->diff($expiryDate)->days;
            
            if ($daysUntilExpiry <= 30 && $expiryDate >= $today) {
                // Check if notification already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM notifications 
                    WHERE type = ? AND vehicle_id = ? AND due_date = ? AND is_resolved = 0
                ");
                $stmt->execute([$check['type'], $vehicle['id'], $vehicle[$check['field']]]);
                
                if (!$stmt->fetch()) {
                    // Create notification
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (type, vehicle_id, title, description, due_date)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $check['type'],
                        $vehicle['id'],
                        $check['type'] . ' ističe za vozilo ' . $vehicle['plate'],
                        'Potrebno obnoviti ' . strtolower($check['type']) . ' za vozilo ' . $vehicle['plate'],
                        $vehicle[$check['field']]
                    ]);
                }
            }
        }
    }
}
?>

<div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-bell"></i> Obaveštenja (sledeća 30 dana)</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                        <i class="fas fa-plus"></i> Nova beleška
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Nema aktuelnih obaveštenja!
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            $dueDate = new DateTime($notification['due_date']);
                            $today = new DateTime();
                            $daysLeft = $today->diff($dueDate)->days;
                            $isOverdue = $dueDate < $today;
                            $alertClass = $isOverdue ? 'alert-danger' : ($daysLeft <= 7 ? 'alert-warning' : 'alert-info');
                            ?>
                            <div class="alert <?php echo $alertClass; ?> d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                    <br>
                                    <small>
                                        <?php echo htmlspecialchars($notification['description']); ?>
                                        <br>
                                        Datum: <?php echo date('d.m.Y', strtotime($notification['due_date'])); ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger">Prošao rok!</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary"><?php echo $daysLeft; ?> dana</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="resolve_notification" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Reši
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-sticky-note"></i> Beleške za rešavanje</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notes)): ?>
                        <p class="text-muted">Nema aktivnih beleški.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-title mb-1">
                                                <?php echo htmlspecialchars($note['title']); ?>
                                                <span class="badge bg-<?php echo $note['priority'] === 'high' ? 'danger' : ($note['priority'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($note['priority']); ?>
                                                </span>
                                            </h6>
                                            <?php if ($note['content']): ?>
                                                <p class="card-text small"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($note['due_date']): ?>
                                                <small class="text-muted">Rok: <?php echo date('d.m.Y', strtotime($note['due_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                            <button type="submit" name="complete_note" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal za dodavanje beleške -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Dodaj novu belešku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Naslov</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Sadržaj</label>
                        <textarea class="form-control" id="content" name="content" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="priority" class="form-label">Prioritet</label>
                        <select class="form-control" id="priority" name="priority">
                            <option value="low">Nizak</option>
                            <option value="medium" selected>Srednji</option>
                            <option value="high">Visok</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Rok (opcionalno)</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Otkaži</button>
                    <button type="submit" name="add_note" class="btn btn-primary">Dodaj belešku</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
