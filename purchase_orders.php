<?php
require 'header.php';
require 'functions.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF validation function
function validateCSRF($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
        $errorMessage = "Nedozvoljeni zahtev. Molimo pokušajte ponovo.";
    } else {
        // All POST handlers go inside CSRF validation
        if (isset($_POST['create_purchase_order'])) {
        $selectedTours = $_POST['selected_tours'] ?? [];
        $orderNumber = trim($_POST['order_number'] ?? '');
        $isManual = !empty($orderNumber);
        
        // Server-side validation for manual order numbers
        if ($isManual) {
            // Validate format: exactly 10 digits
            if (!preg_match('/^\d{10}$/', $orderNumber)) {
                $errorMessage = "Broj nabavnog naloga mora imati tačno 10 cifara.";
            } else {
                // Check uniqueness in database
                $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE order_number = ?");
                $stmt->execute([$orderNumber]);
                if ($stmt->fetch()) {
                    $errorMessage = "Nabavni nalog sa brojem {$orderNumber} već postoji.";
                }
            }
        }
        
        if (!isset($errorMessage) && !empty($selectedTours)) {
            try {
                $purchaseOrderId = createPurchaseOrder($orderNumber ?: null, $isManual, $_SESSION['username'] ?? 'Admin');
                if ($purchaseOrderId && addToursTosPurchaseOrder($purchaseOrderId, $selectedTours)) {
                    $successMessage = "Nabavni nalog je uspešno kreiran!";
                } else {
                    $errorMessage = "Greška pri kreiranju nabavnog naloga. ID: $purchaseOrderId";
                }
            } catch (Exception $e) {
                $errorMessage = "Greška: " . $e->getMessage();
            }
        } elseif (!isset($errorMessage)) {
            $errorMessage = "Morate odabrati najmanje jednu turu.";
        }
        } // Close create_purchase_order block
        
        if (isset($_POST['remove_tour_from_order'])) {
            $tourId = (int)$_POST['tour_id'];
            $purchaseOrderId = (int)$_POST['purchase_order_id'];
            
            if (removeTourFromPurchaseOrder($purchaseOrderId, $tourId)) {
                $successMessage = "Tura je uklonjena iz nabavnog naloga.";
            } else {
                $errorMessage = "Greška pri uklanjanju ture.";
            }
        }
        
        if (isset($_POST['delete_purchase_order'])) {
            $purchaseOrderId = (int)$_POST['purchase_order_id'];
            
            if (deletePurchaseOrder($purchaseOrderId)) {
                $successMessage = "Nabavni nalog je uspešno obrisan!";
            } else {
                $errorMessage = "Greška pri brisanju nabavnog naloga.";
            }
        }
        
        if (isset($_POST['edit_purchase_order'])) {
            $purchaseOrderId = (int)$_POST['purchase_order_id'];
            $newOrderNumber = trim($_POST['new_order_number']);
            
            // Validate new order number
            if (!preg_match('/^\d{10}$/', $newOrderNumber)) {
                $errorMessage = "Broj nabavnog naloga mora imati tačno 10 cifara.";
            } else {
                // Check uniqueness (excluding current order)
                $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE order_number = ? AND id != ?");
                $stmt->execute([$newOrderNumber, $purchaseOrderId]);
                if ($stmt->fetch()) {
                    $errorMessage = "Nabavni nalog sa brojem {$newOrderNumber} već postoji.";
                } else {
                    if (updatePurchaseOrderNumber($purchaseOrderId, $newOrderNumber)) {
                        $successMessage = "Broj nabavnog naloga je uspešno ažuriran!";
                    } else {
                        $errorMessage = "Greška pri ažuriranju broja nabavnog naloga.";
                    }
                }
            }
        }
    } // Close CSRF validation
}

// Get filters
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$datePeriod = $_GET['date_period'] ?? '';
$clientId = $_GET['client_id'] ?? '';
$showWithoutOrders = isset($_GET['without_orders']) && $_GET['without_orders'] == '1';

// Build filters array
$filters = [];
if (!empty($fromDate)) {
    $filters['from_date'] = $fromDate;
}
if (!empty($toDate)) {
    $filters['to_date'] = $toDate;
}
if (!empty($datePeriod)) {
    $filters['date_period'] = $datePeriod;
}
if ($showWithoutOrders) {
    $filters['has_purchase_order'] = false;
}
if (!empty($clientId)) {
    $filters['client_id'] = $clientId;
}

// Get tours
$tours = getToursForPurchaseOrder($filters);

// Get existing purchase orders
$purchaseOrders = getPurchaseOrders();

