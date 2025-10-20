<?php
// plate_list.php - pregled sačuvanih obračuna plata sa mogućnošću brisanja i editovanja

require 'header.php';
require 'config.php';
require 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Delete related items
    $pdo->prepare("DELETE FROM payroll_items WHERE payroll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM payrolls WHERE id = ?")->execute([$id]);
    header('Location: plate_list.php?msg=deleted');
    exit;
}

// Fetch payroll records
$stmt = $pdo->query("SELECT p.id, d.name AS driver_name, p.date_from, p.date_to, p.total_km, p.total_allowance, p.paid_amount
    FROM payrolls p
    JOIN drivers d ON p.driver_id = d.id
    ORDER BY p.date_from DESC, p.date_to DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash message
$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = '<div class="alert alert-success">Obračun je uspešno obrisan.</div>';
}
?>
<div class="container">
  <h3>Pregled obračuna plata</h3>
  <?= $msg ?>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>Vozač</th>
        <th>Period</th>
        <th>Km</th>
        <th>Dnevnica</th>
        <th>Uplaćeno</th>
        <th>Za isplatu</th>
        <th>Akcije</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $r): ?>
        <?php $due = $r['total_allowance'] - $r['paid_amount']; ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['driver_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['date_from']) ?> - <?= htmlspecialchars($r['date_to']) ?></td>
          <td><?= $r['total_km'] ?></td>
          <td><?= number_format($r['total_allowance'],2,',',' ') ?></td>
          <td><?= number_format($r['paid_amount'],2,',',' ') ?></td>
          <td><?= number_format($due,2,',',' ') ?></td>
          <td>
            <a href="plate.php?view=<?= $r['id'] ?>" class="btn btn-info btn-sm">Pregled</a>
            <a href="plate.php?driver=<?= $r['driver_name'] ?>&date_from=<?= $r['date_from'] ?>&date_to=<?= $r['date_to'] ?>" class="btn btn-primary btn-sm">Izmeni</a>
            <a href="plate_list.php?delete=<?= $r['id'] ?>" onclick="return confirm('Da li ste sigurni da želite da obrišete obračun?');" class="btn btn-danger btn-sm">Obriši</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require 'footer.php'; ?>