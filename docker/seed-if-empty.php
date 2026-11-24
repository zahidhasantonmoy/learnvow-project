<?php

// Prints the number of rows in the `users` table (0 = fresh DB, seed it).
// Exits 1 with the error on stderr if the database cannot be reached.
// Called by docker/entrypoint.sh — dependency-free (raw PDO only).
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo $count.PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'seed check failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
