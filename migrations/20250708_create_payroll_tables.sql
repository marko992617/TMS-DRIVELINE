-- Create payroll adjustments
CREATE TABLE IF NOT EXISTS `payroll_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `driver_id` INT NOT NULL,
  `tour_date` DATE NOT NULL,
  `daily_allowance` DECIMAL(10,2) DEFAULT 0,
  `adjustment_amount` DECIMAL(10,2) DEFAULT 0,
  `bank_payment` DECIMAL(10,2) DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`)
) ENGINE=InnoDB;
