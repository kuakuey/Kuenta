<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

ensureMesListo($mes, $anio);

$cuentasPorDia = getCuentasCalendario($mes, $anio);
$paraPagar = getCuentasParaPagar($mes, $anio);

$firstDay = mktime(0, 0, 0, $mes, 1, $anio);
$daysInMonth = (int) date('t', $firstDay);
$startWeekday = (int) date('N', $firstDay);
$today = (int) date('j');
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

$pageTitle = 'Calendario';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div>
        <h1><?= monthName($mes) ?> <?= $anio ?></h1>
    </div>
    <div class="header-actions">
        <div class="month-nav">
            <a href="<?= urlMes('calendario.php', $mes - 1, $anio) ?>" class="btn btn-ghost">&larr;</a>
            <a href="<?= urlMes('calendario.php', (int) date('n'), (int) date('Y')) ?>" class="btn btn-ghost">Hoy</a>
            <a href="<?= urlMes('calendario.php', $mes + 1, $anio) ?>" class="btn btn-ghost">&rarr;</a>
        </div>
    </div>
</section>

<div class="calendar-layout">
    <section class="card calendar-card">
        <div class="calendar-grid">
            <div class="calendar-weekday">Lun</div>
            <div class="calendar-weekday">Mar</div>
            <div class="calendar-weekday">Mié</div>
            <div class="calendar-weekday">Jue</div>
            <div class="calendar-weekday">Vie</div>
            <div class="calendar-weekday">Sáb</div>
            <div class="calendar-weekday">Dom</div>

            <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $isToday = ($day === $today && $mes === $currentMonth && $anio === $currentYear);
                $dayCuentas = $cuentasPorDia[$day] ?? [];
                $totalDia = array_sum(array_map(fn($c) => cuentaTieneValor($c) ? (float) $c['monto'] : 0, $dayCuentas));
                $dayVisual = 'normal';
                foreach ($dayCuentas as $c) {
                    $v = cuentaEstadoVisual($c);
                    if ($v === 'overdue') { $dayVisual = 'overdue'; break; }
                    if ($v === 'pending' && $dayVisual !== 'overdue') { $dayVisual = 'pending'; }
                    if ($v === 'unassigned' && $dayVisual === 'normal') { $dayVisual = 'unassigned'; }
                }
            ?>
                <div class="calendar-day <?= $isToday ? 'today' : '' ?> day-<?= $dayVisual ?>">
                    <div class="calendar-day-header">
                        <a href="pago-fijo-form.php?dia=<?= $day ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"
                           class="day-number day-add-link"
                           title="Agregar cuenta el día <?= $day ?>">
                            <?= $day ?>
                        </a>
                        <?php if ($totalDia > 0): ?>
                            <span class="day-total"><?= formatMoneyShort($totalDia) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="calendar-events">
                        <?php foreach ($dayCuentas as $cuenta):
                            $visual = cuentaEstadoVisual($cuenta);
                            $link = !cuentaValorAsignado($cuenta) || ($visual === 'pending' || $visual === 'overdue')
                                ? 'asignar-valor.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio
                                : 'cuenta-form.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio;
                        ?>
                            <a href="<?= $link ?>"
                               class="calendar-event event-<?= $visual ?>"
                               title="<?= h($cuenta['nombre']) ?> — <?= formatMoneyOrPending($cuenta) ?>">
                                <span class="event-name"><?= h($cuenta['nombre']) ?></span>
                                <span class="event-amount"><?= formatMoneyOrPending($cuenta) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <a href="pago-fijo-form.php?dia=<?= $day ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"
                           class="calendar-add-event"
                           title="Agregar cuenta este día">
                            +
                        </a>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <aside class="calendar-sidebar">
        <section class="card">
            <div class="card-header">
                <h2>Por pagar</h2>
                <?php if (!empty($paraPagar)): ?>
                    <a href="<?= urlMes('pagar.php', $mes, $anio) ?>" class="link">Pagar</a>
                <?php endif; ?>
            </div>
            <?php if (empty($paraPagar)): ?>
                <p class="empty-state small">Nada pendiente este mes.</p>
            <?php else: ?>
                <ul class="sidebar-payments">
                    <?php foreach ($paraPagar as $cuenta):
                        $visual = cuentaEstadoVisual($cuenta);
                    ?>
                        <li class="sidebar-payment-item">
                            <a href="asignar-valor.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="sidebar-link">
                                <strong><?= h($cuenta['nombre']) ?></strong>
                                <span class="text-muted">
                                    Día <?= (int) date('j', strtotime($cuenta['fecha_vencimiento'])) ?>
                                    · <?= formatMoney((float) $cuenta['monto']) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= urlMes('pagar.php', $mes, $anio) ?>" class="btn btn-primary btn-block">Ir a pagar</a>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
