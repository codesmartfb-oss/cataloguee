<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config/local.php';
$installed = is_file($configPath);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $config = "<?php\nreturn " . var_export([
            'admin_email' => $email,
            'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'admin_secret' => bin2hex(random_bytes(32)),
        ], true) . ";\n";

        if (@file_put_contents($configPath, $config, LOCK_EX) === false) {
            $error = 'Impossible de créer config/local.php. Vérifiez les droits du dossier config/. ';
        } else {
            @chmod($configPath, 0600);
            $installed = true;
            $message = 'Installation terminée. Supprimez maintenant install.php du serveur, puis connectez-vous à votre espace admin.';
        }
    }
}

if ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = 'Cette application est déjà installée. Pour votre sécurité, supprimez install.php du serveur.';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — Catalogue WhatsApp</title>
<style>
body{font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:20px;background:#f6f7f9;color:#17202a}main{background:#fff;padding:28px;border-radius:16px;box-shadow:0 8px 30px #0001}label{display:block;margin:16px 0 6px;font-weight:600}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ccd2d8;border-radius:10px}button{margin-top:20px;padding:12px 18px;border:0;border-radius:10px;cursor:pointer;font-weight:700}.ok{padding:12px;background:#e9f8ee;border-radius:10px}.error{padding:12px;background:#fff0f0;border-radius:10px}small{color:#65717d}
</style>
</head>
<body><main>
<h1>Installation</h1>
<p>Créez le compte administrateur de cette installation.</p>
<?php if ($message): ?><p class="ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (!$installed): ?>
<form method="post" autocomplete="off">
<label for="email">Email administrateur</label>
<input id="email" name="email" type="email" required autocomplete="username">
<label for="password">Mot de passe</label>
<input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="password_confirm">Confirmer le mot de passe</label>
<input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Installer</button>
</form>
<?php endif; ?>
<small>Après installation, supprimez install.php et utilisez HTTPS.</small>
</main></body></html>
