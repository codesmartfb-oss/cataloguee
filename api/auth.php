<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Méthode non autorisée'], 405);
}

$input = body();
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    out(['error' => 'Email et mot de passe requis'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(['error' => 'Adresse email invalide'], 422);
}

global $config;
if (!hash_equals((string)$config['admin_email'], $email) || !password_verify($password, (string)$config['admin_password_hash'])) {
    usleep(250000);
    out(['error' => 'Identifiants invalides'], 401);
}

out(['token' => adminToken()]);
