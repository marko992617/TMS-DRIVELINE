<?php
namespace App\Service;

use PDO;

class PayrollService
{
    private PDO $db;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';
        $this->db = new PDO($config['dsn'], $config['user'], $config['password']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function process(): array
    {
        $drivers = $this->db->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $message = '';
        $rows = [];
        $driverId = '';
        $from = '';
        $to = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_POST['adjust']) && !empty($_POST['driver_id'])) {
                foreach ($_POST['adjust'] as $tourDate => $data) {
                    $stmt = $this->db->prepare("
                        REPLACE INTO payroll_adjustments
                        (driver_id, tour_date, daily_allowance, adjustment_amount, bank_payment)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['driver_id'],
                        $tourDate,
                        $data['daily_allowance'],
                        $data['adjustment'],
                        $data['bank_payment']
                    ]);
                }
                $message = 'Izmene sačuvane.';
            }

            if (!empty($_POST['driver_id']) && !empty($_POST['from_date']) && !empty($_POST['to_date'])) {
                $driverId = $_POST['driver_id'];
                $from = $_POST['from_date'];
                $to = $_POST['to_date'];
                $stmt = $this->db->prepare("
                    SELECT t.date, t.revenue, a.daily_allowance, a.adjustment_amount, a.bank_payment
                    FROM tours t
                    LEFT JOIN payroll_adjustments a 
                        ON a.driver_id = ? AND a.tour_date = t.date
                    WHERE t.driver_id = ? AND t.date BETWEEN ? AND ?
                    ORDER BY t.date
                ");
                $stmt->execute([$driverId, $driverId, $from, $to]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return compact('drivers', 'rows', 'message', 'driverId', 'from', 'to');
    }
}
