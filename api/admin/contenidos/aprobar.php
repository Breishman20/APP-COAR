<?php
/**
 * ============================================================================
 * Endpoint: Aprobar/Rechazar contenido (Admin)
 * ============================================================================
 * Propósito: El administrador aprueba o rechaza contenido de profesores.
 * Método: POST
 * URL: api/admin/contenidos/aprobar.php
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

$idContenido = intval($datos["id_contenido"] ?? 0);
$accion = $datos["accion"] ?? ""; // 'aprobar' o 'rechazar'
$observaciones = trim($datos["observaciones"] ?? "");

if ($idContenido <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

$estadoAprobacion = $accion === 'aprobar' ? 'aprobado' : 'rechazado';

$conn->begin_transaction();
try {
    // Actualizar estado del material en tema_materiales
    $stmt = $conn->prepare("
        UPDATE tema_materiales
        SET estado_aprobacion = ?, observaciones = ?
        WHERE id_material = ?
    ");
    $stmt->bind_param("ssi", $estadoAprobacion, $observaciones, $idContenido);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'mensaje' => 'Material no encontrado']);
        $stmt->close();
        $conn->rollback();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Registrar en auditoría
    $tipoAccion = $accion === 'aprobar' ? 'aprobacion' : 'rechazo';
    $stmt = $conn->prepare("
        INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, observaciones)
        VALUES (?, 'material', ?, ?, ?)
    ");
    $tipo = 'video'; // Se podría determinar desde la BD
    $stmt->bind_param("isss", $idContenido, $tipoAccion, $sesion['id_usuario'], $observaciones);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => "Contenido {$estadoAprobacion} correctamente",
        'data' => ['estado' => $estadoAprobacion]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al procesar contenido']);
}

$conn->close();

?>
