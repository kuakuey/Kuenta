<?php
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

$cuenta = $id ? getCuenta($id) : null;
$tiposPago = getTiposPago();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $monto = parseMonto($_POST['monto'] ?? '0');
    $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';
    $tipoPagoId = $_POST['tipo_pago_id'] !== '' ? (int) $_POST['tipo_pago_id'] : null;
    $estado = $_POST['estado'] ?? 'pendiente';
    $fechaPago = $_POST['fecha_pago'] ?: null;
    $notas = trim($_POST['notas'] ?? '');
    $mesPost = (int) ($_POST['mes'] ?? $mes);
    $anioPost = (int) ($_POST['anio'] ?? $anio);

    if ($nombre === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a cero.';
    }
    if (!$fechaVencimiento) {
        $errors[] = 'La fecha de vencimiento es obligatoria.';
    }

    if ($estado === 'pagado' && !$fechaPago) {
        $fechaPago = date('Y-m-d');
    }
    if ($estado === 'pendiente') {
        $fechaPago = null;
    }

    if (empty($errors)) {
        $db = getDB();

        if ($id) {
            $stmt = $db->prepare("
                UPDATE cuentas SET
                    nombre = ?, monto = ?, fecha_vencimiento = ?, tipo_pago_id = ?,
                    estado = ?, fecha_pago = ?, notas = ?, mes = ?, anio = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([
                $nombre, $monto, $fechaVencimiento, $tipoPagoId,
                $estado, $fechaPago, $notas, $mesPost, $anioPost, $id, getUsuarioId()
            ]);
            flash('success', 'Cuenta actualizada correctamente.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO cuentas (usuario_id, nombre, monto, fecha_vencimiento, tipo_pago_id, valor_asignado, estado, fecha_pago, notas, mes, anio)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                getUsuarioId(), $nombre, $monto, $fechaVencimiento, $tipoPagoId,
                $estado, $fechaPago, $notas, $mesPost, $anioPost
            ]);
            flash('success', 'Cuenta creada correctamente.');
        }

        redirect(urlMes('calendario.php', $mesPost, $anioPost));
    }

    $cuenta = [
        'nombre' => $nombre,
        'monto' => $monto,
        'fecha_vencimiento' => $fechaVencimiento,
        'tipo_pago_id' => $tipoPagoId,
        'estado' => $estado,
        'fecha_pago' => $fechaPago,
        'notas' => $notas,
        'mes' => $mesPost,
        'anio' => $anioPost,
    ];
    $mes = $mesPost;
    $anio = $anioPost;
}

$defaults = [
    'nombre' => '',
    'monto' => '',
    'fecha_vencimiento' => sprintf('%04d-%02d-05', $anio, $mes),
    'tipo_pago_id' => $tiposPago[0]['id'] ?? '',
    'estado' => 'pendiente',
    'fecha_pago' => '',
    'notas' => '',
    'mes' => $mes,
    'anio' => $anio,
];

$data = $cuenta ? array_merge($defaults, $cuenta) : $defaults;

$pageTitle = $id ? 'Editar cuenta' : 'Nueva cuenta';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1><?= $id ? 'Editar pago extra' : 'Pago extra del mes' ?></h1>
        <p class="subtitle">Solo para gastos puntuales. Las fechas fijas se gestionan en Fechas.</p>
    </div>
    <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-ghost">&larr; Volver al calendario</a>
</section>

<section class="card card-form">
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($tiposPago)): ?>
        <div class="alert alert-warning">
            No hay tipos de pago. <a href="tipos-pago.php">Agrega al menos uno</a> para seleccionar rápido.
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <input type="hidden" name="mes" value="<?= (int) $data['mes'] ?>">
        <input type="hidden" name="anio" value="<?= (int) $data['anio'] ?>">

        <div class="form-group span-2">
            <label for="nombre">Nombre de la cuenta *</label>
            <input type="text" id="nombre" name="nombre" value="<?= h($data['nombre']) ?>" placeholder="Ej: Reparación, Regalo, pago extra..." required>
            <?php if (!empty($data['pago_fijo_id'])): ?>
                <span class="text-muted small">Viene de una fecha fija. Para cambiar el valor usa el calendario.</span>
            <?php else: ?>
                <span class="text-muted small">Para cuotas diferidas con fecha fija, usa <a href="pagos-fijos.php">Fechas</a>.</span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="monto">Monto *</label>
            <input type="text" id="monto" name="monto" value="<?= h($data['monto'] !== '' ? number_format((float) $data['monto'], fmod((float) $data['monto'], 1.0) ? 2 : 0, ',', '.') : '') ?>" placeholder="Ej: 85,50" required>
        </div>

        <div class="form-group">
            <label for="fecha_vencimiento">Fecha de vencimiento *</label>
            <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= h($data['fecha_vencimiento']) ?>" required>
        </div>

        <div class="form-group span-2">
            <label for="tipo_pago_id">Tipo de pago</label>
            <div class="tipo-pago-selector">
                <select id="tipo_pago_id" name="tipo_pago_id">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($tiposPago as $tipo): ?>
                        <option value="<?= (int) $tipo['id'] ?>" <?= (string) $data['tipo_pago_id'] === (string) $tipo['id'] ? 'selected' : '' ?>>
                            <?= h($tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="tipo-pago-chips">
                    <?php foreach ($tiposPago as $tipo): ?>
                        <button type="button"
                                class="chip-btn"
                                data-tipo-id="<?= (int) $tipo['id'] ?>"
                                style="--chip-color: <?= h($tipo['color']) ?>">
                            <?= h($tipo['nombre']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="pendiente" <?= $data['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="pagado" <?= $data['estado'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
            </select>
        </div>

        <div class="form-group" id="fecha-pago-group" style="<?= $data['estado'] === 'pagado' ? '' : 'display:none' ?>">
            <label for="fecha_pago">Fecha de pago</label>
            <input type="date" id="fecha_pago" name="fecha_pago" value="<?= h($data['fecha_pago'] ?: date('Y-m-d')) ?>">
        </div>

        <div class="form-group span-2">
            <label for="notas">Detalles / notas</label>
            <textarea id="notas" name="notas" rows="4" placeholder="Número de factura, referencia, observaciones..."><?= h($data['notas']) ?></textarea>
        </div>

        <div class="form-actions span-2">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Guardar cambios' : 'Crear cuenta' ?></button>
            <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
