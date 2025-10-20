
<?php
require 'header.php';
require 'functions.php';

// Get all categories first
$categories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $stmt = $pdo->prepare("INSERT INTO vehicle_categories (name, fuel_cost_per_km, amortization_per_km, earnings_per_km, frigo_cost_per_km, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['fuel_cost_per_km'],
            $_POST['amortization_per_km'],
            $_POST['earnings_per_km'],
            $_POST['frigo_cost_per_km'],
            $_POST['description']
        ]);
        $success = "Kategorija vozila je uspešno dodana.";
    }

    if (isset($_POST['update_category'])) {
        $stmt = $pdo->prepare("UPDATE vehicle_categories SET name=?, fuel_cost_per_km=?, amortization_per_km=?, earnings_per_km=?, frigo_cost_per_km=?, description=? WHERE id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['fuel_cost_per_km'],
            $_POST['amortization_per_km'],
            $_POST['earnings_per_km'],
            $_POST['frigo_cost_per_km'],
            $_POST['description'],
            $_POST['category_id']
        ]);
        $success = "Kategorija vozila je uspešno ažurirana.";
    }

    if (isset($_POST['delete_category'])) {
        $stmt = $pdo->prepare("DELETE FROM vehicle_categories WHERE id=?");
        $stmt->execute([$_POST['category_id']]);
        $success = "Kategorija vozila je obrisana.";
    }
}

// Get all categories
$categories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();
?>

