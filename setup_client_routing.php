<?php
/**
 * Setup Client Route Configuration
 * This script adds route calculation configuration to the clients table
 * and sets up specific configurations for known clients
 */

require 'config.php';

try {
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'route_calculation_type'");
    $columnExists = $stmt->fetchColumn();
    
    if (!$columnExists) {
        echo "Adding route calculation columns to clients table...\n";
        
        // Add route calculation columns
        $pdo->exec("ALTER TABLE clients ADD COLUMN route_calculation_type ENUM('standard', 'delta_transporti', 'milsped_doo') DEFAULT 'standard'");
        $pdo->exec("ALTER TABLE clients ADD COLUMN base_location_name VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE clients ADD COLUMN base_latitude DECIMAL(10, 8) DEFAULT NULL");
        $pdo->exec("ALTER TABLE clients ADD COLUMN base_longitude DECIMAL(11, 8) DEFAULT NULL");
        $pdo->exec("ALTER TABLE clients ADD COLUMN route_config_json TEXT DEFAULT NULL");
        
        echo "✓ Route calculation columns added successfully.\n";
    } else {
        echo "✓ Route calculation columns already exist.\n";
    }
    
    // Set specific configuration for known clients
    echo "Configuring client-specific route calculation...\n";
    
    // Delta Transporti configuration
    $stmt = $pdo->prepare("UPDATE clients SET 
        route_calculation_type = 'delta_transporti',
        base_location_name = 'Nova Pazova',
        base_latitude = 45.224722,
        base_longitude = 20.033333
        WHERE name LIKE '%Delta%' OR name LIKE '%delta%' OR name LIKE '%transporti%'");
    $stmt->execute();
    $deltaCount = $stmt->rowCount();
    
    // Milšped DOO configuration
    $stmt = $pdo->prepare("UPDATE clients SET 
        route_calculation_type = 'milsped_doo'
        WHERE name LIKE '%Milšped%' OR name LIKE '%milsped%' OR name LIKE '%Mil\u0161ped%'");
    $stmt->execute();
    $milspedCount = $stmt->rowCount();
    
    echo "✓ Configured {$deltaCount} clients for Delta Transporti logic.\n";
    echo "✓ Configured {$milspedCount} clients for Milšped DOO logic.\n";
    
    // Check if client_locations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'client_locations'");
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        echo "Creating client_locations table...\n";
        
        $sql = "CREATE TABLE client_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            location_name VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            location_type ENUM('store', 'warehouse', 'distribution_center', 'other') DEFAULT 'store',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            INDEX idx_client_locations_client_id (client_id),
            INDEX idx_client_locations_active (is_active)
        )";
        $pdo->exec($sql);
        
        echo "✓ client_locations table created successfully.\n";
    } else {
        echo "✓ client_locations table already exists.\n";
    }
    
    // Display current client configurations
    echo "\nCurrent client route configurations:\n";
    echo "=====================================\n";
    
    $stmt = $pdo->query("SELECT name, route_calculation_type, base_location_name FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($clients as $client) {
        $calcType = $client['route_calculation_type'] ?? 'standard';
        $baseLoc = $client['base_location_name'] ? " (base: {$client['base_location_name']})" : '';
        echo "• {$client['name']}: {$calcType}{$baseLoc}\n";
    }
    
    echo "\n✅ Client route configuration setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test the distance estimation in edit_tour.php\n";
    echo "2. Configure specific client locations if needed\n";
    echo "3. Verify OSRM API connectivity\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up client route configuration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>