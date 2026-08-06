<?php
require_once __DIR__ . '/includes/functions.php';

if (getUsuarioId()) {
    redirect('calendario.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        $result = registrarUsuario($nombre, $email, $password);
        if ($result['ok']) {
            flash('success', '¡Cuenta creada! Ya puedes organizar tus pagos.');
            redirect('calendario.php');
        }
        $errors[] = $result['error'];
    }
}

$pageTitle = 'Crear cuenta';
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Cuentas Hogar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="logo-icon">🏠</span>
                <h1>Crear cuenta</h1>
                <p>Tus pagos, tu calendario, privados</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="email">Correo</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" minlength="6" required>
                </div>
                <div class="form-group">
                    <label for="password2">Confirmar contraseña</label>
                    <input type="password" id="password2" name="password2" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Registrarme</button>
            </form>

            <p class="auth-switch">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
            </p>
        </div>
    </main>
</body>
</html>
