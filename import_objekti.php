<?php
// import_objekti.php
require __DIR__ . '/db.php';

function normalizujHeader($s) {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = ['š'=>'s','ć'=>'c','č'=>'c','ž'=>'z','đ'=>'dj'];
    $s = strtr($s, $map);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}
function mapujKolone($headers) {
    $map = ['sifra'=>null,'naziv'=>null,'adresa'=>null,'grad'=>null];
    foreach ($headers as $i=>$h) {
        $n = normalizujHeader($h);
        if ($map['sifra']===null   && preg_match('/(sifra|broj\s*radnje|code|id)\b/u', $n)) $map['sifra']=$i;
        if ($map['naziv']===null   && preg_match('/(naziv|objek|store|name)\b/u', $n))       $map['naziv']=$i;
        if ($map['adresa']===null  && preg_match('/(adresa|ulica|street)\b/u', $n))          $map['adresa']=$i;
        if ($map['grad']===null    && preg_match('/(grad|opstina|opština|city)\b/u', $n))    $map['grad']=$i;
    }
    return $map;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK) {
        die('Greška pri uploadu fajla.');
    }
    $tmp = $_FILES['file']['tmp_name'];
    $name = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        die('Molim upload CSV (UTF-8).');
    }

    $fh = fopen($tmp, 'r');
    if (!$fh) die('Ne mogu da pročitam fajl.');

    $line = fgets($fh); rewind($fh);
    $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';

    $headers = fgetcsv($fh, 0, $delimiter);
    if (!$headers) die('Nisu prepoznati hederi.');

    $map = mapujKolone($headers);
    if ($map['sifra']===null || $map['adresa']===null) {
        die('Obavezno: kolone Šifra/Broj radnje i Adresa. (Naziv, Grad su opciono)');
    }

    $insert = $pdo->prepare("
        INSERT INTO objekti (sifra, naziv, adresa, grad)
        VALUES (:sifra, :naziv, :adresa, :grad)
        ON DUPLICATE KEY UPDATE naziv=VALUES(naziv), adresa=VALUES(adresa), grad=VALUES(grad)
    ");

    $n=0;
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count(array_filter($row, fn($x)=>trim((string)$x)!==''))===0) continue;
        $sifra  = trim((string)($row[$map['sifra']]  ?? ''));
        $naziv  = trim((string)($row[$map['naziv']]  ?? ''));
        $adresa = trim((string)($row[$map['adresa']] ?? ''));
        $grad   = trim((string)($row[$map['grad']]   ?? ''));
        if ($sifra==='' || $adresa==='') continue;

        $insert->execute([':sifra'=>$sifra, ':naziv'=>$naziv, ':adresa'=>$adresa, ':grad'=>$grad]);
        $n++;
    }
    fclose($fh);
    echo "Uvezeno/ažurirano: {$n}. <a href='index.html'>Nazad</a>";
    exit;
}
?>
<!doctype html>
<html lang="sr">
<head><meta charset="utf-8"><title>Import objekata (CSV)</title></head>
<body>
  <h1>Import objekata (CSV UTF-8)</h1>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" accept=".csv" required>
    <button type="submit">Upload & Import</button>
  </form>
  <p>Kolone: Šifra/Broj radnje, Naziv (opciono), Adresa, Grad.</p>
  <p><a href="index.html">← Nazad</a></p>
</body>
</html>