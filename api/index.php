<?php
require __DIR__ . '/bootstrap.php';
$pdo = db(); $action = $_GET['action'] ?? 'catalogue'; $method = $_SERVER['REQUEST_METHOD'];

if ($action === 'upload' && $method === 'POST') {
    requireAdmin();
    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) out(['error' => 'Choisissez une image valide.'], 422);
    if ($file['size'] > 8 * 1024 * 1024) out(['error' => 'L’image ne doit pas dépasser 8 Mo.'], 422);
    $info = @getimagesize($file['tmp_name']);
    $mimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($mimeTypes[$info['mime']])) out(['error' => 'Formats autorisés : JPG, PNG ou WebP.'], 422);
    if ($info[0] > 6000 || $info[1] > 6000 || $info[0] < 320 || $info[1] < 320) out(['error' => 'Utilisez une image d’au moins 320 px et de moins de 6000 px.'], 422);
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) out(['error' => 'Le dossier des images ne peut pas être créé.'], 500);
    $name = bin2hex(random_bytes(12)) . '.webp'; $target = $dir . '/' . $name;
    if (extension_loaded('gd')) {
        $source = match ($info['mime']) {'image/jpeg' => imagecreatefromjpeg($file['tmp_name']), 'image/png' => imagecreatefrompng($file['tmp_name']), 'image/webp' => imagecreatefromwebp($file['tmp_name'])};
        $side = min($info[0], $info[1]); $x = (int)(($info[0] - $side) / 2); $y = (int)(($info[1] - $side) / 2); $canvas = imagecreatetruecolor(1200, 1200);
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, 1200, 1200, $side, $side); imagewebp($canvas, $target, 84); imagedestroy($source); imagedestroy($canvas);
    } elseif (!move_uploaded_file($file['tmp_name'], $target)) out(['error' => 'Impossible d’enregistrer l’image.'], 500);
    out(['url' => 'uploads/' . $name, 'resized' => extension_loaded('gd')]);
}

if ($action === 'backup' && $method === 'GET') {
    requireAdmin();
    if (!class_exists('ZipArchive')) out(['error' => 'L’extension ZIP est requise sur cet hébergement.'], 500);
    $file = tempnam(sys_get_temp_dir(), 'katalog-backup-'); $zip = new ZipArchive(); $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFile(__DIR__ . '/../data/catalogue.sqlite', 'data/catalogue.sqlite');
    foreach (glob(__DIR__ . '/../uploads/*') ?: [] as $image) if (is_file($image)) $zip->addFile($image, 'uploads/' . basename($image));
    $zip->close(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="katalog-sauvegarde-' . date('Y-m-d') . '.zip"'); header('Content-Length: ' . filesize($file)); readfile($file); unlink($file); exit;
}

if ($action === 'catalogue') {
    $pdo->prepare('INSERT INTO daily_metrics(day,metric,count) VALUES(date("now"),"catalogue_views",1) ON CONFLICT(day,metric) DO UPDATE SET count=count+1')->execute();
    $settings = $pdo->query('SELECT key,value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    $products = $pdo->query('SELECT * FROM products WHERE stock > 0 ORDER BY featured DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    out(['settings' => $settings, 'products' => $products]);
}
if ($action === 'admin') {
    requireAdmin();
    if ($method === 'GET') out(['settings' => $pdo->query('SELECT key,value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR), 'products' => $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC), 'orders' => $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC), 'views' => (int)$pdo->query("SELECT COALESCE(SUM(count),0) FROM daily_metrics WHERE metric='catalogue_views'")->fetchColumn(), 'views_today' => (int)$pdo->query("SELECT COALESCE(count,0) FROM daily_metrics WHERE metric='catalogue_views' AND day=date('now')")->fetchColumn()]);
    $input = body();
    if (($input['type'] ?? '') === 'settings') { $stmt = $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'); foreach ($input['settings'] as $k => $v) $stmt->execute([$k, trim((string)$v)]); out(['ok' => true]); }
    if (($input['type'] ?? '') === 'product') { $p = $input['product']; $id = (int)($p['id'] ?? 0); $values = [trim($p['name']), trim($p['description'] ?? ''), (int)$p['price'], trim($p['image'] ?? ''), trim($p['category'] ?? 'Autres'), (int)($p['stock'] ?? 0), !empty($p['featured']) ? 1 : 0]; if ($id) { $values[]=$id; $pdo->prepare('UPDATE products SET name=?,description=?,price=?,image=?,category=?,stock=?,featured=? WHERE id=?')->execute($values); } else $pdo->prepare('INSERT INTO products(name,description,price,image,category,stock,featured) VALUES(?,?,?,?,?,?,?)')->execute($values); out(['ok'=>true]); }
    if (($input['type'] ?? '') === 'delete_product') { $pdo->prepare('DELETE FROM products WHERE id=?')->execute([(int)$input['id']]); out(['ok'=>true]); }
}
if ($action === 'order' && $method === 'POST') {
    $input = body(); $items = $input['items'] ?? []; if (!$items || !trim($input['customer_name'] ?? '') || !trim($input['customer_phone'] ?? '')) out(['error'=>'Informations manquantes'],422);
    $total = array_sum(array_map(fn($i) => (int)$i['price'] * (int)$i['quantity'], $items));
    $pdo->prepare('INSERT INTO orders(customer_name,customer_phone,location,message,items,total) VALUES(?,?,?,?,?,?)')->execute([trim($input['customer_name']),trim($input['customer_phone']),trim($input['location'] ?? ''),trim($input['message'] ?? ''),json_encode($items, JSON_UNESCAPED_UNICODE),$total]);
    out(['ok'=>true]);
}
out(['error' => 'Route inconnue'], 404);
