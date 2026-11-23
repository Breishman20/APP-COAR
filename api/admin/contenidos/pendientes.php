<?php
/**
 * ============================================================================
 * Endpoint: Listar contenidos pendientes de aprobación (Admin)
 * ============================================================================
 * Método: GET
 * Rol requerido: admin
 * Query: tipo (opcional: pregunta/video/pdf), estado_aprobacion (opcional)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('admin');

    $tipo = $_GET['tipo'] ?? null;
    $estadoAprobacion = $_GET['estado_aprobacion'] ?? 'pendiente';

    // Materiales pendientes
    $where = "1=1";
    $params = [];
    $types = '';

    if ($tipo !== null && in_array($tipo, ['video', 'pdf'], true)) {
        $where .= " AND tm.tipo = ?";
        $params[] = $tipo;
        $types .= 's';
    }

    if ($estadoAprobacion !== null && $estadoAprobacion !== '') {
        $where .= " AND tm.estado_aprobacion = ?";
        $params[] = $estadoAprobacion;
        $types .= 's';
    }

    $sql = "
        SELECT tm.*, t.nombre AS tema_nombre, a.nombre AS area_nombre,
               u.nombres AS profesor_nombres, u.apellidos AS profesor_apellidos
        FROM tema_materiales tm
        LEFT JOIN temas t ON tm.id_tema = t.id_tema
        LEFT JOIN areas a ON t.id_area = a.id_area
        LEFT JOIN usuarios u ON tm.id_profesor = u.id_usuario
        WHERE $where
        ORDER BY tm.fecha_registro DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $materiales = [];
    while ($fila = $resultado->fetch_assoc()) {
        $materiales[] = $fila;
    }
    $stmt->close();

    // Preguntas sin aprobación (todas las preguntas activas)
    $preguntasSql = "
        SELECT p.*, t.nombre AS tema_nombre, a.nombre AS area_nombre
        FROM preguntas p
        LEFT JOIN temas t ON p.id_tema = t.id_tema
        LEFT JOIN areas a ON t.id_area = a.id_area
        WHERE p.estado = 1
        ORDER BY p.fecha_creacion DESC
        LIMIT 50
    ";
    $preguntasResult = $conn->query($preguntasSql);
    $preguntas = [];
    while ($fila = $preguntasResult->fetch_assoc()) {
        $preguntas[] = $fila;
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Contenidos obtenidos',
        'data' => [
            'materiales' => $materiales,
            'preguntas' => $preguntas
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener los contenidos'
    ]);
} finally {
    $conn->close();
}

?>
