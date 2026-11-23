<?php
/**
 * ============================================================================
 * Endpoint: Listar preguntas del profesor
 * ============================================================================
 * Método: GET
 * Rol requerido: profesor
 * Query: id_tema (opcional), estado (opcional)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('profesor');
    $idUsuario = $sesion['id_usuario'];

    $idTema = $_GET['id_tema'] ?? null;
    $estado = $_GET['estado'] ?? null;

    $where = "1=1";
    $params = [];
    $types = '';

    if ($idTema !== null && is_numeric($idTema)) {
        $where .= " AND p.id_tema = ?";
        $params[] = (int)$idTema;
        $types .= 'i';
    }

    if ($estado !== null && $estado !== '') {
        $where .= " AND p.estado = ?";
        $params[] = $estado;
        $types .= 's';
    }

    $sql = "
        SELECT p.*, t.nombre AS tema_nombre, a.nombre AS area_nombre,
               (SELECT COUNT(*) FROM alternativas alt WHERE alt.id_pregunta = p.id_pregunta) AS num_alternativas
        FROM preguntas p
        LEFT JOIN temas t ON p.id_tema = t.id_tema
        LEFT JOIN areas a ON t.id_area = a.id_area
        WHERE $where
        ORDER BY p.fecha_creacion DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $preguntas = [];
    while ($fila = $resultado->fetch_assoc()) {
        // Obtener alternativas
        $altStmt = $conn->prepare("SELECT * FROM alternativas WHERE id_pregunta = ? ORDER BY id_alternativa");
        $altStmt->bind_param("i", $fila['id_pregunta']);
        $altStmt->execute();
        $altResult = $altStmt->get_result();
        $alternativas = [];
        while ($alt = $altResult->fetch_assoc()) {
            $alternativas[] = $alt;
        }
        $altStmt->close();

        $fila['alternativas'] = $alternativas;
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
