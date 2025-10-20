<?php
// settings.php - comprehensive settings for vehicles, fuel prices, and admin users
require 'header.php';
require 'config.php';
require 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle vehicle operations
    if (isset($_POST['add_vehicle'])) {
        $plate = $_POST['plate'] ?? '';
        $avg_consumption = floatval($_POST['avg_consumption'] ?? 0);
        $amortization_per_km = floatval($_POST['amortization_per_km'] ?? 0);
        if ($plate) {
            $stmt = $pdo->prepare("INSERT INTO vehicles (plate, avg_consumption, amortization_per_km) VALUES (?, ?, ?)");
            $stmt->execute([$plate, $avg_consumption, $amortization_per_km]);
            header('Location: settings.php?tab=vehicles&msg=vehicle_added');
            exit;
        }
    } elseif (isset($_POST['edit_vehicle'])) {
        $id = intval($_POST['id']);
        $plate = $_POST['plate'] ?? '';
        $avg_consumption = floatval($_POST['avg_consumption'] ?? 0);
        $amortization_per_km = floatval($_POST['amortization_per_km'] ?? 0);
        if ($id && $plate) {
            $stmt = $pdo->prepare("UPDATE vehicles SET plate=?, avg_consumption=?, amortization_per_km=? WHERE id=?");
            $stmt->execute([$plate, $avg_consumption, $amortization_per_km, $id]);
            header('Location: settings.php?tab=vehicles&msg=vehicle_updated');
            exit;
        }
    } elseif (isset($_POST['delete_vehicle'])) {
        $id = intval($_POST['delete_id']);
        $pdo->prepare("DELETE FROM vehicles WHERE id = ?")->execute([$id]);
        header('Location: settings.php?tab=vehicles&msg=vehicle_deleted');
        exit;
    }

    // Handle fuel price operations
    elseif (isset($_POST['add_fuel_price'])) {
        $month = $_POST['month'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        if ($month && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO fuel_prices (month, price_without_vat) VALUES (?, ?) ON DUPLICATE KEY UPDATE price_without_vat = VALUES(price_without_vat)");
            $stmt->execute([$month, $price]);
            header('Location: settings.php?tab=fuel&msg=fuel_added');
            exit;
        }
    } elseif (isset($_POST['delete_fuel_price'])) {
        $id = intval($_POST['delete_id']);
        $pdo->prepare("DELETE FROM fuel_prices WHERE id = ?")->execute([$id]);
        header('Location: settings.php?tab=fuel&msg=fuel_deleted');
        exit;
    }

    // Handle admin user operations
    elseif (isset($_POST['add_admin'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $name = $_POST['name'] ?? '';
        if ($username && $password && $name) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $name]);
            header('Location: settings.php?tab=admins&msg=admin_added');
            exit;
        }
    } elseif (isset($_POST['edit_admin'])) {
        $id = intval($_POST['id']);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $name = $_POST['name'] ?? '';
        if ($id && $username && $name) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, name=? WHERE id=?");
                $stmt->execute([$username, $hash, $name, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, name=? WHERE id=?");
                $stmt->execute([$username, $name, $id]);
            }
            header('Location: settings.php?tab=admins&msg=admin_updated');
            exit;
        }
    } elseif (isset($_POST['delete_admin'])) {
        $id = intval($_POST['delete_id']);
        if ($id != $_SESSION['user_id']) { // Don't allow deleting yourself
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            header('Location: settings.php?tab=admins&msg=admin_deleted');
            exit;
        }
    }
}

// Fetch data
$vehicles = $pdo->query("SELECT id, plate, avg_consumption, amortization_per_km FROM vehicles ORDER BY plate")->fetchAll(PDO::FETCH_ASSOC);
$fuel_prices = $pdo->query("SELECT id, month, price_without_vat FROM fuel_prices ORDER BY month DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins = $pdo->query("SELECT id, username, name FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Active tab
$active_tab = $_GET['tab'] ?? 'vehicles';

// Flash messages
$msg = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'vehicle_added': $msg = 'Vozilo dodato.'; break;
        case 'vehicle_updated': $msg = 'Vozilo ažurirano.'; break;
        case 'vehicle_deleted': $msg = 'Vozilo obrisano.'; break;
        case 'fuel_added': $msg = 'Cena goriva dodana.'; break;
        case 'fuel_deleted': $msg = 'Cena goriva obrisana.'; break;
        case 'admin_added': $msg = 'Admin korisnik dodat.'; break;
        case 'admin_updated': $msg = 'Admin korisnik ažuriran.'; break;
        case 'admin_deleted': $msg = 'Admin korisnik obrisan.'; break;
    }
}
?>

