<?php
/*
 * AGENDA · listar memorias personales
 * GET ?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD (opcional)
 *   - Si no se pasan, devuelve los últimos 30 días + futuros próximos
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';
$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

$desde = $_GET['fecha_inicio'] ?? '';
$hasta = $_GET['fecha_fin']    ?? '';

$where  = "usuario_id = ?";
$params = [$usuarioId];
$types  = 'i';

if ($desde !== '' && strtotime($desde)) {
    $where .= " AND fecha >= ?";  $params[] = $desde; $types .= 's';
} else {
    $where .= " AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}
if ($hasta !== '' && strtotime($hasta)) {
    $where .= " AND fecha <= ?";  $params[] = $hasta; $types .= 's';
}

$sql = "SELECT memoria_id, contenido, fecha, color, creado_en, actualizado_en
        FROM personal_memories
        WHERE $where
        ORDER BY fecha ASC, memoria_id ASC";

$stmt = @$conexion->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
    $conexion->close(); exit;
}
if ($params) $stmt->bind_param($types, ...$params);
@$stmt->execute();
$r = $stmt->get_result();
$memorias = [];
while ($f = $r->fetch_assoc()) $memorias[] = $f;
$stmt->close();
$conexion->close();

echo json_encode(["ok"=>true,"memorias"=>$memorias]);
