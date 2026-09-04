<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$localConfig = __DIR__ . '/../config/local.php';
if (!is_file($localConfig)) {
    http_response_code(503);
    echo json_encode(['error' => 'Application non installée. Lancez /install.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $localConfig;
if (!is_array($config) || empty($config['admin_email']) || empty($config['admin_password_hash']) || empty($config['admin_secret'])) {
    http_response_code(503);
    echo json_encode(['error' => 'Configuration de production invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Le dossier SQLite ne peut pas être créé.');
    }

    $path = $dataDir . '/catalogue.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        price INTEGER NOT NULL,
        image TEXT NOT NULL DEFAULT '',
        category TEXT NOT NULL DEFAULT 'Autres',
        stock INTEGER NOT NULL DEFAULT 1,
        featured INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_name TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        location TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        items TEXT NOT NULL,
        total INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'nouvelle',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_metrics (
        day TEXT NOT NULL,
        metric TEXT NOT NULL,
        count INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(day, metric)
    )");

    $defaults = [
        'shop_name' => 'Ma Boutique',
        'tagline' => 'Commandez facilement sur WhatsApp',
        'whatsapp' => '2250102030405',
        'currency' => 'FCFA',
        'delivery_note' => 'Livraison à confirmer par WhatsApp',
    ];
    $insert = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
    foreach ($defaults as $key => $value) {
        $insert->execute([$key, $value]);
    }

    return $pdo;
}

function body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function adminToken(): string
{
    global $config;
    return hash('sha256', (string)$config['admin_secret']);
}

function requireAdmin(): void
{
    $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals(adminToken(), $provided)) {
        out(['error' => 'Non autorisé'], 401);
    }
}
