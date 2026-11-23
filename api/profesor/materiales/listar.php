<?php
/**
 * ============================================================================
 * Endpoint: Listar materiales del profesor
 * ============================================================================
 * Método: GET
 * Rol requerido: profesor
 * Query: id_tema (opcional), tipo (opcional: video/pdf), estado_aprobacion (opcional)
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
    $tipo = $_GET['tipo'] ?? null;
    $estadoAprobacion = $_GET['estado_aprobacion'] ?? null;

    $where = "tm.id_profesor = ?";
    $params = [$idUsuario];
    $types = 'i';

    if ($idTema !== null && is_numeric($idTema)) {
        $where .= " AND tm.id_tema = ?";
        $params[] = (int)$idTema;
        $types .= 'i';
    }

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
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $materiales = [];
    while ($fila = $resultado->fetch_assoc()) {
        $materiales[] = $fila;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Materiales obtenidos',
        'data' => $materiales
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener los materiales'
    ]);
} finally {
    $conn->close();
}

?>
