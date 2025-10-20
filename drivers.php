
<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require 'functions.php';

// Handle POST operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_driver'])) {
        $name = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($name && $username && $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO drivers (name, username, password_hash, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $username, $hash, $is_active]);
            header('Location: drivers.php?msg=added');
            exit;
        }
    } elseif (isset($_POST['edit_driver'])) {
        $id = intval($_POST['id']);
        $name = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id && $name && $username) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE drivers SET name=?, username=?, password_hash=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $username, $hash, $is_active, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE drivers SET name=?, username=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $username, $is_active, $id]);
            }
            header('Location: drivers.php?msg=updated');
            exit;
        }
    } elseif (isset($_POST['delete_driver'])) {
        $id = intval($_POST['delete_id']);
        $pdo->prepare("DELETE FROM drivers WHERE id = ?")->execute([$id]);
        header('Location: drivers.php?msg=deleted');
        exit;
    }
}

$drivers = $pdo->query("SELECT id, name, username, is_active FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Flash messages
$msg = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added': $msg = 'Vozač uspešno dodat.'; break;
        case 'updated': $msg = 'Vozač uspešno ažuriran.'; break;
        case 'deleted': $msg = 'Vozač uspešno obrisan.'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vozači</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Upravljanje vozačima</h3>
    
    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Lista vozača -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Lista vozača</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ime</th>
                                <th>Korisničko ime</th>
                                <th>Status</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drivers as $d): ?>
                            <tr class="<?= $d['is_active'] ? '' : 'table-secondary' ?>">
                                <td><?= $d['id'] ?></td>
                                <td><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($d['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge bg-<?= $d['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $d['is_active'] ? 'Aktivan' : 'Neaktivan' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="editDriver(<?= $d['id'] ?>,'<?= addslashes($d['name']) ?>','<?= addslashes($d['username']) ?>',<?= $d['is_active'] ?>)">
                                        Izmeni
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovog vozača?');">
                                        <input type="hidden" name="delete_id" value="<?= $d['id'] ?>">
                                        <button type="submit" name="delete_driver" class="btn btn-sm btn-danger">Obriši</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($drivers)): ?>
                        <div class="alert alert-info">
                            Nema unetih vozača. Dodajte prvog vozača koristeći formu sa desne strane.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Forma za dodavanje/izmenu vozača -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 id="form-title">Dodaj novog vozača</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="driver-form">
                        <input type="hidden" name="id" id="driver-id" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Ime i prezime *</label>
                            <input type="text" name="name" id="driver-name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Korisničko ime *</label>
                            <input type="text" name="username" id="driver-username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Lozinka <span id="password-hint">*</span></label>
                            <input type="password" name="password" id="driver-password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="driver-active" class="form-check-input" checked>
                                <label class="form-check-label" for="driver-active">Aktivan vozač</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_driver" id="add-btn" class="btn btn-success">Dodaj vozača</button>
                        <button type="submit" name="edit_driver" id="edit-btn" class="btn btn-primary" style="display:none;">Sačuvaj izmene</button>
                        <button type="button" id="cancel-btn" class="btn btn-secondary" style="display:none;">Odustani</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="index.php" class="btn btn-outline-secondary">← Nazad na početnu</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editDriver(id, name, username, isActive) {
    document.getElementById('form-title').innerText = 'Izmeni vozača';
    document.getElementById('driver-id').value = id;
    document.getElementById('driver-name').value = name;
    document.getElementById('driver-username').value = username;
    document.getElementById('driver-active').checked = isActive == 1;
    document.getElementById('driver-password').removeAttribute('required');
    document.getElementById('password-hint').innerText = '(ostaviti prazno za nepromenu)';
    document.getElementById('add-btn').style.display = 'none';
    document.getElementById('edit-btn').style.display = 'inline-block';
    document.getElementById('cancel-btn').style.display = 'inline-block';
}

document.getElementById('cancel-btn').onclick = function() {
    document.getElementById('form-title').innerText = 'Dodaj novog vozača';
    document.getElementById('driver-id').value = '';
    document.getElementById('driver-name').value = '';
    document.getElementById('driver-username').value = '';
    document.getElementById('driver-password').value = '';
    document.getElementById('driver-active').checked = true;
    document.getElementById('driver-password').setAttribute('required','required');
    document.getElementById('password-hint').innerText = '*';
    document.getElementById('add-btn').style.display = 'inline-block';
    document.getElementById('edit-btn').style.display = 'none';
    this.style.display = 'none';
};
</script>
</body>
</html>
