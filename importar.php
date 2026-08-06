<?php

require_once __DIR__ . '/config/install.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$action = $_GET['accion'] ?? $_POST['accion'] ?? '';
$log = [];
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $action === 'importar') {
    try {
        if ($action === 'importar_url') {
            $url = trim($_POST['sql_url'] ?? $_GET['sql_url'] ?? '');
            if ($url === '') {
                throw new InvalidArgumentException('Debes indicar una URL con archivo SQL.');
            }
            $log = importSqlFromUrl($url);
        } else {
            $log = runInstall();
        }
        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$status = getInstallStatus();
$pageTitle = 'Importar base de datos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Cuentas Hogar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <span class="logo-icon">🏠</span>
                <span>Cuentas Hogar</span>
            </a>
        </div>
    </header>

    <main class="container main-content">
        <section class="page-header">
            <div>
                <h1>Importar base de datos</h1>
                <p class="subtitle">Configura MySQL desde el navegador sin usar phpMyAdmin</p>
            </div>
            <?php if ($status['ready']): ?>
                <a href="index.php" class="btn btn-primary">Ir al inicio</a>
            <?php endif; ?>
        </section>

        <div class="grid-2">
            <section class="card">
                <h2>Estado actual</h2>
                <ul class="status-list">
                    <li class="<?= $status['mysql']['ok'] ? 'ok' : 'fail' ?>">
                        <strong>MySQL</strong>
                        <span><?= h($status['mysql']['message']) ?></span>
                    </li>
                    <li class="<?= $status['database']['ok'] ? 'ok' : 'fail' ?>">
                        <strong>Base de datos</strong>
                        <span><?= h($status['database']['message']) ?></span>
                    </li>
                    <li class="<?= $status['tables']['ok'] ? 'ok' : 'fail' ?>">
                        <strong>Tablas</strong>
                        <span><?= h($status['tables']['message']) ?></span>
                    </li>
                </ul>

                <?php if ($status['ready']): ?>
                    <div class="alert alert-success">
                        Sistema listo: <?= (int) $status['tipos_pago'] ?> tipos de pago,
                        <?= (int) $status['cuentas'] ?> cuentas registradas.
                    </div>
                <?php endif; ?>

                <div class="config-box">
                    <h3>Configuración en uso</h3>
                    <p><strong>Host:</strong> <?= h(DB_HOST) ?></p>
                    <p><strong>Base de datos:</strong> <?= h(DB_NAME) ?></p>
                    <p><strong>Usuario:</strong> <?= h(DB_USER) ?></p>
                    <p class="text-muted small">Edita <code>config/db.php</code> si necesitas cambiar credenciales.</p>
                </div>
            </section>

            <section class="card card-form">
                <h2>Importar</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($success && $log): ?>
                    <div class="alert alert-success">Importación completada correctamente.</div>
                    <ul class="import-log">
                        <?php foreach ($log as $entry): ?>
                            <li><?= h($entry) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" class="form-grid">
                    <input type="hidden" name="accion" value="importar">
                    <div class="form-group span-2">
                        <p>Importa el esquema local <code>database/schema.sql</code> y crea la base de datos automáticamente.</p>
                    </div>
                    <div class="form-actions span-2">
                        <button type="submit" class="btn btn-primary">Importar base de datos</button>
                        <a href="importar.php?accion=importar" class="btn btn-secondary">Importar desde URL directa</a>
                    </div>
                </form>

                <hr class="divider">

                <form method="post" class="form-grid">
                    <input type="hidden" name="accion" value="importar_url">
                    <div class="form-group span-2">
                        <label for="sql_url">Importar SQL desde URL</label>
                        <input
                            type="url"
                            id="sql_url"
                            name="sql_url"
                            placeholder="https://ejemplo.com/schema.sql"
                            value="<?= h($_POST['sql_url'] ?? '') ?>"
                        >
                        <span class="text-muted small">Pega la URL pública de un archivo .sql</span>
                    </div>
                    <div class="form-actions span-2">
                        <button type="submit" class="btn btn-secondary">Descargar e importar</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="card">
            <h2>Enlaces rápidos</h2>
            <div class="quick-actions">
                <a href="importar.php?accion=importar" class="btn btn-secondary btn-block">
                    http://localhost/Cuentashogar/importar.php?accion=importar
                </a>
                <a href="http://localhost/phpmyadmin" class="btn btn-ghost btn-block" target="_blank" rel="noopener">
                    Abrir phpMyAdmin
                </a>
            </div>
        </section>
    </main>
</body>
</html>
