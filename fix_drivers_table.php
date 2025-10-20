
<?php
require 'config.php';

try {
    // Check if name column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM drivers LIKE 'name'");
    $nameExists = $stmt->rowCount() > 0;
    
    if (!$nameExists) {
        echo "Dodajem kolonu 'name' u tabelu 'drivers'...\n";
        $pdo->exec("ALTER TABLE drivers ADD COLUMN name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Kolona 'name' je dodana!\n";
    } else {
        echo "Kolona 'name' već postoji u tabeli 'drivers'.\n";
    }
    
    // Check if is_active column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM drivers LIKE 'is_active'");
    $isActiveExists = $stmt->rowCount() > 0;
    
    if (!$isActiveExists) {
        echo "Dodajem kolonu 'is_active' u tabelu 'drivers'...\n";
        $pdo->exec("ALTER TABLE drivers ADD COLUMN is_active TINYINT DEFAULT 1");
        echo "Kolona 'is_active' je dodana!\n";
    } else {
        echo "Kolona 'is_active' već postoji u tabeli 'drivers'.\n";
    }
    
    // Show current table structure
    echo "\nTrenutna struktura tabele 'drivers':\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM drivers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Greška: " . $e->getMessage() . "\n";
}
?>
