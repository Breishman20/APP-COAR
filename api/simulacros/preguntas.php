<?php
/**
 * ============================================================================
 * Endpoint: Obtener preguntas de simulacro (versión congelada)
 * ============================================================================
 * Método: GET
 * Rol requerido: estudiante
 * Query: id_simulacro (requerido)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('estudiante');

    $idSimulacro = $_GET['id_simulacro'] ?? null;
    if ($idSimulacro === null || !is_numeric($idSimulacro)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'id_simulacro es requerido']);
        exit;
    }

    $idSimulacro = (int)$idSimulacro;

    // Obtener preguntas congeladas del simulacro
    $stmt = $conn->prepare("
        SELECT * FROM simulacro_preguntas
        WHERE id_simulacro = ?
        ORDER BY numero_orden
    ");
    $stmt->bind_param("i", $idSimulacro);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $preguntas = [];
    while ($fila = $resultado->fetch_assoc()) {
        // Decodificar alternativas snapshot
        $alternativas = json_decode($fila['alternativas_snapshot'] ?? '[]', true);
        $fila['alternativas'] = is_array($alternativas) ? $alternativas : [];
        $preguntas[] = $fila;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Preguntas obtenidas',
        'data' => $preguntas
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener las preguntas'
    ]);
} finally {
    $conn->close();
}

?>
