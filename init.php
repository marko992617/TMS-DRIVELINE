<?php
// init.php in root directory
// Primer: konekcija na bazu
try {
    $db = new PDO('mysql:host=localhost;dbname=vaspot_tms;charset=utf8', 'vaspot_tms', '1324vaspotrcko');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
// Ostale globalne konfiguracije...
?>