// Get all clients for filter
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nabavni nalozi - TMS</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

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
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #212121;
            margin: 0;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            min-width: 120px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .btn-edit:hover {
            background: #d97706;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        .order-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tours-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }

        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #212121;
        }

        .tours-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tours-table th,
        .tours-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .tours-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #374151;
        }

        .tours-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-primljen { background: #dbeafe; color: #1e40af; }
        .status-spreman { background: #e0f2fe; color: #0277bd; }
        .status-utovar { background: #fef3c7; color: #d97706; }
        .status-put { background: #fed7aa; color: #ea580c; }
        .status-istovar { background: #fde68a; color: #ca8a04; }
        .status-zavrseno { background: #d1fae5; color: #065f46; }
        .status-problem { background: #fecaca; color: #dc2626; }

        .purchase-order-number {
            background: #e0f2fe;
            color: #0277bd;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 16px 0;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fecaca;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .create-order-section {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .order-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: end;
        }

        .orders-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }

        .order-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .order-number {
            font-weight: 600;
            color: #212121;
        }

        .order-details {
            font-size: 14px;
            color: #6b7280;
        }

        .loading-section {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .tours-table {
                font-size: 12px;
            }
            
            .tours-table th,
            .tours-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <div class="header-content">
            <h1><i class="fas fa-file-invoice"></i> Nabavni nalozi</h1>
            <div style="display: flex; gap: 8px;">
                <a href="tours.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Nazad na ture
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    
    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Od datuma:</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" class="form-control">
            </div>
            
            <div class="filter-group">
                <label>Do datuma:</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" class="form-control">
            </div>
            
            <div class="filter-group">
                <label>Period u mesecu:</label>
                <select name="date_period" class="form-control">
                    <option value="">Svi dani</option>
                    <option value="first_half" <?= $datePeriod == 'first_half' ? 'selected' : '' ?>>1. - 15. dan</option>
                    <option value="second_half" <?= $datePeriod == 'second_half' ? 'selected' : '' ?>>16. - 30/31. dan</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Klijent:</label>
                <select name="client_id" class="form-control">
                    <option value="">Svi klijenti</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="without_orders" name="without_orders" value="1" <?= $showWithoutOrders ? 'checked' : '' ?>>
                <label for="without_orders">Samo ture bez nabavnog naloga</label>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtriraj
            </button>
            
            <a href="purchase_orders.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Očisti
            </a>
        </form>
    </div>

    <!-- Tours Section -->
    <div class="tours-section">
        <div class="section-header">
            <h2 class="section-title">
                Ture (<?= count($tours) ?>)
                <?php if ($showWithoutOrders): ?>
                    - Bez nabavnog naloga
                <?php endif; ?>
            </h2>
            <div>
                <button type="button" id="selectAll" class="btn btn-secondary">
                    <i class="fas fa-check-square"></i> Označi sve
                </button>
                <button type="button" id="selectNone" class="btn btn-secondary">
                    <i class="fas fa-square"></i> Poništi sve
                </button>
            </div>
        </div>
        
        <?php if (empty($tours)): ?>
            <div class="loading-section">
                <i class="fas fa-inbox fa-3x" style="margin-bottom: 16px; opacity: 0.3;"></i>
                <p>Nema tura za prikazane filtere.</p>
            </div>
        <?php else: ?>
            <!-- Jedna forma za sve -->
            <form method="POST" id="mainForm" onsubmit="return validateOrderForm()">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <table class="tours-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="masterCheckbox">
                            </th>
                            <th>ID</th>
                            <th>Datum</th>
                            <th>Vozač</th>
                            <th>Vozilo</th>
                            <th>Br. tovarnog lista</th>
                            <th>Klijent</th>
                            <th>Utovar</th>
                            <th>Istovar</th>
                            <th>Status</th>
                            <th>Nabavni nalog</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tours as $tour): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_tours[]" value="<?= $tour['id'] ?>" class="tour-checkbox">
                                </td>
                                <td><?= $tour['id'] ?></td>
                                <td><?= date('d.m.Y', strtotime($tour['date'])) ?></td>
                                <td><?= htmlspecialchars($tour['driver'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tour['vehicle'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tour['waybill_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($tour['client_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($tour['loading_loc'], 0, 30)) ?><?= strlen($tour['loading_loc']) > 30 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars(substr($tour['unloading_loc'], 0, 40)) ?><?= strlen($tour['unloading_loc']) > 40 ? '...' : '' ?></td>
                                <td>
                                    <span class="status-badge status-<?= getStatusBadgeClass($tour['status']) ?>">
                                        <?= getStatusLabel($tour['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tour['purchase_order_number']): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="purchase-order-number"><?= htmlspecialchars($tour['purchase_order_number']) ?></span>
                                            <!-- Posebna forma za uklanjanje -->
                                            <button onclick="removeTourFromOrder(<?= $tour['id'] ?>, <?= $tour['purchase_order_id'] ?>)" class="btn btn-danger" style="padding: 2px 6px; font-size: 12px;" title="Ukloni turu iz nabavnog naloga">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6b7280; font-style: italic;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Create Purchase Order Section unutar iste forme -->
                <div class="create-order-section">
                    <h3 style="margin-bottom: 16px;">Kreiraj nabavni nalog</h3>
                    <div class="order-form">
                        <div class="filter-group">
                            <label>Broj nabavnog naloga (opciono):</label>
                            <input type="text" name="order_number" placeholder="Unesite broj ili ostavite prazno za automatsko generisanje" class="form-control" style="min-width: 300px;" pattern="[0-9]{10}" title="Broj mora imati tačno 10 cifara">
                        </div>
                        
                        <button type="submit" name="create_purchase_order" class="btn btn-success">
                            <i class="fas fa-plus"></i> Kreiraj nalog
                        </button>
                    </div>
                    <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                        Odaberite ture iznad i kliknite "Kreiraj nalog" da napravite nabavni nalog sa 10-cifrenim brojem.
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Sekcija postojećih naloga ostaje van forme -->
    <?php if (!empty($tours)): ?>
    <?php endif; ?>

    <!-- Existing Purchase Orders -->
    <?php if (!empty($purchaseOrders)): ?>
    <div class="orders-list">
        <div class="section-header">
            <h2 class="section-title">Postojeći nabavni nalozi (<?= count($purchaseOrders) ?>)</h2>
        </div>
        
        <?php foreach ($purchaseOrders as $order): ?>
            <div class="order-item">
                <div class="order-info">
                    <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                    <div class="order-details">
                        Kreiran: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?> | 
                        Autor: <?= htmlspecialchars($order['created_by']) ?> | 
                        Tura: <?= $order['total_tours'] ?> | 
                        <?= $order['is_manual'] ? 'Manuelno unet' : 'Automatski generisan' ?>
                    </div>
                </div>
                <div class="order-actions">
                    <span class="status-badge status-<?= $order['status'] == 'active' ? 'spreman' : 'zavrseno' ?>">
                        <?= $order['status'] == 'active' ? 'Aktivan' : ucfirst($order['status']) ?>
                    </span>
                    
                    <!-- Edit Order Number -->
                    <button type="button" class="btn btn-edit" onclick="editOrderNumber(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <!-- Delete Order -->
                    <button onclick="deletePurchaseOrder(<?= $order['id'] ?>)" class="btn btn-delete" title="Obriši nabavni nalog">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const masterCheckbox = document.getElementById('masterCheckbox');
    const tourCheckboxes = document.querySelectorAll('.tour-checkbox');
    const selectAllBtn = document.getElementById('selectAll');
    const selectNoneBtn = document.getElementById('selectNone');

    // Master checkbox functionality
    masterCheckbox?.addEventListener('change', function() {
        tourCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Update master checkbox when individual checkboxes change
    tourCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.tour-checkbox:checked').length;
            masterCheckbox.checked = checkedCount === tourCheckboxes.length;
            masterCheckbox.indeterminate = checkedCount > 0 && checkedCount < tourCheckboxes.length;
        });
    });

    // Select all button
    selectAllBtn?.addEventListener('click', function() {
        tourCheckboxes.forEach(checkbox => checkbox.checked = true);
        masterCheckbox.checked = true;
        masterCheckbox.indeterminate = false;
    });

    // Select none button
    selectNoneBtn?.addEventListener('click', function() {
        tourCheckboxes.forEach(checkbox => checkbox.checked = false);
        masterCheckbox.checked = false;
        masterCheckbox.indeterminate = false;
    });
});

// Edit order number functionality
function editOrderNumber(orderId, currentNumber) {
    const newNumber = prompt('Unesite novi 10-cifreni broj nabavnog naloga:', currentNumber);
    
    if (newNumber === null) {
        return; // User cancelled
    }
    
    if (!/^\d{10}$/.test(newNumber)) {
        alert('Broj nabavnog naloga mora imati tačno 10 cifara!');
        return;
    }
    
    if (newNumber === currentNumber) {
        alert('Novi broj je isti kao trenutni!');
        return;
    }
    
    if (confirm(`Da li ste sigurni da želite da promenite broj sa ${currentNumber} na ${newNumber}?`)) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="purchase_order_id" value="${orderId}">
            <input type="hidden" name="new_order_number" value="${newNumber}">
            <input type="hidden" name="edit_purchase_order" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Remove tour from order functionality
function removeTourFromOrder(tourId, purchaseOrderId) {
    if (confirm('Da li ste sigurni da želite da uklonite ovu turu iz nabavnog naloga?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="tour_id" value="${tourId}">
            <input type="hidden" name="purchase_order_id" value="${purchaseOrderId}">
            <input type="hidden" name="remove_tour_from_order" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete purchase order functionality
function deletePurchaseOrder(orderId) {
    if (confirm('Da li ste sigurni da želite da obrišete ovaj nabavni nalog? Ovo će ukloniti nalog sa svih povezanih tura.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="purchase_order_id" value="${orderId}">
            <input type="hidden" name="delete_purchase_order" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function validateOrderForm() {
    const checkedBoxes = document.querySelectorAll('.tour-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Morate odabrati najmanje jednu turu!');
        return false;
    }
    
    const orderNumber = document.querySelector('input[name="order_number"]').value;
    if (orderNumber && !/^\d{10}$/.test(orderNumber)) {
        alert('Broj nabavnog naloga mora imati tačno 10 cifara!');
        return false;
    }
    
    return confirm(`Da li ste sigurni da želite da kreirate nabavni nalog za ${checkedBoxes.length} tura?`);
}
</script>

</body>
</html>

<?php require 'footer.php'; ?>