<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';

        if ($nombre !== '') {
            try {
                $stmt = getDB()->prepare('INSERT INTO tipos_pago (usuario_id, nombre, color) VALUES (?, ?, ?)');
                $stmt->execute([getUsuarioId(), $nombre, $color]);
                flash('success', 'Tipo de pago agregado.');
            } catch (PDOException $e) {
                flash('error', 'Ya existe un tipo de pago con ese nombre.');
            }
        }
    }

    if ($action === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';

        if ($id && $nombre !== '') {
            try {
                $stmt = getDB()->prepare('UPDATE tipos_pago SET nombre = ?, color = ? WHERE id = ? AND usuario_id = ?');
                $stmt->execute([$nombre, $color, $id, getUsuarioId()]);
                flash('success', 'Tipo de pago actualizado.');
            } catch (PDOException $e) {
                flash('error', 'Ya existe un tipo de pago con ese nombre.');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('UPDATE tipos_pago SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Estado del tipo de pago actualizado.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('DELETE FROM tipos_pago WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Tipo de pago eliminado.');
    }

    redirect('tipos-pago.php');
}

$tipos = getTiposPago(false);
$pageTitle = 'Tipos de pago';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1>Tipos de pago</h1>
        <p class="subtitle">Agrega métodos para seleccionarlos rápido al registrar cuentas</p>
    </div>
</section>

<div class="grid-2">
    <section class="card card-form">
        <h2>Nuevo tipo de pago</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="crear">

            <div class="form-group span-2">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ej: Pichincha, Deuna, Efectivo..." required>
            </div>

            <div class="form-group">
                <label for="color">Color</label>
                <input type="color" id="color" name="color" value="#6366f1">
            </div>

            <div class="form-actions span-2">
                <button type="submit" class="btn btn-primary">Agregar</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Tipos registrados</h2>
        <?php if (empty($tipos)): ?>
            <p class="empty-state">Aún no hay tipos de pago.</p>
        <?php else: ?>
            <ul class="tipo-list">
                <?php foreach ($tipos as $tipo): ?>
                    <li class="tipo-item <?= $tipo['activo'] ? '' : 'inactive' ?>">
                        <form method="post" class="tipo-edit-form">
                            <input type="hidden" name="action" value="editar">
                            <input type="hidden" name="id" value="<?= (int) $tipo['id'] ?>">
                            <span class="color-dot" style="background: <?= h($tipo['color']) ?>"></span>
                            <input type="text" name="nombre" value="<?= h($tipo['nombre']) ?>" required>
                            <input type="color" name="color" value="<?= h($tipo['color']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">Guardar</button>
                        </form>
                        <div class="tipo-item-actions">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $tipo['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">
                                    <?= $tipo['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar este tipo de pago?')">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $tipo['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
