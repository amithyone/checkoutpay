<?php
echo "=== Quick Database Test ===\n\n";

$host = '127.0.0.1';
$db = 'checkoutpay';
$user = 'checkoutpay_user';
$pass = 'checkoutpay_pass_2024';

echo "Testing: $user@$host -> $db\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✓ SUCCESS: Connected to database!\n";
    echo "Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
    echo "MySQL Version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";
} catch (PDOException $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "→ User doesn't exist or password is wrong\n";
        echo "→ Run setup_database.sql in phpMyAdmin\n";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "→ Database doesn't exist\n";
        echo "→ Run setup_database.sql in phpMyAdmin\n";
    }
    exit(1);
}
