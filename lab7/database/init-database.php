<?php
// Optional alternative to the two sqlite3 commands in the lab instructions.
// Applies setup-database.sql, then upgrade-to-user-management.sql if it has not been applied yet.
// Safe to rerun.   php database/init-database.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/db.php';

try {
    $pdo = openDatabase();
    $pdo->exec(file_get_contents(__DIR__ . '/setup-database.sql'));
    $columns = $pdo->query('PRAGMA table_info(students)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('user_id', $columns, true)) {
        $pdo->exec(file_get_contents(__DIR__ . '/upgrade-to-user-management.sql'));
    }
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo 'Database ready. Tables: ' . implode(', ', $tables) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Database setup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
