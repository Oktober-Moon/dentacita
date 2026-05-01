<?php
/*
 * FINANZAS · exportar transacciones a CSV (Excel-friendly)
 * --------------------------------------------------------
 * Acepta los mismos filtros que mostrar.php (tipo, estado, desde, hasta,
 * categoria, paciente_id, metodo). Devuelve un CSV con BOM UTF-8 para
 * que Excel respete acentos y separe correctamente.
 */

require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionVista($conexion, '../');

$usuarioId = getUsuarioId();

$tipo       = $_GET['tipo']        ?? '';
$estado     = $_GET['estado']      ?? 'activa';
$desde      = $_GET['desde']       ?? '';
$hasta      = $_GET['hasta']       ?? '';
$categoria  = trim($_GET['categoria']  ?? '');
$pacienteId = $_GET['paciente_id'] ?? '';
$metodo     = $_GET['metodo']      ?? '';

$where  = ["t.usuario_id = ?"];
$params = [$usuarioId];
$types  = 'i';

if ($tipo !== '' && in_array($tipo, ['ingreso','egreso'], true)) {
    $where[] = "t.tipo = ?";  $params[] = $tipo; $types .= 's';
}
if ($estado !== 'todas' && in_array($estado, ['activa','anulada'], true)) {
    $where[] = "t.estado = ?"; $params[] = $estado; $types .= 's';
}
if ($desde !== '' && strtotime($desde)) { $where[] = "t.fecha >= ?"; $params[] = $desde; $types .= 's'; }
if ($hasta !== '' && strtotime($hasta)) { $where[] = "t.fecha <= ?"; $params[] = $hasta; $types .= 's'; }
if ($categoria !== '' && mb_strlen($categoria) <= 50) {
    $where[] = "t.categoria = ?"; $params[] = $categoria; $types .= 's';
}
if ($metodo !== '' && in_array($metodo, ['efectivo','tarjeta','transferencia','cheque','otro'], true)) {
    $where[] = "t.metodo_pago = ?"; $params[] = $metodo; $types .= 's';
}
if ($pacienteId !== '' && is_numeric($pacienteId)) {
    $pidInt = (int)$pacienteId;
    $where[] = "t.paciente_id = ?"; $params[] = $pidInt; $types .= 'i';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT
            t.fecha, t.tipo, t.categoria, t.descripcion,
            t.monto, t.metodo_pago, t.estado,
            p.nombre_completo AS paciente_nombre,
            c.titulo          AS cita_titulo,
            i.nombre          AS item_nombre,
            t.anulada_en, t.motivo_anulacion
        FROM transacciones t
        LEFT JOIN pacientes p          ON t.paciente_id        = p.paciente_id
        LEFT JOIN citas c              ON t.cita_id            = c.cita_id
        LEFT JOIN inventario_items i   ON t.inventario_item_id = i.item_id
        $whereSql
        ORDER BY t.fecha DESC, t.transaccion_id DESC";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Error al preparar la consulta.";
    $conexion->close(); exit;
}
$stmt->bind_param($types, ...$params);
@$stmt->execute();
$res = $stmt->get_result();

// Headers Excel-friendly
$nombreArchivo = 'transacciones_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

// BOM UTF-8 para que Excel respete acentos
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, [
    'Fecha', 'Tipo', 'Categoría', 'Descripción',
    'Monto', 'Método de pago', 'Estado',
    'Paciente', 'Cita', 'Producto',
    'Anulada en', 'Motivo anulación'
], ';');

while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
        $row['fecha'],
        $row['tipo'],
        $row['categoria'],
        $row['descripcion'],
        number_format((float)$row['monto'], 2, '.', ''),
        $row['metodo_pago'],
        $row['estado'],
        $row['paciente_nombre'] ?? '',
        $row['cita_titulo']     ?? '',
        $row['item_nombre']     ?? '',
        $row['anulada_en']      ?? '',
        $row['motivo_anulacion'] ?? ''
    ], ';');
}

fclose($out);
$stmt->close();
$conexion->close();
