<?php
/**
 * ============================================================================
 * Endpoint: Listar simulacros disponibles
 * ============================================================================
 * Método: GET
 * Rol requerido: estudiante, profesor, admin
 * Query: estado (opcional: activo/inactivo)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol(['estudiante', 'profesor', 'admin']);

    $estado = $_GET['estado'] ?? null;

    $where = "1=1";
    $params = [];
    $types = '';

    if ($estado !== null && $estado !== '') {
        $where .= " AND sa.estado = ?";
        $params[] = $estado;
        $types .= 's';
    }

    // Solo simulacros disponibles en el rango de fechas
    $where .= " AND (sa.disponible_desde IS NULL OR sa.disponible_desde <= NOW())";
    $where .= " AND (sa.disponible_hasta IS NULL OR sa.disponible_hasta >= NOW())";

    $sql = "
        SELECT sa.*,
               (SELECT COUNT(*) FROM simulacro_preguntas sp WHERE sp.id_simulacro = sa.id_simulacro) AS num_preguntas
        FROM simulacros_administrados sa
        WHERE $where
        ORDER BY sa.disponible_desde DESC, sa.nombre
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $simulacros = [];
    while ($fila = $resultado->fetch_assoc()) {
        $simulacros[] = $fila;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Simulacros obtenidos',
        'data' => $simulacros
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener los simulacros'
    ]);
} finally {
    $conn->close();
}

?>
