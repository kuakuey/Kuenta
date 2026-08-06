<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

ensureMesListo($mes, $anio);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_map('intval', $_POST['cuentas'] ?? []);
    $fechaPago = $_POST['fecha_pago'] ?: date('Y-m-d');
    $pagadas = marcarCuentasPagadas($ids, $fechaPago);

    flash('success', $pagadas > 0
        ? "Se registraron {$pagadas} pago(s) correctamente."
        : 'No se seleccionó ningún pago válido.');

    redirect(urlMes('calendario.php', $mes, $anio));
}

$cuentas = getCuentasParaPagar($mes, $anio);
$totalPendiente = array_sum(array_column($cuentas, 'monto'));

$pageTitle = 'Pagar';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1>¿Qué vas a pagar?</h1>
        <p class="subtitle"><?= monthName($mes) ?> <?= $anio ?> — selecciona las cuentas que pagarás ahora</p>
    </div>
    <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-ghost">&larr; Volver al calendario</a>
</section>

<section class="card">
    <?php if (empty($cuentas)): ?>
        <p class="empty-state">No hay cuentas listas para pagar.</p>
        <p class="text-muted">Primero asigna el valor en el calendario (fechas sin valor aparecen en gris).</p>
        <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-primary">Ir al calendario</a>
    <?php else: ?>
        <form method="post" id="form-pagar">
            <div class="pagar-toolbar">
                <label class="checkbox-label">
                    <input type="checkbox" id="select-all">
                    Seleccionar todas (<?= count($cuentas) ?>)
                </label>
                <strong id="total-seleccionado">Total: $0</strong>
            </div>

            <ul class="pagar-list">
                <?php foreach ($cuentas as $cuenta):
                    $visual = cuentaEstadoVisual($cuenta);
                ?>
                    <li class="pagar-item <?= $visual ?>">
                        <label class="pagar-item-label">
                            <input type="checkbox" name="cuentas[]" value="<?= (int) $cuenta['id'] ?>" class="cuenta-check" data-monto="<?= (float) $cuenta['monto'] ?>">
                            <div class="pagar-item-body">
                                <div class="pagar-item-top">
                                    <strong><?= h($cuenta['nombre']) ?></strong>
                                    <span class="amount"><?= formatMoney((float) $cuenta['monto']) ?></span>
                                </div>
                                <div class="pagar-item-meta">
                                    <span>Vence: <?= formatDate($cuenta['fecha_vencimiento']) ?></span>
                                    <?php if ($cuenta['tipo_pago_nombre']): ?>
                                        <span class="badge" style="--badge-color: <?= h($cuenta['tipo_pago_color']) ?>">
                                            <?= h($cuenta['tipo_pago_nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($visual === 'overdue'): ?>
                                        <span class="status status-overdue">Vencida</span>
                                    <?php else: ?>
                                        <span class="status status-pending">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($cuenta['notas']): ?>
                                    <div class="text-muted small"><?= h($cuenta['notas']) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="pagar-footer form-grid">
                <div class="form-group">
                    <label for="fecha_pago">Fecha de pago</label>
                    <input type="date" id="fecha_pago" name="fecha_pago" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Registrar pagos seleccionados</button>
                </div>
            </div>
        </form>

        <p class="text-muted small pagar-hint">
            Total pendiente del mes con valor asignado: <?= formatMoney($totalPendiente) ?>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
