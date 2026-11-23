<?php
/**
 * ============================================================================
 * Endpoint: Registrar progreso de PDF
 * ============================================================================
 * Método: POST
 * Rol requerido: estudiante
 * Cuerpo JSON: {"id_tema": int, "completado": bool}
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('estudiante');
    $idUsuario = $sesion['id_usuario'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null || empty($input['id_tema'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'id_tema es requerido']);
        exit;
    }

    $idTema = (int)$input['id_tema'];
    $completado = !empty($input['completado']) ? 1 : 0;
    $porcentaje = $completado ? 100.0 : 50.0;
    $tipo = 'pdf';

    // Upsert progreso
    $stmt = $conn->prepare("
        SELECT id FROM progreso_actividad
        WHERE id_usuario = ? AND id_tema = ? AND tipo = ?
    ");
    $stmt->bind_param("iis", $idUsuario, $idTema, $tipo);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente !== null) {
        $stmt = $conn->prepare("
            UPDATE progreso_actividad
            SET porcentaje = ?, completado = ?, actualizado_en = NOW()
            WHERE id_usuario = ? AND id_tema = ? AND tipo = ?
        ");
        $stmt->bind_param("diisi", $porcentaje, $completado, $idUsuario, $idTema, $tipo);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO progreso_actividad (id_usuario, id_tema, tipo, porcentaje, completado, actualizado_en)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisdi", $idUsuario, $idTema, $tipo, $porcentaje, $completado);
    }
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Progreso de PDF registrado',
        'data' => ['id_tema' => $idTema, 'completado' => (bool)$completado]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al registrar el progreso']);
} finally {
    $conn->close();
}

?>
