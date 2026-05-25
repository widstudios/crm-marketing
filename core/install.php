<?php

echo "\n========================================\n";
echo "CRM Marketing - Installation\n";
echo "========================================\n\n";

$storageDir = __DIR__ . '/../storage/database';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
    echo "✓ Created storage directory\n";
}

$config = require __DIR__ . '/../config/config.php';

require_once __DIR__ . '/Database.php';
$database = new \CRMMarketing\Database($config['database']['path']);

try {
    $pdo = $database->connect();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$schema = require __DIR__ . '/../database/schema.php';

try {
    $pdo->exec($schema);
    echo "✓ Database tables created\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Installation completed!\n\n";