<div class="container-fluid mt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 h4">
                            <i class="fas fa-truck me-2"></i>
                            Upravljanje kategorijama vozila
                        </h2>
                        <div class="btn-group">
                            <a href="quotes.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Nazad na ponude
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

                <div class="card-body p-4">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-bottom">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-list me-2"></i>
                                        Postojeće kategorije
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($categories) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4">
                                                        <i class="fas fa-tag me-1 text-muted"></i>
                                                        Naziv
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-gas-pump me-1 text-muted"></i>
                                                        Gorivo/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-chart-line me-1 text-muted"></i>
                                                        Amortizacija/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-money-bill-wave me-1 text-muted"></i>
                                                        Zarada/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-snowflake me-1 text-muted"></i>
                                                        Frigo/km
                                                    </th>
                                                    <th>
                                                        <i class="fas fa-info-circle me-1 text-muted"></i>
                                                        Opis
                                                    </th>
                                                    <th class="text-center pe-4">
                                                        <i class="fas fa-cogs me-1 text-muted"></i>
                                                        Akcije
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $cat): ?>
                                                <tr class="border-bottom">
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                                <i class="fas fa-truck text-muted"></i>
                                                            </div>
                                                            <strong class="text-primary"><?= htmlspecialchars($cat['name']) ?></strong>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning text-dark">
                                                            <?= number_format($cat['fuel_cost_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info text-dark">
                                                            <?= number_format($cat['amortization_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success">
                                                            <?= number_format($cat['earnings_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary">
                                                            <?= number_format($cat['frigo_cost_per_km'] ?? 0, 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '') ?></small>
                                                    </td>
                                                    <td class="text-center pe-4">
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                                    onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                                                    title="Izmeni kategoriju">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" style="display: inline;"
                                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovu kategoriju?')">
                                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                                <button type="submit" name="delete_category" class="btn btn-outline-danger btn-sm"
                                                                        title="Obriši kategoriju">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                                            <i class="fas fa-truck" style="font-size: 4rem; color: #dee2e6;"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">Nema definisanih kategorija vozila</h5>
                                        <p class="text-muted mb-0">Počnite sa kreiranjem nove kategorije vozila.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-bottom">
                                    <h5 class="mb-0 text-primary" id="form-title">
                                        <i class="fas fa-plus me-2"></i>
                                        Dodaj novu kategoriju
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="category-form">
                                        <input type="hidden" name="category_id" id="category_id">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Naziv kategorije *</label>
                                            <input type="text" name="name" id="name" class="form-control" required
                                                   placeholder="npr. Kamion do 3.5t, Kombi, itd.">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Potrošnja goriva po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="fuel_cost_per_km" id="fuel_cost_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Koliko RSD košta gorivo po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Amortizacija po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="amortization_per_km" id="amortization_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Trošak amortizacije vozila po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Zarada po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="earnings_per_km" id="earnings_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Profit po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Doplata za frigo po km</label>
                                            <div class="input-group">
                                                <input type="number" name="frigo_cost_per_km" id="frigo_cost_per_km"
                                                       class="form-control" step="0.01" value="0">
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Dodatni trošak po kilometru za frigo jedinicu</small>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-semibold">Opis</label>
                                            <textarea name="description" id="description" class="form-control" rows="3"
                                                      placeholder="Dodatne informacije o kategoriji"></textarea>
                                        </div>

                                        <button type="submit" name="add_category" id="submit-btn" class="btn btn-success w-100">
                                            <i class="fas fa-save me-2"></i>Dodaj kategoriju
                                        </button>
                                        <button type="button" id="cancel-btn" class="btn btn-secondary w-100 mt-2"
                                                onclick="resetForm()" style="display: none;">
                                            <i class="fas fa-times me-2"></i>Otkaži
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-header bg-light border-bottom">
                                    <h6 class="mb-0 text-primary">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Pomoć pri unosu
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <small>
                                        <strong>Saveti:</strong><br>
                                        • <strong>Gorivo/km:</strong> Izračunajte na osnovu potrošnje i cene goriva<br>
                                        • <strong>Amortizacija/km:</strong> Godišnja amortizacija vozila / godišnja kilometraža<br>
                                        • <strong>Zarada/km:</strong> Željena marža/profit po kilometru<br>
                                        • <strong>Doplata za frigo/km:</strong> Dodatni trošak po kilometru za frigo jedinicu<br>
                                        <br>
                                        <div class="bg-light p-3 rounded">
                                            <strong>Primer za kombi:</strong><br>
                                            Gorivo: 25 RSD/km<br>
                                            Amortizacija: 15 RSD/km<br>
                                            Zarada: 20 RSD/km<br>
                                            Doplata za frigo: 10 RSD/km<br>
                                            = <strong class="text-success">70 RSD/km ukupno</strong>
                                        </div>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (count($categories) > 0): ?>
                <div class="card-footer bg-light border-top">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-chart-bar me-1"></i>
                                Ukupno kategorija: <strong><?= count($categories) ?></strong>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted">
                                Prosečna cena po km: 
                                <strong class="text-success">
                                    <?php 
                                    $avgCost = array_sum(array_map(function($cat) {
                                        return $cat['fuel_cost_per_km'] + $cat['amortization_per_km'] + $cat['earnings_per_km'];
                                    }, $categories)) / count($categories);
                                    echo number_format($avgCost, 2);
                                    ?> RSD
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
    color: white !important;
}

.card-header * {
    color: white !important;
}

.card-header .btn {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
}

.card-header .btn:hover {
    background-color: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
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

<script>
function editCategory(category) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit me-2"></i>Izmeni kategoriju';
    document.getElementById('category_id').value = category.id;
    document.getElementById('name').value = category.name;
    document.getElementById('fuel_cost_per_km').value = category.fuel_cost_per_km;
    document.getElementById('amortization_per_km').value = category.amortization_per_km;
    document.getElementById('earnings_per_km').value = category.earnings_per_km;
    document.getElementById('frigo_cost_per_km').value = category.frigo_cost_per_km || 0;
    document.getElementById('description').value = category.description || '';

    document.getElementById('submit-btn').name = 'update_category';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save me-2"></i>Ažuriraj kategoriju';
    document.getElementById('submit-btn').className = 'btn btn-primary w-100';
    document.getElementById('cancel-btn').style.display = 'block';
}

function resetForm() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus me-2"></i>Dodaj novu kategoriju';
    document.getElementById('category-form').reset();
    document.getElementById('category_id').value = '';

    document.getElementById('submit-btn').name = 'add_category';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save me-2"></i>Dodaj kategoriju';
    document.getElementById('submit-btn').className = 'btn btn-success w-100';
    document.getElementById('cancel-btn').style.display = 'none';
}
</script>

<?php require 'footer.php'; ?>
<?php
require 'header.php';
require 'functions.php';

// Get all categories first
$categories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $stmt = $pdo->prepare("INSERT INTO vehicle_categories (name, fuel_cost_per_km, amortization_per_km, earnings_per_km, frigo_cost_per_km, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['fuel_cost_per_km'],
            $_POST['amortization_per_km'],
            $_POST['earnings_per_km'],
            $_POST['frigo_cost_per_km'],
            $_POST['description']
        ]);
        $success = "Kategorija vozila je uspešno dodana.";
    }

    if (isset($_POST['update_category'])) {
        $stmt = $pdo->prepare("UPDATE vehicle_categories SET name=?, fuel_cost_per_km=?, amortization_per_km=?, earnings_per_km=?, frigo_cost_per_km=?, description=? WHERE id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['fuel_cost_per_km'],
            $_POST['amortization_per_km'],
            $_POST['earnings_per_km'],
            $_POST['frigo_cost_per_km'],
            $_POST['description'],
            $_POST['category_id']
        ]);
        $success = "Kategorija vozila je uspešno ažurirana.";
    }

    if (isset($_POST['delete_category'])) {
        $stmt = $pdo->prepare("DELETE FROM vehicle_categories WHERE id=?");
        $stmt->execute([$_POST['category_id']]);
        $success = "Kategorija vozila je obrisana.";
    }
}

