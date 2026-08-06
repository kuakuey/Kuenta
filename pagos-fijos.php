<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('UPDATE pagos_fijos SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Estado actualizado.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if (eliminarPagoFijo($id)) {
                flash('success', 'Fecha de pago y todas sus fechas del calendario eliminadas.');
            } else {
                flash('error', 'No se encontró la fecha de pago.');
            }
        } catch (Throwable $e) {
            flash('error', 'No se pudo eliminar la fecha de pago.');
        }
    }

    if ($action === 'generar_mes') {
        [$mes, $anio] = parseMesAnio(
            isset($_POST['mes']) ? (int) $_POST['mes'] : null,
            isset($_POST['anio']) ? (int) $_POST['anio'] : null
        );
        $creados = syncPagosFijosMes($mes, $anio);
        flash('success', $creados > 0
            ? "Se crearon {$creados} fechas en " . monthName($mes) . " {$anio}."
            : 'Ese mes ya tenía todas las fechas.');
        redirect(urlMes('calendario.php', $mes, $anio));
    }

    redirect('pagos-fijos.php');
}

$pagos = getPagosFijos(false);
$mes = (int) date('n');
$anio = (int) date('Y');

$pageTitle = 'Fechas de pago';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1>Fechas de pago</h1>
        <p class="subtitle">Paso 1: define en qué día del mes toca pagar cada cuenta</p>
    </div>
    <div class="header-actions">
        <a href="pago-fijo-form.php" class="btn btn-primary">+ Nueva fecha</a>
        <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-ghost">Calendario</a>
    </div>
</section>

<section class="card">
    <div class="info-banner">
        <strong>Tu flujo</strong>
        <ol class="flow-steps">
            <li>Registras aquí las <strong>fechas fijas</strong> (día de corte).</li>
            <li>En el <strong>calendario</strong>, mes a mes, asignas el valor cuando toca.</li>
            <li>En la <strong>Lista</strong>, seleccionas qué cuentas vas a pagar.</li>
            <li>En el calendario pasan a <span class="color-hint green">verde</span> (o <span class="color-hint red">rojo</span> si se vencieron).</li>
        </ol>
    </div>
</section>

<section class="card">
    <?php if (empty($pagos)): ?>
        <p class="empty-state">Aún no hay fechas de pago.</p>
        <a href="pago-fijo-form.php" class="btn btn-primary">Agregar la primera</a>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Cuenta</th>
                        <th>Tipo habitual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr class="<?= $pago['activo'] ? '' : 'row-paid' ?>">
                            <td><strong>Día <?= (int) $pago['dia_pago'] ?></strong></td>
                            <td>
                                <strong><?= h($pago['nombre']) ?></strong>
                                <?php if ($pago['notas']): ?>
                                    <div class="text-muted small"><?= h($pago['notas']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pago['tipo_pago_nombre']): ?>
                                    <span class="badge" style="--badge-color: <?= h($pago['tipo_pago_color']) ?>">
                                        <?= h($pago['tipo_pago_nombre']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $pago['activo'] ? '<span class="status status-paid">Activa</span>' : '<span class="status status-pending">Inactiva</span>' ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="pago-fijo-form.php?id=<?= (int) $pago['id'] ?>" class="btn btn-sm btn-ghost">Editar</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost">
                                            <?= $pago['activo'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar esta fecha de pago y todas sus fechas del calendario?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
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
