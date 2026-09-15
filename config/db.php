<?php
/**
 * Database Connection & Global Configuration
 * Child Vaccination Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to prevent header errors across redirects
if (!ob_get_level()) {
    ob_start();
}

// Database Credentials (Default XAMPP)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'child_vaccination');
define('DB_PORT', '3306');

// Application Info
define('APP_NAME', 'Vaccination Management System');

// Dynamic BASE_URL detection
if (!defined('BASE_URL')) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Find project root index position
    $rootIndicators = ['/admin/', '/includes/', '/config/', '/auth/', '/database/', '/parent/', '/hospital/'];
    $base = '';
    
    foreach ($rootIndicators as $ind) {
        $pos = strpos($scriptPath, $ind);
        if ($pos !== false) {
            $base = substr($scriptPath, 0, $pos + 1);
            break;
        }
    }
    
    if (empty($base)) {
        // Direct root execution e.g. /vaccination/vaccination/index.php -> /vaccination/vaccination/
        $dir = dirname($scriptPath);
        $base = ($dir === '/' || $dir === '\\') ? '/' : rtrim($dir, '/') . '/';
    }
    
    define('BASE_URL', $base);
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4;port=" . DB_PORT;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // If DB not found (1049), attempt auto-creation
        if ($e->getCode() == 1049) {
            try {
                $rootDsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4;port=" . DB_PORT;
                $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $rootPdo->exec($sql);
                }
                
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (Exception $setupEx) {
                // Return null so web installer link can be shown
                return null;
            }
        }
        return null;
    }
}

// Global PDO instance
$pdo = getDBConnection();
