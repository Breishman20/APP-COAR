<?php
/**
 * ============================================================================
 * Endpoint: Cambiar plan de usuario (Admin)
 * ============================================================================
 * Propósito: El administrador cambia el plan de un estudiante.
 * Método: POST
 * URL: api/admin/usuarios/cambiar_plan.php
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
$planNuevo = $datos["plan_nuevo"] ?? "";

if ($idUsuario <= 0 || !in_array($planNuevo, ['GRATIS', 'PREMIUM'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

// Obtener plan actual
$stmt = $conn->prepare("SELECT tipo_plan FROM usuarios WHERE id_usuario = ?");
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
$usuario = $resultado->fetch_assoc();
$planAnterior = $usuario['tipo_plan'];
$stmt->close();

// Transacción
$conn->begin_transaction();
try {
    // Actualizar plan
    $stmt = $conn->prepare("UPDATE usuarios SET tipo_plan = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $planNuevo, $idUsuario);
    $stmt->execute();
    $stmt->close();

    // Registrar en auditoría
    $stmt = $conn->prepare("
        INSERT INTO auditoria_plan (id_usuario, plan_anterior, plan_nuevo, cambiado_por, observaciones)
        VALUES (?, ?, ?, ?, 'Cambio manual desde panel admin')
    ");
    $stmt->bind_param("isii", $idUsuario, $planAnterior, $planNuevo, $sesion['id_usuario']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => "Plan cambiado de {$planAnterior} a {$planNuevo}",
        'data' => ['plan_anterior' => $planAnterior, 'plan_nuevo' => $planNuevo]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al cambiar plan']);
}

$conn->close();

?>
