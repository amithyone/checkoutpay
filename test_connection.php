<?php
/**
 * Database Connection Test Script
 * This script tests the database connection using the credentials from .env
 */

echo "=== Database Connection Test ===\n\n";

// Load environment variables
$envFile = __DIR__ . '/.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }
    }
}

$host = $envVars['DB_HOST'] ?? '127.0.0.1';
$port = $envVars['DB_PORT'] ?? '3306';
$database = $envVars['DB_DATABASE'] ?? 'checkoutpay';
$username = $envVars['DB_USERNAME'] ?? 'checkoutpay_user';
$password = $envVars['DB_PASSWORD'] ?? 'checkoutpay_pass_2024';

echo "Configuration:\n";
echo "  Host: $host\n";
echo "  Port: $port\n";
echo "  Database: $database\n";
echo "  Username: $username\n";
echo "  Password: " . (empty($password) ? '(empty)' : '***') . "\n\n";

echo "Testing connection...\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Connection successful!\n\n";
    
    // Get database info
    $stmt = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version");
    $info = $stmt->fetch();
    echo "Connected to database: " . $info['db_name'] . "\n";
    echo "MySQL Version: " . $info['version'] . "\n\n";
    
    // Test query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: " . (count($tables) > 0 ? implode(', ', $tables) : '(none - database is empty)') . "\n";
    
    echo "\n✓ All tests passed! Database is ready.\n";
    
} catch (PDOException $e) {
    echo "✗ Connection failed!\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "Possible issues:\n";
        echo "  1. User credentials are incorrect\n";
        echo "  2. User doesn't exist - run setup_database.sql in phpMyAdmin\n";
        echo "  3. Password is incorrect\n";
    } elseif (strpos($e->getMessage(), "Unknown database") !== false) {
        echo "Possible issues:\n";
        echo "  1. Database doesn't exist - run setup_database.sql in phpMyAdmin\n";
        echo "  2. Database name is incorrect\n";
    } elseif (strpos($e->getMessage(), "Connection refused") !== false || strpos($e->getMessage(), "Can't connect") !== false) {
        echo "Possible issues:\n";
        echo "  1. MySQL service is not running\n";
        echo "  2. Host/Port is incorrect\n";
    }
    
    exit(1);
}
