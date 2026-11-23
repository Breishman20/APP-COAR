<?php
/**
 * ============================================================================
 * Endpoint: Reactivar cuenta (Admin)
 * ============================================================================
 * Método: POST
 * Rol requerido: admin
 * Cuerpo JSON: {"id_sancion": int}
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('admin');
    $idAdmin = $sesion['id_usuario'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null || empty($input['id_sancion'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'id_sancion es requerido']);
        exit;
    }

    $idSancion = (int)$input['id_sancion'];

    // Verificar que la sanción existe y está activa
    $stmt = $conn->prepare("SELECT * FROM sanciones_usuarios WHERE id_sancion = ? AND estado = 'activa'");
    $stmt->bind_param("i", $idSancion);
    $stmt->execute();
    $sancion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($sancion === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'mensaje' => 'Sanción no encontrada o ya inactiva']);
        exit;
    }

    // Marcar sanción como reactivada
    $fechaReactivacion = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE sanciones_usuarios
        SET estado = 'reactivada', fecha_reactivacion = ?
        WHERE id_sancion = ?
    ");
    $stmt->bind_param("si", $fechaReactivacion, $idSancion);
    $stmt->execute();
    $stmt->close();

    // Revocar sesiones del usuario sancionado
    $idUsuario = $sancion['id_usuario'];
    revocar_sesiones_usuario($idUsuario);

    // Registrar en auditoría
    $idContenido = (string)$idSancion;
    $tipo = 'sancion';
    $accion = 'reactivar';
    $observaciones = "Cuenta reactivada por administrador";
    $stmt = $conn->prepare("
        INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, fecha, observaciones)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param("ssiss", $idContenido, $tipo, $accion, $idAdmin, $observaciones);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Cuenta reactivada exitosamente',
        'data' => ['id_sancion' => $idSancion, 'id_usuario' => $idUsuario]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al reactivar la cuenta'
    ]);
} finally {
    $conn->close();
}

?>
