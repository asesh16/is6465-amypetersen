<?php
// Reusable PDO connection.
// Lab 7: the PTH_DB_PATH environment variable lets automated HTTP tests point the
// application at a disposable database instead of the working tutoring.db.
function openDatabase($path = null) {
    if ($path === null) {
        $envPath = getenv('PTH_DB_PATH');
        $path = ($envPath !== false && $envPath !== '') ? $envPath : dirname(__DIR__) . '/database/tutoring.db';
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}
