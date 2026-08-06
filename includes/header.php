<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Cuentas Hogar') ?> — Cuentas Hogar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="calendario.php" class="logo">
                <span>Kuenta</span>
            </a>
            <nav class="main-nav">
                <a href="calendario.php" class="<?= in_array($currentPage, ['index', 'calendario', 'asignar-valor'], true) ? 'active' : '' ?>">Calendario</a>
                <a href="pagos-fijos.php" class="<?= in_array($currentPage, ['pagos-fijos', 'pago-fijo-form'], true) ? 'active' : '' ?>">Fechas</a>
                <a href="cuentas.php" class="<?= in_array($currentPage, ['cuentas', 'cuenta-form', 'pagar'], true) ? 'active' : '' ?>">Lista</a>
                <a href="tipos-pago.php" class="<?= $currentPage === 'tipos-pago' ? 'active' : '' ?>">Tipos de pago</a>
            </nav>
            <?php $usuario = getUsuarioActual(); if ($usuario): ?>
                <div class="user-menu">
                    <span class="user-name"><?= h($usuario['nombre']) ?></span>
                    <a href="logout.php" class="btn btn-sm btn-ghost">Salir</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="container main-content">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?>">
                <?= h($flash['message']) ?>
            </div>
        <?php endif; ?>
