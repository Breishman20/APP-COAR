<?php
/**
 * ============================================================================
 * Endpoint: Listar sanciones (Admin)
 * ============================================================================
 * Método: GET
 * Rol requerido: admin
 * Query: estado (opcional: activa/vencida/reactivada), id_usuario (opcional)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('admin');

    $estado = $_GET['estado'] ?? null;
    $idUsuario = $_GET['id_usuario'] ?? null;

    $where = "1=1";
    $params = [];
    $types = '';

    if ($estado !== null && $estado !== '') {
        $where .= " AND su.estado = ?";
        $params[] = $estado;
        $types .= 's';
    }

    if ($idUsuario !== null && is_numeric($idUsuario)) {
        $where .= " AND su.id_usuario = ?";
        $params[] = (int)$idUsuario;
        $types .= 'i';
    }

    $sql = "
        SELECT su.*,
               u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos,
               u.correo AS usuario_correo, u.rol AS usuario_rol,
               a.nombres AS admin_nombres, a.apellidos AS admin_apellidos
        FROM sanciones_usuarios su
        LEFT JOIN usuarios u ON su.id_usuario = u.id_usuario
        LEFT JOIN usuarios a ON su.id_admin = a.id_usuario
        WHERE $where
        ORDER BY su.fecha_inicio DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $sanciones = [];
    while ($fila = $resultado->fetch_assoc()) {
        $sanciones[] = $fila;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Sanciones obtenidas',
        'data' => $sanciones
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener las sanciones'
    ]);
} finally {
    $conn->close();
}

?>
