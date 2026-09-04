<?php
require __DIR__ . '/bootstrap.php';
$input = body();
if (($input['email'] ?? '') === 'admin@katalog.local' && ($input['password'] ?? '') === 'change-me-now') out(['token' => adminToken()]);
out(['error' => 'Identifiants invalides'], 401);
