<?php
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
[$mesCalendario, $anioCalendario] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

$pago = $id ? getPagoFijo($id) : null;
$tiposPago = getTiposPago();
$errors = [];
$desdeCalendario = !$id && isset($_GET['dia']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $diaPago = (int) ($_POST['dia_pago'] ?? 0);
    $tipoPagoId = $_POST['tipo_pago_id'] !== '' ? (int) $_POST['tipo_pago_id'] : null;
    $notas = trim($_POST['notas'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $generarFuturos = isset($_POST['generar_futuros']);
    $mesCalendario = (int) ($_POST['mes_calendario'] ?? date('n'));
    $anioCalendario = (int) ($_POST['anio_calendario'] ?? date('Y'));

    if ($nombre === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if ($diaPago < 1 || $diaPago > 31) {
        $errors[] = 'El día de corte debe estar entre 1 y 31.';
    }

    if (empty($errors)) {
        $db = getDB();

        if ($id) {
            $stmt = $db->prepare('
                UPDATE pagos_fijos
                SET nombre = ?, dia_pago = ?, tipo_pago_id = ?, notas = ?, activo = ?
                WHERE id = ? AND usuario_id = ?
            ');
            $stmt->execute([$nombre, $diaPago, $tipoPagoId, $notas, $activo, $id, getUsuarioId()]);
            flash('success', 'Fecha de pago actualizada.');
        } else {
            $stmt = $db->prepare('
                INSERT INTO pagos_fijos (usuario_id, nombre, dia_pago, tipo_pago_id, notas, activo)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([getUsuarioId(), $nombre, $diaPago, $tipoPagoId, $notas, $activo]);

            if ($generarFuturos) {
                $creados = syncPagosFijosDesde($mesCalendario, $anioCalendario);
                flash('success', "Fecha creada. Aparecerá en {$creados} mes(es) a partir de " . monthName($mesCalendario) . " {$anioCalendario}.");
            } else {
                flash('success', 'Fecha de pago creada.');
            }
        }

        redirect(urlMes('calendario.php', $mesCalendario, $anioCalendario));
    }

    $pago = [
        'nombre' => $nombre,
        'dia_pago' => $diaPago,
        'tipo_pago_id' => $tipoPagoId,
        'notas' => $notas,
        'activo' => $activo,
    ];
}

$diaPreseleccionado = isset($_GET['dia']) ? (int) $_GET['dia'] : null;
if ($diaPreseleccionado !== null && ($diaPreseleccionado < 1 || $diaPreseleccionado > 31)) {
    $diaPreseleccionado = null;
}

$defaults = [
    'nombre' => '',
    'dia_pago' => $diaPreseleccionado ?? 5,
    'tipo_pago_id' => $tiposPago[0]['id'] ?? '',
    'notas' => '',
    'activo' => 1,
];

$data = $pago ? array_merge($defaults, $pago) : $defaults;

$pageTitle = $id ? 'Editar fecha de pago' : 'Agregar en el calendario';
$volverUrl = urlMes('calendario.php', $mesCalendario, $anioCalendario);
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1><?= $id ? 'Editar fecha de pago' : 'Agregar fecha de pago' ?></h1>
        <p class="subtitle">
            <?php if ($desdeCalendario): ?>
                Día <?= (int) $data['dia_pago'] ?> · <?= monthName($mesCalendario) ?> <?= $anioCalendario ?> y todos los meses siguientes
            <?php else: ?>
                Solo la fecha es fija. El valor lo registras mes a mes en el calendario.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= $volverUrl ?>" class="btn btn-ghost">&larr; Calendario</a>
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

    <?php if ($desdeCalendario): ?>
        <div class="info-banner">
            <p>Esta cuenta aparecerá el <strong>día <?= (int) $data['dia_pago'] ?></strong> de <?= monthName($mesCalendario) ?> <?= $anioCalendario ?> en adelante. El valor lo asignas cuando toque cada mes.</p>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <input type="hidden" name="mes_calendario" value="<?= $mesCalendario ?>">
        <input type="hidden" name="anio_calendario" value="<?= $anioCalendario ?>">

        <div class="form-group span-2">
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" value="<?= h($data['nombre']) ?>" placeholder="Ej: Tarjeta Pacífico, Supermaxi, Internet..." required autofocus>
        </div>

        <div class="form-group span-2">
            <label for="dia_pago">Día de corte / pago fijo *</label>
            <select id="dia_pago" name="dia_pago" required>
                <?php for ($d = 1; $d <= 31; $d++): ?>
                    <option value="<?= $d ?>" <?= (int) $data['dia_pago'] === $d ? 'selected' : '' ?>>
                        Día <?= $d ?> de cada mes
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group span-2">
            <label for="tipo_pago_id">Tipo de pago habitual</label>
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

        <div class="form-group span-2">
            <label for="notas">Detalles</label>
            <textarea id="notas" name="notas" rows="3" placeholder="Banco, producto, número de crédito..."><?= h($data['notas']) ?></textarea>
        </div>

        <div class="form-group span-2">
            <label class="checkbox-label">
                <input type="checkbox" name="activo" value="1" <?= $data['activo'] ? 'checked' : '' ?>>
                Activa (aparece en el calendario)
            </label>
        </div>

        <?php if (!$id): ?>
            <div class="form-group span-2">
                <label class="checkbox-label">
                    <input type="checkbox" name="generar_futuros" value="1" checked>
                    Crear en <?= monthName($mesCalendario) ?> <?= $anioCalendario ?> y todos los meses siguientes
                </label>
            </div>
        <?php endif; ?>

        <div class="form-actions span-2">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Guardar' : 'Agregar al calendario' ?></button>
            <a href="<?= $volverUrl ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
