<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'marcar_pagado') {
        $id = (int) ($_POST['id'] ?? 0);
        $fechaPago = $_POST['fecha_pago'] ?: date('Y-m-d');
        $stmt = getDB()->prepare("UPDATE cuentas SET estado = 'pagado', fecha_pago = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$fechaPago, $id, getUsuarioId()]);
        flash('success', 'Cuenta marcada como pagada.');
    }

    if ($action === 'marcar_pendiente') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare("UPDATE cuentas SET estado = 'pendiente', fecha_pago = NULL WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Cuenta marcada como pendiente.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('DELETE FROM cuentas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Cuenta eliminada.');
    }

    redirect(urlMes('calendario.php', $mes, $anio));
}

ensureMesListo($mes, $anio);

$cuentas = getCuentasMes($mes, $anio);
$resumen = resumenMes($mes, $anio);
$filtro = $_GET['filtro'] ?? 'todas';

if ($filtro === 'pendientes') {
    $cuentas = array_values(array_filter($cuentas, fn($c) => $c['estado'] === 'pendiente'));
} elseif ($filtro === 'pagadas') {
    $cuentas = array_values(array_filter($cuentas, fn($c) => $c['estado'] === 'pagado'));
}

$pageTitle = 'Cuentas';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1>Cuentas del mes</h1>
        <p class="subtitle"><?= monthName($mes) ?> <?= $anio ?> &mdash; <?= formatMoney((float) $resumen['monto_total']) ?> total</p>
    </div>
    <div class="header-actions">
        <div class="month-nav">
            <a href="<?= urlMes('cuentas.php', $mes - 1, $anio, ['filtro' => $filtro]) ?>" class="btn btn-ghost">&larr;</a>
            <a href="<?= urlMes('cuentas.php', (int) date('n'), (int) date('Y'), ['filtro' => $filtro]) ?>" class="btn btn-ghost">Hoy</a>
            <a href="<?= urlMes('cuentas.php', $mes + 1, $anio, ['filtro' => $filtro]) ?>" class="btn btn-ghost">&rarr;</a>
        </div>
        <a href="cuenta-form.php?mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-primary">+ Nueva cuenta</a>
    </div>
</section>

<div class="filter-tabs">
    <a href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'todas']) ?>" class="<?= $filtro === 'todas' ? 'active' : '' ?>">Todas (<?= (int) $resumen['total'] ?>)</a>
    <a href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pendientes']) ?>" class="<?= $filtro === 'pendientes' ? 'active' : '' ?>">Pendientes (<?= (int) $resumen['pendientes'] ?>)</a>
    <a href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pagadas']) ?>" class="<?= $filtro === 'pagadas' ? 'active' : '' ?>">Pagadas (<?= (int) $resumen['pagadas'] ?>)</a>
</div>

<section class="card">
    <?php if (empty($cuentas)): ?>
        <p class="empty-state">No hay cuentas registradas para este mes.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <th>Vencimiento</th>
                        <th>Monto</th>
                        <th>Tipo de pago</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cuentas as $cuenta): ?>
                        <tr class="<?= $cuenta['estado'] === 'pagado' ? 'row-paid' : '' ?>">
                            <td>
                                <strong><?= h($cuenta['nombre']) ?></strong>
                                <?php if ($cuenta['pago_fijo_id']): ?>
                                    <span class="badge badge-fixed">Fijo</span>
                                <?php endif; ?>
                                <?php if ($cuenta['notas']): ?>
                                    <div class="text-muted small"><?= h($cuenta['notas']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($cuenta['fecha_vencimiento']) ?></td>
                            <td class="amount"><?= formatMoney((float) $cuenta['monto']) ?></td>
                            <td>
                                <?php if ($cuenta['tipo_pago_nombre']): ?>
                                    <span class="badge" style="--badge-color: <?= h($cuenta['tipo_pago_color']) ?>">
                                        <?= h($cuenta['tipo_pago_nombre']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cuenta['estado'] === 'pagado'): ?>
                                    <span class="status status-paid">Pagado<?= $cuenta['fecha_pago'] ? ' · ' . formatDate($cuenta['fecha_pago']) : '' ?></span>
                                <?php else: ?>
                                    <span class="status status-pending">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="cuenta-form.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-sm btn-ghost">Editar</a>
                                    <?php if ($cuenta['estado'] === 'pendiente'): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="action" value="marcar_pagado">
                                            <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                            <input type="hidden" name="fecha_pago" value="<?= date('Y-m-d') ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Pagar</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="action" value="marcar_pendiente">
                                            <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-ghost">Desmarcar</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar esta cuenta?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
