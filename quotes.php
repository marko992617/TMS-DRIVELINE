
<?php
require 'header.php';
require 'functions.php';

// Dobij sve ponude
$stmt = $pdo->query("SELECT q.*, c.name as client_name FROM quotes q LEFT JOIN clients c ON q.client_id = c.id ORDER BY q.created_at DESC");
$quotes = $stmt->fetchAll();
?>

<div class="container-fluid mt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 h4">
                            <i class="fas fa-file-invoice me-2"></i>
                            Upravljanje ponudama
                        </h2>
                        <div class="btn-group">
                            <a href="vehicle_categories.php" class="btn btn-light btn-sm">
                                <i class="fas fa-truck me-1"></i>
                                Kategorije vozila
                            </a>
                            <a href="clients.php" class="btn btn-light btn-sm">
                                <i class="fas fa-users me-1"></i>
                                Klijenti
                            </a>
                            <a href="create_quote.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                Nova ponuda
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (count($quotes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">
                                        <i class="fas fa-hashtag me-1 text-muted"></i>
                                        Broj ponude
                                    </th>
                                    <th>
                                        <i class="fas fa-user me-1 text-muted"></i>
                                        Klijent
                                    </th>
                                    <th>
                                        <i class="fas fa-route me-1 text-muted"></i>
                                        Ruta
                                    </th>
                                    <th class="text-center">
                                        <i class="fas fa-road me-1 text-muted"></i>
                                        Kilometraža
                                    </th>
                                    <th class="text-end">
                                        <i class="fas fa-money-bill-wave me-1 text-muted"></i>
                                        Cena bez PDV
                                    </th>
                                    <th class="text-end">
                                        <i class="fas fa-calculator me-1 text-muted"></i>
                                        Ukupno sa PDV
                                    </th>
                                    <th class="text-center">
                                        <i class="fas fa-info-circle me-1 text-muted"></i>
                                        Status
                                    </th>
                                    <th class="text-center">
                                        <i class="fas fa-calendar me-1 text-muted"></i>
                                        Datum
                                    </th>
                                    <th class="text-center pe-4">
                                        <i class="fas fa-cogs me-1 text-muted"></i>
                                        Akcije
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotes as $quote): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4">
                                        <strong class="text-primary"><?= htmlspecialchars($quote['quote_number']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="fas fa-building text-muted"></i>
                                            </div>
                                            <span><?= htmlspecialchars($quote['client_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($quote['route_summary']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info text-dark">
                                            <?= number_format($quote['total_km'], 0) ?> km
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">
                                            <?= number_format($quote['price_without_vat'], 2) ?>
                                            <small class="text-muted">RSD</small>
                                        </strong>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-primary">
                                            <?= number_format($quote['total_price'], 2) ?>
                                            <small class="text-muted">RSD</small>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $statusColor = 'secondary';
                                        $statusText = 'Draft';
                                        if ($quote['status'] === 'sent') {
                                            $statusColor = 'primary';
                                            $statusText = 'Poslato';
                                        } elseif ($quote['status'] === 'accepted') {
                                            $statusColor = 'success';
                                            $statusText = 'Prihvaćeno';
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?= date('d.m.Y', strtotime($quote['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="btn-group" role="group">
                                            <a href="edit_quote.php?id=<?= $quote['id'] ?>" 
                                               class="btn btn-outline-warning btn-sm" 
                                               title="Izmeni ponudu">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="quote_pdf.php?id=<?= $quote['id'] ?>" 
                                               class="btn btn-outline-info btn-sm" 
                                               target="_blank"
                                               title="Prikaži PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            <a href="delete_quote.php?id=<?= $quote['id'] ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('Da li ste sigurni da želite da obrišete ovu ponudu?')"
                                               title="Obriši ponudu">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-file-invoice" style="font-size: 4rem; color: #dee2e6;"></i>
                        </div>
                        <h5 class="text-muted mb-3">Nema kreiranih ponuda</h5>
                        <p class="text-muted mb-4">Počnite sa kreiranjem nove ponude za vaše klijente.</p>
                        <a href="create_quote.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Kreiraj prvu ponudu
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (count($quotes) > 0): ?>
                <div class="card-footer bg-light border-top">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-chart-bar me-1"></i>
                                Ukupno ponuda: <strong><?= count($quotes) ?></strong>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted">
                                Ukupna vrednost: 
                                <strong class="text-success">
                                    <?= number_format(array_sum(array_column($quotes, 'total_price')), 2) ?> RSD
                                </strong>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.table th {
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
    padding: 1rem 0.75rem;
}

.table td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.btn-group .btn {
    border-radius: 6px;
    margin: 0 1px;
    transition: all 0.2s ease;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.75rem;
    padding: 0.5em 0.75em;
    font-weight: 500;
}

.border-bottom:last-child {
    border-bottom: none !important;
}

.bg-light.rounded-circle {
    border: 1px solid #e9ecef;
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
    
    .card-header .btn-group {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<?php require 'footer.php'; ?>
