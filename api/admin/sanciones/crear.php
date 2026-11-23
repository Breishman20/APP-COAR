<?php
/**
 * ============================================================================
 * Endpoint: Crear sanción (Admin)
 * ============================================================================
 * Propósito: Suspender, deshabilitar o revocar permisos de una cuenta.
 * Método: POST
 * URL: api/admin/sanciones/crear.php
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error de conexión al servidor']);
    exit;
}

$sesion = autenticar_y_verificar_rol('admin');

$datos = json_decode(file_get_contents("php://input"), true);

$idUsuario = intval($datos["id_usuario"] ?? 0);
$tipoSancion = $datos["tipo_sancion"] ?? "";
$motivo = trim($datos["motivo"] ?? "");
$fechaVencimiento = $datos["fecha_vencimiento"] ?? null;

$tiposValidos = ['suspension', 'deshabilitacion', 'revocacion_permisos'];
if ($idUsuario <= 0 || !in_array($tipoSancion, $tiposValidos) || $motivo === "") {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos. Motivo obligatorio.']);
    exit;
}

// Verificar que el usuario existe
$stmt = $conn->prepare("SELECT id_usuario, estado FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();
if ($resultado->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'Usuario no encontrado']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Transacción
$conn->begin_transaction();
try {
    // Registrar sanción
    $stmt = $conn->prepare("
        INSERT INTO sanciones_usuarios (id_usuario, id_admin, tipo_sancion, motivo, fecha_vencimiento, estado)
        VALUES (?, ?, ?, ?, ?, 'activa')
    ");
    $stmt->bind_param("iisss", $idUsuario, $sesion['id_usuario'], $tipoSancion, $motivo, $fechaVencimiento);
    $stmt->execute();
    $idSancion = $stmt->insert_id;
    $stmt->close();

    // Si es suspensión o deshabilitación, actualizar estado del usuario
    if ($tipoSancion === 'suspension' || $tipoSancion === 'deshabilitacion') {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = 0 WHERE id_usuario = ?");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $stmt->close();

        // Revocar sesiones
        revocar_sesiones_usuario($idUsuario);
    }

    // Registrar en auditoría
    $stmt = $conn->prepare("
        INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, observaciones)
        VALUES (?, 'usuario', 'sancion', ?, ?)
    ");
    $observacion = "Sanción: {$tipoSancion} - {$motivo}";
    $stmt->bind_param("iis", $idUsuario, $sesion['id_usuario'], $observacion);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => "Sanción de {$tipoSancion} aplicada correctamente",
        'data' => ['id_sancion' => $idSancion]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al aplicar sanción']);
}

$conn->close();

?>
