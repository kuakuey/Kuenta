<?php
require_once __DIR__ . '/includes/functions.php';

if (getUsuarioId()) {
    redirect('calendario.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (loginUsuario($email, $password)) {
        redirect('calendario.php');
    }

    $error = 'Correo o contraseña incorrectos.';
}

$pageTitle = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Cuentas Hogar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="logo-icon">🏠</span>
                <h1>Cuentas Hogar</h1>
                <p>Ecuador · Organiza tus pagos mensuales</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?>">
                    <?= h($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="email">Correo</label>
                    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>

            <p class="auth-switch">
                ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
            </p>
        </div>
    </main>
</body>
</html>