// Get all categories
$categories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();
?>

<div class="container-fluid mt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 h4">
                            <i class="fas fa-truck me-2"></i>
                            Upravljanje kategorijama vozila
                        </h2>
                        <div class="btn-group">
                            <a href="quotes.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Nazad na ponude
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

                <div class="card-body p-4">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-bottom">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-list me-2"></i>
                                        Postojeće kategorije
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($categories) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4">
                                                        <i class="fas fa-tag me-1 text-muted"></i>
                                                        Naziv
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-gas-pump me-1 text-muted"></i>
                                                        Gorivo/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-chart-line me-1 text-muted"></i>
                                                        Amortizacija/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-money-bill-wave me-1 text-muted"></i>
                                                        Zarada/km
                                                    </th>
                                                    <th class="text-center">
                                                        <i class="fas fa-snowflake me-1 text-muted"></i>
                                                        Frigo/km
                                                    </th>
                                                    <th>
                                                        <i class="fas fa-info-circle me-1 text-muted"></i>
                                                        Opis
                                                    </th>
                                                    <th class="text-center pe-4">
                                                        <i class="fas fa-cogs me-1 text-muted"></i>
                                                        Akcije
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $cat): ?>
                                                <tr class="border-bottom">
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                                <i class="fas fa-truck text-muted"></i>
                                                            </div>
                                                            <strong class="text-primary"><?= htmlspecialchars($cat['name']) ?></strong>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning text-dark">
                                                            <?= number_format($cat['fuel_cost_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info text-dark">
                                                            <?= number_format($cat['amortization_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success">
                                                            <?= number_format($cat['earnings_per_km'], 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary">
                                                            <?= number_format($cat['frigo_cost_per_km'] ?? 0, 2) ?> RSD
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '') ?></small>
                                                    </td>
                                                    <td class="text-center pe-4">
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                                    onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                                                    title="Izmeni kategoriju">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" style="display: inline;"
                                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovu kategoriju?')">
                                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                                <button type="submit" name="delete_category" class="btn btn-outline-danger btn-sm"
                                                                        title="Obriši kategoriju">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                                            <i class="fas fa-truck" style="font-size: 4rem; color: #dee2e6;"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">Nema definisanih kategorija vozila</h5>
                                        <p class="text-muted mb-0">Počnite sa kreiranjem nove kategorije vozila.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-bottom">
                                    <h5 class="mb-0 text-primary" id="form-title">
                                        <i class="fas fa-plus me-2"></i>
                                        Dodaj novu kategoriju
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="category-form">
                                        <input type="hidden" name="category_id" id="category_id">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Naziv kategorije *</label>
                                            <input type="text" name="name" id="name" class="form-control" required
                                                   placeholder="npr. Kamion do 3.5t, Kombi, itd.">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Potrošnja goriva po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="fuel_cost_per_km" id="fuel_cost_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Koliko RSD košta gorivo po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Amortizacija po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="amortization_per_km" id="amortization_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Trošak amortizacije vozila po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Zarada po km *</label>
                                            <div class="input-group">
                                                <input type="number" name="earnings_per_km" id="earnings_per_km"
                                                       class="form-control" step="0.01" required>
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Profit po kilometru</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Doplata za frigo po km</label>
                                            <div class="input-group">
                                                <input type="number" name="frigo_cost_per_km" id="frigo_cost_per_km"
                                                       class="form-control" step="0.01" value="0">
                                                <span class="input-group-text">RSD/km</span>
                                            </div>
                                            <small class="form-text text-muted">Dodatni trošak po kilometru za frigo jedinicu</small>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-semibold">Opis</label>
                                            <textarea name="description" id="description" class="form-control" rows="3"
                                                      placeholder="Dodatne informacije o kategoriji"></textarea>
                                        </div>

                                        <button type="submit" name="add_category" id="submit-btn" class="btn btn-success w-100">
                                            <i class="fas fa-save me-2"></i>Dodaj kategoriju
                                        </button>
                                        <button type="button" id="cancel-btn" class="btn btn-secondary w-100 mt-2"
                                                onclick="resetForm()" style="display: none;">
                                            <i class="fas fa-times me-2"></i>Otkaži
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-header bg-light border-bottom">
                                    <h6 class="mb-0 text-primary">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Pomoć pri unosu
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <small>
                                        <strong>Saveti:</strong><br>
                                        • <strong>Gorivo/km:</strong> Izračunajte na osnovu potrošnje i cene goriva<br>
                                        • <strong>Amortizacija/km:</strong> Godišnja amortizacija vozila / godišnja kilometraža<br>
                                        • <strong>Zarada/km:</strong> Željena marža/profit po kilometru<br>
                                        • <strong>Doplata za frigo/km:</strong> Dodatni trošak po kilometru za frigo jedinicu<br>
                                        <br>
                                        <div class="bg-light p-3 rounded">
                                            <strong>Primer za kombi:</strong><br>
                                            Gorivo: 25 RSD/km<br>
                                            Amortizacija: 15 RSD/km<br>
                                            Zarada: 20 RSD/km<br>
                                            Doplata za frigo: 10 RSD/km<br>
                                            = <strong class="text-success">70 RSD/km ukupno</strong>
                                        </div>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (count($categories) > 0): ?>
                <div class="card-footer bg-light border-top">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-chart-bar me-1"></i>
                                Ukupno kategorija: <strong><?= count($categories) ?></strong>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted">
                                Prosečna cena po km: 
                                <strong class="text-success">
                                    <?php 
                                    $avgCost = array_sum(array_map(function($cat) {
                                        return $cat['fuel_cost_per_km'] + $cat['amortization_per_km'] + $cat['earnings_per_km'];
                                    }, $categories)) / count($categories);
                                    echo number_format($avgCost, 2);
                                    ?> RSD
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
    color: white !important;
}

.card-header * {
    color: white !important;
}

.card-header .btn {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
}

.card-header .btn:hover {
    background-color: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
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

<script>
function editCategory(category) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit me-2"></i>Izmeni kategoriju';
    document.getElementById('category_id').value = category.id;
    document.getElementById('name').value = category.name;
    document.getElementById('fuel_cost_per_km').value = category.fuel_cost_per_km;
    document.getElementById('amortization_per_km').value = category.amortization_per_km;
    document.getElementById('earnings_per_km').value = category.earnings_per_km;
    document.getElementById('frigo_cost_per_km').value = category.frigo_cost_per_km || 0;
    document.getElementById('description').value = category.description || '';

    document.getElementById('submit-btn').name = 'update_category';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save me-2"></i>Ažuriraj kategoriju';
    document.getElementById('submit-btn').className = 'btn btn-primary w-100';
    document.getElementById('cancel-btn').style.display = 'block';
}

function resetForm() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus me-2"></i>Dodaj novu kategoriju';
    document.getElementById('category-form').reset();
    document.getElementById('category_id').value = '';

    document.getElementById('submit-btn').name = 'add_category';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save me-2"></i>Dodaj kategoriju';
    document.getElementById('submit-btn').className = 'btn btn-success w-100';
    document.getElementById('cancel-btn').style.display = 'none';
}
</script>

<?php require 'footer.php'; ?>
