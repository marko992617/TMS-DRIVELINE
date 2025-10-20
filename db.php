
<?php
// db.php - MySQL database connection
$host = 'localhost';
$dbname = 'vaspot_tms';
$username = 'vaspot_tms';
$password = '1324vaspotrcko';

$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $opt);
} catch (Exception $e) {
    http_response_code(500);
    echo "DB connection error: " . $e->getMessage();
    exit;
}
?>
