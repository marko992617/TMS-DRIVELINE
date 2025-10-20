<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['coords'])) {
    $parts = explode(',', $_POST['coords']);
    if (count($parts) === 2) {
        $lat = trim($parts[0]);
        $lng = trim($parts[1]);
        $stmt = $pdo->prepare("UPDATE objekti SET lat = ?, lng = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $_POST['id']]);
        echo "<p style='color: green;'>Uspešno sačuvano za ID: {$_POST['id']}</p>";
    }
}

$stmt = $pdo->query("SELECT * FROM objekti WHERE lat IS NULL OR lng IS NULL");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ručni unos koordinata</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .item { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; }
        input[type="text"] { width: 220px; }
        label { display: inline-block; width: 100px; }
        .map-button {
            margin-top: 10px;
            display: inline-block;
            background-color: #f0f0f0;
            border: 1px solid #999;
            padding: 5px 10px;
            text-decoration: none;
            color: #000;
        }
    </style>
</head>
<body>

<h2>Ručni unos koordinata za objekte</h2>

<?php foreach ($rows as $row):
    $full_address = htmlspecialchars($row['adresa'] . ', ' . $row['grad']);
    $encoded_address = urlencode($row['adresa'] . ', ' . $row['grad']);
?>
<div class="item">
    <p><strong>Šifra:</strong> <?= htmlspecialchars($row['sifra']) ?></p>
    <p><strong>Adresa:</strong> <?= $full_address ?></p>

    <a class="map-button" href="https://yandex.com/maps/?text=<?= $encoded_address ?>" target="_blank">
        Otvori u Yandex Mapama
    </a>

    <form method="post">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <label>Lat,Lng:</label>
        <input type="text" name="coords" placeholder="44.801774, 20.369074" required>
        <button type="submit">Sačuvaj</button>
    </form>
</div>
<?php endforeach; ?>

<?php if (count($rows) === 0): ?>
    <p style="color: green;">Svi objekti imaju koordinate. Nema nerešenih unosa.</p>
<?php endif; ?>

</body>
</html>
