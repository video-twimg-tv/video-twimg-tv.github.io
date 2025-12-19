<?php
require_once __DIR__ . '/config.php';

function create_sqlite($path) {
    if (file_exists($path)) {
        echo "SQLite DB already exists at $path\n";
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "CREATE TABLE IF NOT EXISTS donations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp TEXT NOT NULL,
        name TEXT,
        email TEXT,
        usd_amount REAL,
        crypto_amount TEXT,
        network TEXT,
        address TEXT
    );";
    $pdo->exec($sql);
    echo "Created/verified SQLite DB at $path\n";
}

function create_mysql($cfg) {
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // create db if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['dbname']}` CHARACTER SET {$cfg['charset']} COLLATE {$cfg['charset']}_general_ci");
        $pdo->exec("USE `{$cfg['dbname']}`");
        $sql = "CREATE TABLE IF NOT EXISTS donations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(64) NOT NULL,
            name VARCHAR(255),
            email VARCHAR(255),
            usd_amount DECIMAL(12,2),
            crypto_amount VARCHAR(255),
            network VARCHAR(64),
            address VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cfg['charset']};";
        $pdo->exec($sql);
        echo "Created/verified MySQL DB {$cfg['dbname']}\n";
    } catch (Exception $e) {
        echo "MySQL error: " . $e->getMessage() . "\n";
    }
}

if ($db_type === 'sqlite') {
    create_sqlite($sqlite_path);
} else {
    create_mysql($mysql);
}