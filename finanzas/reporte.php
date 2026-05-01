<?php
/*
 * FINANZAS · REPORTE · serie temporal mensual
 * --------------------------------------------
 * Devuelve KPIs agregados por mes para los últimos N meses (por defecto 6),
 * útil para pintar gráfico de ingresos vs egresos.
 *
 * GET parámetros opcionales:
 *   meses=N (1..24, default 6)
 *
 * Respuesta JSON:
 *   {
 *     ok: true,
 *     serie: [
 *       { mes: '2026-04', etiqueta: 'abr 2026', ingresos: 0.0, egresos: 0.0, balance: 0.0 },
 *       ...
 *     ],
 *     top_categorias_egreso: [ { categoria, total }, ... ]   // top 5 del rango
 *   }
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);

$usuarioId = getUsuarioId();

$modo = $_GET['modo'] ?? 'mensual';
if (!in_array($modo, ['mensual','diario'], true)) $modo = 'mensual';

$meses = isset($_GET['meses']) && is_numeric($_GET['meses'])
    ? max(1, min(24, (int)$_GET['meses']))
    : 6;
$dias = isset($_GET['dias']) && is_numeric($_GET['dias'])
    ? max(7, min(90, (int)$_GET['dias']))
    : 30;

$nombresMes = [
    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may',  6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
];

/* ----- Construir serie según modo ----- */
$serie = [];

if ($modo === 'mensual') {
    for ($i = $meses - 1; $i >= 0; $i--) {
        $ts    = strtotime(date('Y-m-01') . " -$i month");
        $clave = date('Y-m', $ts);
        $serie[$clave] = [
            'periodo'  => $clave,
            'etiqueta' => $nombresMes[(int)date('n', $ts)] . ' ' . date('Y', $ts),
            'ingresos' => 0.0,
            'egresos'  => 0.0,
            'balance'  => 0.0
        ];
    }
    $inicioRango = array_key_first($serie) . '-01';
    $sqlSerie = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS periodo, tipo, SUM(monto) AS total
                 FROM transacciones
                 WHERE usuario_id = ? AND estado = 'activa' AND fecha >= ?
                 GROUP BY DATE_FORMAT(fecha, '%Y-%m'), tipo";
} else {
    /* Diario: últimos N días, etiqueta 'dd/mm' */
    for ($i = $dias - 1; $i >= 0; $i--) {
        $ts    = strtotime("-$i day");
        $clave = date('Y-m-d', $ts);
        $serie[$clave] = [
            'periodo'  => $clave,
            'etiqueta' => date('d/m', $ts),
            'ingresos' => 0.0,
            'egresos'  => 0.0,
            'balance'  => 0.0
        ];
    }
    $inicioRango = array_key_first($serie);
    $sqlSerie = "SELECT DATE_FORMAT(fecha, '%Y-%m-%d') AS periodo, tipo, SUM(monto) AS total
                 FROM transacciones
                 WHERE usuario_id = ? AND estado = 'activa' AND fecha >= ?
                 GROUP BY DATE_FORMAT(fecha, '%Y-%m-%d'), tipo";
}

$stmt = @$conexion->prepare($sqlSerie);
if (!$stmt) {
    echo json_encode(["ok"=>false, "mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
$stmt->bind_param("is", $usuarioId, $inicioRango);
@$stmt->execute();
$r = $stmt->get_result();
while ($f = $r->fetch_assoc()) {
    if (!isset($serie[$f['periodo']])) continue;
    if ($f['tipo'] === 'ingreso') $serie[$f['periodo']]['ingresos'] = (float)$f['total'];
    if ($f['tipo'] === 'egreso')  $serie[$f['periodo']]['egresos']  = (float)$f['total'];
}
foreach ($serie as $k => $v) {
    $serie[$k]['balance'] = $serie[$k]['ingresos'] - $serie[$k]['egresos'];
}
$stmt->close();

/* ----- Top categorías de egreso del rango ----- */
$top = [];
$stmtTop = @$conexion->prepare(
    "SELECT categoria, SUM(monto) AS total
     FROM transacciones
     WHERE usuario_id = ? AND estado = 'activa' AND tipo = 'egreso' AND fecha >= ?
     GROUP BY categoria
     ORDER BY total DESC
     LIMIT 5"
);
if ($stmtTop) {
    $stmtTop->bind_param("is", $usuarioId, $inicioRango);
    @$stmtTop->execute();
    $r = $stmtTop->get_result();
    while ($f = $r->fetch_assoc()) {
        $top[] = ['categoria' => $f['categoria'], 'total' => (float)$f['total']];
    }
    $stmtTop->close();
}

/* ----- Distribución de ingresos por categoría (rango entero) ----- */
$ingresosCat = [];
$stmtIngCat = @$conexion->prepare(
    "SELECT categoria, SUM(monto) AS total
     FROM transacciones
     WHERE usuario_id = ? AND estado = 'activa' AND tipo = 'ingreso' AND fecha >= ?
     GROUP BY categoria
     ORDER BY total DESC"
);
if ($stmtIngCat) {
    $stmtIngCat->bind_param("is", $usuarioId, $inicioRango);
    @$stmtIngCat->execute();
    $r = $stmtIngCat->get_result();
    while ($f = $r->fetch_assoc()) {
        $monto = (float)$f['total'];
        if ($monto > 0) {
            $ingresosCat[] = ['categoria' => $f['categoria'], 'total' => $monto];
        }
    }
    $stmtIngCat->close();
}

$conexion->close();

echo json_encode([
    "ok"                       => true,
    "modo"                     => $modo,
    "serie"                    => array_values($serie),
    "top_categorias_egreso"    => $top,
    "ingresos_por_categoria"   => $ingresosCat
]);
