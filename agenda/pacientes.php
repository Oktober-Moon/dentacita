<?php
/*
 * PACIENTES · endpoint AJAX (JSON)
 * ----------------------------------
 * Soporta tres acciones:
 *   GET  ?accion=buscar&q=...     → buscar pacientes por nombre
 *   GET  ?accion=listar-recientes → últimos 15 pacientes (sin filtro)
 *   POST  accion=crear            → crear paciente con todos los campos
 *
 * No es un módulo CRUD completo (eso vive en pacientes/). Es solo lo
 * necesario para el flujo de "agendar cita": buscar entre los existentes,
 * listar los más recientes, o registrar uno nuevo con la misma ficha
 * completa que el modal grande del módulo Pacientes.
 */

header('Content-Type: application/json');
require __DIR__ . '/../conexion.php';

$conexion = obtenerConexion();
exigirSesionAjax($conexion);
$usuarioId = getUsuarioId();

require __DIR__ . '/_validaciones.php';


/* ============================================================
 * GET · LISTAR RECIENTES (últimos 15 sin filtro)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'listar-recientes') {
    $stmt = @$conexion->prepare(
        "SELECT paciente_id, nombre_completo, telefono, email
         FROM pacientes
         WHERE usuario_id = ?
         ORDER BY paciente_id DESC
         LIMIT 15"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $stmt->bind_param("i", $usuarioId);
    @$stmt->execute();
    $res = $stmt->get_result();
    $pacientes = [];
    while ($f = $res->fetch_assoc()) $pacientes[] = $f;
    $stmt->close(); $conexion->close();
    echo json_encode(["ok"=>true,"pacientes"=>$pacientes]);
    exit;
}


/* ============================================================
 * GET · BUSCAR PACIENTES
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'buscar') {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        echo json_encode(["ok"=>true,"pacientes"=>[]]);
        exit;
    }
    if (mb_strlen($q) > 100) {
        echo json_encode(["ok"=>false,"mensaje"=>"Búsqueda demasiado larga."]);
        exit;
    }

    $stmt = @$conexion->prepare(
        "SELECT paciente_id, nombre_completo, telefono, email
         FROM pacientes
         WHERE usuario_id = ? AND nombre_completo LIKE ?
         ORDER BY nombre_completo ASC
         LIMIT 15"
    );
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }
    $like = '%' . $q . '%';
    $stmt->bind_param("is", $usuarioId, $like);
    @$stmt->execute();
    $res = $stmt->get_result();

    $pacientes = [];
    while ($f = $res->fetch_assoc()) $pacientes[] = $f;

    $stmt->close(); $conexion->close();
    echo json_encode(["ok"=>true,"pacientes"=>$pacientes]);
    exit;
}


/* ============================================================
 * POST · CREAR PACIENTE
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {

    $nombre        = preg_replace('/\s+/', ' ', trim($_POST['nombre_completo'] ?? ''));
    $telefono      = trim($_POST['telefono'] ?? '');
    $email         = strtolower(trim($_POST['email'] ?? ''));
    $fechaNac      = $_POST['fecha_nacimiento'] ?? '';
    $genero        = trim($_POST['genero'] ?? 'no_especificado');
    $ocupacion     = trim($_POST['ocupacion'] ?? '');
    $direccion     = trim($_POST['direccion'] ?? '');
    $alergias      = trim($_POST['alergias'] ?? '');
    $padecimientos = trim($_POST['padecimientos'] ?? '');
    $medicamentos  = trim($_POST['medicamentos'] ?? '');
    $notas         = trim($_POST['notas'] ?? '');

    /* Validaciones */
    $err = validarPacienteNombre($nombre);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarTelefonoOpcional($telefono);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarEmailOpcional($email);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarFechaNacimientoOpcional($fechaNac);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarGeneroOpcional($genero);
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($ocupacion,     100, "La ocupación");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($direccion,     200, "La dirección");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($alergias,      500, "El campo de alergias");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($padecimientos, 500, "El campo de padecimientos");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($medicamentos,  500, "El campo de medicamentos");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    $err = validarLongitudOpcional($notas, 1000, "Las notas");
    if ($err) { echo json_encode(["ok"=>false,"mensaje"=>$err]); exit; }

    /* Conversión de strings vacíos a NULL para columnas opcionales */
    $telefonoV      = $telefono      === '' ? null : $telefono;
    $emailV         = $email         === '' ? null : $email;
    $fechaV         = $fechaNac      === '' ? null : $fechaNac;
    $ocupacionV     = $ocupacion     === '' ? null : $ocupacion;
    $direccionV     = $direccion     === '' ? null : $direccion;
    $alergiasV      = $alergias      === '' ? null : $alergias;
    $padecimientosV = $padecimientos === '' ? null : $padecimientos;
    $medicamentosV  = $medicamentos  === '' ? null : $medicamentos;
    $notasV         = $notas         === '' ? null : $notas;

    /* Insert con todos los campos · usa la columna real `notas_dentista` */
    $sql = "INSERT INTO pacientes
            (usuario_id, nombre_completo, telefono, email, fecha_nacimiento, genero,
             direccion, ocupacion, alergias, padecimientos, medicamentos, notas_dentista)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = @$conexion->prepare($sql);
    if (!$stmt) {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($conexion->errno, $conexion->error)]);
        $conexion->close(); exit;
    }

    // 1 int (usuario_id) + 11 strings (varchar/date/enum/text → 's')
    $stmt->bind_param("isssssssssss",
        $usuarioId,
        $nombre, $telefonoV, $emailV, $fechaV, $genero,
        $direccionV, $ocupacionV, $alergiasV, $padecimientosV, $medicamentosV,
        $notasV
    );

    if (@$stmt->execute()) {
        $nuevoId = $stmt->insert_id;
        $stmt->close(); $conexion->close();
        echo json_encode([
            "ok"       => true,
            "mensaje"  => "Paciente registrado.",
            "paciente" => [
                "paciente_id"     => $nuevoId,
                "nombre_completo" => $nombre,
                "telefono"        => $telefonoV,
                "email"           => $emailV
            ]
        ]);
        exit;
    } else {
        echo json_encode(["ok"=>false,"mensaje"=>mensajeErrorMysql($stmt->errno, $stmt->error)]);
        $stmt->close(); $conexion->close(); exit;
    }
}

/* Si llegó aquí, la combinación método+acción no es válida */
echo json_encode(["ok"=>false,"mensaje"=>"Operación no reconocida."]);
$conexion->close();
