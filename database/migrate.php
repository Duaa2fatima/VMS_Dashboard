<?php
/**
 * Migration Script: Per-Hospital Vaccine Stock & Hospital Appointment Time
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';

echo "--- Starting Database Migration ---\n";

if (!$pdo) {
    die("Database connection failed. Please check config/db.php\n");
}

try {
    // 1. Add appointment_time and hospital_notes to bookings table if missing
    $cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('appointment_time', $cols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN appointment_time TIME NULL AFTER appointment_date");
        echo "✓ Added 'appointment_time' column to 'bookings' table.\n";
    } else {
        echo "✓ 'appointment_time' column already exists in 'bookings'.\n";
    }

    if (!in_array('hospital_notes', $cols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN hospital_notes TEXT NULL AFTER approval_date");
        echo "✓ Added 'hospital_notes' column to 'bookings' table.\n";
    } else {
        echo "✓ 'hospital_notes' column already exists in 'bookings'.\n";
    }

    // 2. Create hospital_vaccines table if missing
    $tableExists = $pdo->query("SHOW TABLES LIKE 'hospital_vaccines'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE hospital_vaccines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                hospital_id INT NOT NULL,
                vaccine_id INT NOT NULL,
                stock_status ENUM('Available', 'Unavailable') DEFAULT 'Available',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_hospital_vaccine (hospital_id, vaccine_id),
                FOREIGN KEY (hospital_id) REFERENCES hospitals(hospital_id) ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (vaccine_id) REFERENCES vaccines(vaccine_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "✓ Created 'hospital_vaccines' table.\n";
    } else {
        echo "✓ 'hospital_vaccines' table already exists.\n";
    }

    // 3. Seed hospital_vaccines with records for all hospitals and vaccines
    $inserted = $pdo->exec("
        INSERT IGNORE INTO hospital_vaccines (hospital_id, vaccine_id, stock_status)
        SELECT h.hospital_id, v.vaccine_id, v.stock_status
        FROM hospitals h
        CROSS JOIN vaccines v;
    ");
    echo "✓ Populated hospital_vaccines ({$inserted} initial links created).\n";

    // 4. Update existing sample bookings with reasonable appointment_time if null
    $pdo->exec("UPDATE bookings SET appointment_time = '10:00:00' WHERE appointment_time IS NULL AND status IN ('Approved', 'Completed')");
    echo "✓ Populated appointment_time for approved/completed bookings.\n";

    echo "--- Migration Completed Successfully! ---\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
