#!/usr/bin/env php
<?php
echo "=== Database Connection Verification ===\n\n";

$host = '127.0.0.1';
$db = 'checkoutpay';
$user = 'checkoutpay_user';
$pass = 'checkoutpay_pass_2024';

echo "Testing connection to: $db@$host\n";
echo "User: $user\n\n";

// Test 1: Check if we can connect to MySQL server
echo "Test 1: Connecting to MySQL server...\n";
try {
    $pdo1 = new PDO("mysql:host=$host", $user, $pass);
    echo "✓ Connected to MySQL server\n";
} catch (PDOException $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    echo "\nNOTE: Database or user may not exist yet.\n";
    echo "Please run setup_database.sql in phpMyAdmin first.\n";
    exit(1);
}

// Test 2: Check if database exists
echo "\nTest 2: Checking if database '$db' exists...\n";
try {
    $stmt = $pdo1->query("SHOW DATABASES LIKE '$db'");
    $result = $stmt->fetch();
    if ($result) {
        echo "✓ Database '$db' exists\n";
    } else {
        echo "✗ Database '$db' does not exist\n";
        echo "\nPlease run this SQL in phpMyAdmin:\n";
        echo "CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Connect to the specific database
echo "\nTest 3: Connecting to database '$db'...\n";
try {
    $pdo2 = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Successfully connected to database '$db'\n";
    
    // Get version info
    $stmt = $pdo2->query("SELECT VERSION() as version, DATABASE() as db");
    $info = $stmt->fetch();
    echo "  MySQL Version: " . $info['version'] . "\n";
    echo "  Current Database: " . $info['db'] . "\n";
    
    // Check tables
    $stmt = $pdo2->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables: " . (count($tables) > 0 ? count($tables) . " table(s)" : "No tables (empty database)") . "\n";
    
    echo "\n✓✓✓ ALL TESTS PASSED! Database connection is working! ✓✓✓\n";
    
} catch (PDOException $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    exit(1);
}
