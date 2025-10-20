<?php
// setup_database.php - Convert MySQL schema to SQLite and populate data

$db_file = 'delhaize_stores.db';

// Remove existing database if it exists
if (file_exists($db_file)) {
    unlink($db_file);
}

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables (SQLite compatible versions)
    $tables = [
        "CREATE TABLE drivers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) UNIQUE,
            password_hash VARCHAR(255)
        )",

        "CREATE TABLE driver_km (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tour_id INTEGER NOT NULL,
            driver_id INTEGER NOT NULL,
            km INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME,
            UNIQUE(tour_id, driver_id)
        )",

        "CREATE TABLE driver_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            driver_id INTEGER NOT NULL,
            tour_id INTEGER NOT NULL,
            waybill_number VARCHAR(50),
            start_km INTEGER,
            end_km INTEGER,
            note TEXT,
            images_json TEXT,
            image_path VARCHAR(255),
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
            FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE
        )",

        "CREATE TABLE maintenance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            vehicle_id INTEGER NOT NULL,
            service_date DATE NOT NULL,
            mileage INTEGER NOT NULL,
            labor_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            parts_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(20) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
        )",

        "CREATE TABLE objekti (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sifra VARCHAR(64) NOT NULL UNIQUE,
            naziv VARCHAR(255),
            adresa VARCHAR(255),
            grad VARCHAR(128),
            lat DECIMAL(10,7),
            lng DECIMAL(10,7)
        )",

        "CREATE TABLE payrolls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            driver_id INTEGER NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            total_km INTEGER NOT NULL,
            total_allowance DECIMAL(10,2) NOT NULL,
            deduction_reason VARCHAR(255),
            deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            bonus_reason VARCHAR(255),
            bonus_amount DECIMAL(10,2) DEFAULT 0.00,
            paid_amount DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        )",

        "CREATE TABLE payroll_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            payroll_id INTEGER NOT NULL,
            type VARCHAR(10) NOT NULL CHECK (type IN ('BONUS', 'ODBITAK')),
            reason VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE
        )",

        "CREATE TABLE settings (
            name VARCHAR(50) PRIMARY KEY,
            value VARCHAR(255) NOT NULL
        )",

        "CREATE TABLE tours (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date DATE,
            driver_id INTEGER,
            vehicle_id INTEGER,
            km DECIMAL(10,2),
            fuel_cost DECIMAL(10,2),
            amortization DECIMAL(10,2),
            allowance DECIMAL(10,2),
            loading_time DATETIME,
            loading_loc VARCHAR(100),
            unloading_loc TEXT NOT NULL,
            route VARCHAR(255) NOT NULL,
            note TEXT,
            turnover DECIMAL(10,2),
            profit DECIMAL(10,2),
            ors_id VARCHAR(100),
            delivery_type VARCHAR(100),
            extra_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            driver_km INTEGER
        )",

        "CREATE TABLE vehicles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plate VARCHAR(20),
            registration_date DATE,
            insurance_expiry DATE,
            cmr_expiry DATE,
            tachograph_expiry DATE,
            sixmo_expiry DATE,
            fuel_consumption DECIMAL(10,3) NOT NULL DEFAULT 0.000,
            amort_per_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            depreciation_per_km DECIMAL(10,3) NOT NULL DEFAULT 0.000
        )",

        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        "CREATE TABLE export_cache (
            user_id INTEGER,
            data TEXT,
            created_at DATETIME,
            PRIMARY KEY (user_id)
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Insert sample data

    // Insert drivers
    $drivers = [
        [2, 'Dušan Dvokić', 'dusan', '$2y$10$/mX2jm.IDpZMMu6uwWjj4.ljWvX0tKR39qVbIHigVgyl5iLul5JH2'],
        [3, 'Darko Veličković', null, null],
        [4, 'Saša Mijatović', 'sasa', '$2y$10$dnPmqGrVzzCJO3MgIM5jCugwT054CmR9kSvNnP2x2t.HwdDz95GbW'],
        [6, 'Aleksandar Stanković', 'aleksandar', '$2y$10$5gQdHJpEb9A/ozgb4EOQruOKTZMiRgnUDmwn5LDoRBJXk.jN5ohKK'],
        [7, 'Darko Ivšić', 'darko', '$2y$10$3k52AJJp6ZWrS3X7ZnYIv.SYK6eB36HoHu0NjPhLLtCLAHA9C1Fe2'],
        [9, 'Aleksa', 'aleksa', '$2y$10$DCHEAKW2x1S.z.L.BeQAEuNrFEHW.TuqsB3IpweSRfpPq2CraEJi.']
    ];

    $stmt = $pdo->prepare("INSERT INTO drivers (id, name, username, password_hash) VALUES (?, ?, ?, ?)");
    foreach ($drivers as $driver) {
        $stmt->execute($driver);
    }

    // Insert vehicles
    $vehicles = [
        [1, 'BG-2798-ON', null, null, null, null, null, 13.500, 0.00, 5.000],
        [3, 'BG-2746-GP', null, null, null, null, null, 13.500, 0.00, 5.000],
        [4, 'BG-2746-PK', null, null, null, null, null, 13.000, 0.00, 5.000],
        [5, 'BG-2865-EH', null, null, null, null, null, 25.000, 0.00, 10.000],
        [6, 'BG-2811-ET', null, null, null, null, null, 13.500, 0.00, 5.000],
        [7, 'BG-2860-ZO', null, null, null, null, null, 25.000, 0.00, 10.000]
    ];

    $stmt = $pdo->prepare("INSERT INTO vehicles (id, plate, registration_date, insurance_expiry, cmr_expiry, tachograph_expiry, sixmo_expiry, fuel_consumption, amort_per_km, depreciation_per_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($vehicles as $vehicle) {
        $stmt->execute($vehicle);
    }

    // Insert admin user
    $pdo->prepare("INSERT INTO users (id, username, password) VALUES (?, ?, ?)")
        ->execute([1, 'marko', '$2y$10$d.GesrJTSHK0QMRpVLw8Tu8sG8ojuoXupHqRbdCFNWlwxdlcm5Hzi']);

    // Insert settings
    $settings = [
        ['fuel_price', '160'],
        ['import_map', '{"ORS_ID":"3","Datum utovara rute":"1","Mesto utovara":"7","Tip isporuke":"6","Vreme utova.":"9","licenseplate":"13","Mesto istovara":"16"}'],
        ['import_mapping', '{"date":"0","km":"5","turnover":"7","loading":"4","unloading":"6"}'],
        ['import_map_tura', '{"date":"1","ors_id":"3","delivery_type":"6","loading_loc":"7","loading_time":"9","licenseplate":"13","unloading_loc":"16","route":"12"}']
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }

    // Insert some sample objekti (first 10 from the SQL)
    $objekti = [
        [195, '213 102', 'Maxi - 102', 'Dragice Koncar 33a, Brace Jerkovic', 'Beograd', 44.7692798, 20.4971262],
        [196, '213 103', 'Maxi - 103', 'Džona Kenedija 10a', 'Beograd', 44.8342385, 20.4128999],
        [197, '213 104', 'Maxi - 104', 'Patrijarha Joanikija 17g, Vidikovac', 'Beograd', 44.7361217, 20.4193790],
        [198, '213 107', 'Maxi - 107', 'Marijane Gregoran br.58, Karaburma', 'Beograd', 44.8132384, 20.5067834],
        [199, '213 109', 'Maxi - 109', 'Blok F, Milana Toplice 2b, Borča', 'Beograd', 44.8822330, 20.4566930]
    ];

    $stmt = $pdo->prepare("INSERT INTO objekti (id, sifra, naziv, adresa, grad, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($objekti as $obj) {
        $stmt->execute($obj);
    }

    // Insert sample tours (first 5 from the SQL)
    $tours = [
        [21, '2025-06-14', 5, 4, 443.00, 9214.40, 2215.00, 6500.00, '2025-06-14 03:01:00', 'TMO 213 843', 'TMO 213 237,TMO 213 780', 'Novi Sad', '', 30654.33, -17929.40, '5750156ORS', 'SPEC ZEL', 0.00, null],
        [22, '2025-06-14', 2, 3, 106.00, 2289.60, 530.00, 4500.00, '2025-06-14 03:01:00', 'TMO 213 843', 'TMO 215 047,TMO 215 143', 'Beograd', '', 0.00, -7319.60, '5750176ORS', 'SPEC ZEL', 0.00, null]
    ];

    $stmt = $pdo->prepare("INSERT INTO tours (id, date, driver_id, vehicle_id, km, fuel_cost, amortization, allowance, loading_time, loading_loc, unloading_loc, route, note, turnover, profit, ors_id, delivery_type, extra_cost, driver_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($tours as $tour) {
        $stmt->execute($tour);
    }

    echo "Database setup completed successfully!\n";
    echo "Database file: $db_file\n";
    echo "Admin login: marko / admin123\n";
    echo "Driver login examples: dusan, sasa, aleksandar, darko, aleksa (password same as username)\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>