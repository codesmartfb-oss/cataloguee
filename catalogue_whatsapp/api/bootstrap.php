<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Le dossier de données SQLite ne peut pas être créé.');
    }
    $path = $dataDir . '/catalogue.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\',
        price INTEGER NOT NULL, image TEXT NOT NULL DEFAULT \'\', category TEXT NOT NULL DEFAULT \'Autres\',
        stock INTEGER NOT NULL DEFAULT 1, featured INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, customer_name TEXT NOT NULL, customer_phone TEXT NOT NULL,
        location TEXT NOT NULL DEFAULT \'\', message TEXT NOT NULL DEFAULT \'\', items TEXT NOT NULL, total INTEGER NOT NULL, status TEXT NOT NULL DEFAULT \'nouvelle\', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS daily_metrics (day TEXT NOT NULL, metric TEXT NOT NULL, count INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(day, metric))');
    $orderColumns = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('location', $orderColumns, true)) $pdo->exec("ALTER TABLE orders ADD COLUMN location TEXT NOT NULL DEFAULT ''");
    if (!in_array('message', $orderColumns, true)) $pdo->exec("ALTER TABLE orders ADD COLUMN message TEXT NOT NULL DEFAULT ''");
    $defaults = ['shop_name' => 'Ma Boutique', 'tagline' => 'Commandez facilement sur WhatsApp', 'whatsapp' => '2250102030405', 'currency' => 'FCFA', 'delivery_note' => 'Livraison à confirmer par WhatsApp'];
    $insert = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
    foreach ($defaults as $key => $value) $insert->execute([$key, $value]);
    if ((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $seed = $pdo->prepare('INSERT INTO products(name, description, price, image, category, stock, featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $seed->execute(['Sac Élégance', 'Sac pratique pour le quotidien.', 18000, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=700&q=80', 'Mode', 8, 1]);
        $seed->execute(['Sneakers Urban', 'Confort et style pour toutes vos sorties.', 25000, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=700&q=80', 'Chaussures', 5, 1]);
        $seed->execute(['Montre Classique', 'Une finition sobre et raffinée.', 15000, 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=700&q=80', 'Accessoires', 3, 0]);
    }
    return $pdo;
}

function body(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function out(mixed $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function requireAdmin(): void { if (($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '') !== adminToken()) out(['error' => 'Non autorisé'], 401); }
function adminToken(): string { return hash('sha256', 'catalogue-admin-change-me'); }