<div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
  <h3>Podešavanja sistema</h3>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- Navigation tabs -->
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link <?= $active_tab == 'vehicles' ? 'active' : '' ?>" href="?tab=vehicles">Vozila</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $active_tab == 'fuel' ? 'active' : '' ?>" href="?tab=fuel">Cene goriva</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $active_tab == 'admins' ? 'active' : '' ?>" href="?tab=admins">Admin korisnici</a>
    </li>
  </ul>

  <!-- VEHICLES TAB -->
  <?php if ($active_tab == 'vehicles'): ?>
  <div class="row">
    <div class="col-md-8">
      <h4>Upravljanje vozilima</h4>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Registarske tablice</th>
            <th>Prosečna potrošnja (l/100km)</th>
            <th>Amortizacija (din/km)</th>
            <th>Akcije</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vehicles as $v): ?>
          <tr>
            <td><?= $v['id'] ?></td>
            <td><?= htmlspecialchars($v['plate'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format($v['avg_consumption'], 2) ?></td>
            <td><?= number_format($v['amortization_per_km'], 2) ?></td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="editVehicle(<?= $v['id'] ?>,'<?= addslashes($v['plate']) ?>',<?= $v['avg_consumption'] ?>,<?= $v['amortization_per_km'] ?>)">Izmeni</button>
              <form method="post" style="display:inline;" onsubmit="return confirm('Da li ste sigurni?');">
                <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                <button type="submit" name="delete_vehicle" class="btn btn-sm btn-danger">Obriši</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="col-md-4">
      <h4 id="vehicle-form-title">Dodaj vozilo</h4>
      <form method="post" id="vehicle-form">
        <input type="hidden" name="id" id="vehicle-id" value="">
        <div class="mb-3">
          <label class="form-label">Registarske tablice</label>
          <input type="text" name="plate" id="vehicle-plate" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Prosečna potrošnja (l/100km)</label>
          <input type="number" step="0.01" name="avg_consumption" id="vehicle-consumption" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Amortizacija (din/km)</label>
          <input type="number" step="0.01" name="amortization_per_km" id="vehicle-amortization" class="form-control" required>
        </div>
        <button type="submit" name="add_vehicle" id="vehicle-add-btn" class="btn btn-success">Dodaj</button>
        <button type="submit" name="edit_vehicle" id="vehicle-edit-btn" class="btn btn-primary" style="display:none;">Sačuvaj izmene</button>
        <button type="button" id="vehicle-cancel-btn" class="btn btn-secondary" style="display:none;">Odustani</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- FUEL PRICES TAB -->
  <?php if ($active_tab == 'fuel'): ?>
  <div class="row">
    <div class="col-md-8">
      <h4>Cene goriva po mesecima</h4>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Mesec</th>
            <th>Cena bez PDV-a (din/l)</th>
            <th>Akcije</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fuel_prices as $f): ?>
          <tr>
            <td><?= $f['id'] ?></td>
            <td><?= htmlspecialchars($f['month'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format($f['price_without_vat'], 2) ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Da li ste sigurni?');">
                <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                <button type="submit" name="delete_fuel_price" class="btn btn-sm btn-danger">Obriši</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="col-md-4">
      <h4>Dodaj cenu goriva</h4>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Mesec (YYYY-MM)</label>
          <input type="month" name="month" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Cena bez PDV-a (din/l)</label>
          <input type="number" step="0.01" name="price" class="form-control" required>
        </div>
        <button type="submit" name="add_fuel_price" class="btn btn-success">Dodaj</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- ADMIN USERS TAB -->
  <?php if ($active_tab == 'admins'): ?>
  <div class="row">
    <div class="col-md-8">
      <h4>Admin korisnici</h4>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Korisničko ime</th>
            <th>Ime</th>
            <th>Akcije</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a): ?>
          <tr>
            <td><?= $a['id'] ?></td>
            <td><?= htmlspecialchars($a['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="editAdmin(<?= $a['id'] ?>,'<?= addslashes($a['username']) ?>','<?= addslashes($a['name']) ?>')">Izmeni</button>
              <?php if ($a['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Da li ste sigurni?');">
                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                <button type="submit" name="delete_admin" class="btn btn-sm btn-danger">Obriši</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="col-md-4">
      <h4 id="admin-form-title">Dodaj admin korisnika</h4>
      <form method="post" id="admin-form">
        <input type="hidden" name="id" id="admin-id" value="">
        <div class="mb-3">
          <label class="form-label">Korisničko ime</label>
          <input type="text" name="username" id="admin-username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Ime i prezime</label>
          <input type="text" name="name" id="admin-name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Lozinka <span id="admin-password-hint">(obavezno)</span></label>
          <input type="password" name="password" id="admin-password" class="form-control" required>
        </div>
        <button type="submit" name="add_admin" id="admin-add-btn" class="btn btn-success">Dodaj</button>
        <button type="submit" name="edit_admin" id="admin-edit-btn" class="btn btn-primary" style="display:none;">Sačuvaj izmene</button>
        <button type="button" id="admin-cancel-btn" class="btn btn-secondary" style="display:none;">Odustani</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
// Vehicle functions
function editVehicle(id, plate, consumption, amortization) {
  document.getElementById('vehicle-form-title').innerText = 'Izmeni vozilo';
  document.getElementById('vehicle-id').value = id;
  document.getElementById('vehicle-plate').value = plate;
  document.getElementById('vehicle-consumption').value = consumption;
  document.getElementById('vehicle-amortization').value = amortization;
  document.getElementById('vehicle-add-btn').style.display = 'none';
  document.getElementById('vehicle-edit-btn').style.display = 'inline-block';
  document.getElementById('vehicle-cancel-btn').style.display = 'inline-block';
}

document.getElementById('vehicle-cancel-btn').onclick = function() {
  document.getElementById('vehicle-form-title').innerText = 'Dodaj vozilo';
  document.getElementById('vehicle-id').value = '';
  document.getElementById('vehicle-plate').value = '';
  document.getElementById('vehicle-consumption').value = '';
  document.getElementById('vehicle-amortization').value = '';
  document.getElementById('vehicle-add-btn').style.display = 'inline-block';
  document.getElementById('vehicle-edit-btn').style.display = 'none';
  this.style.display = 'none';
};

// Admin functions
function editAdmin(id, username, name) {
  document.getElementById('admin-form-title').innerText = 'Izmeni admin korisnika';
  document.getElementById('admin-id').value = id;
  document.getElementById('admin-username').value = username;
  document.getElementById('admin-name').value = name;
  document.getElementById('admin-password').removeAttribute('required');
  document.getElementById('admin-password-hint').innerText = '(ostaviti prazno za nepromenu)';
  document.getElementById('admin-add-btn').style.display = 'none';
  document.getElementById('admin-edit-btn').style.display = 'inline-block';
  document.getElementById('admin-cancel-btn').style.display = 'inline-block';
}

document.getElementById('admin-cancel-btn').onclick = function() {
  document.getElementById('admin-form-title').innerText = 'Dodaj admin korisnika';
  document.getElementById('admin-id').value = '';
  document.getElementById('admin-username').value = '';
  document.getElementById('admin-name').value = '';
  document.getElementById('admin-password').value = '';
  document.getElementById('admin-password').setAttribute('required','required');
  document.getElementById('admin-password-hint').innerText = '(obavezno)';
  document.getElementById('admin-add-btn').style.display = 'inline-block';
  document.getElementById('admin-edit-btn').style.display = 'none';
  this.style.display = 'none';
};
</script>

<?php require 'footer.php'; ?>