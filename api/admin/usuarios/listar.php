<?php
/**
 * ============================================================================
 * Endpoint: Listar usuarios (Admin)
 * ============================================================================
 * Propósito: Consultar estudiantes y profesores.
 * Método: GET
 * URL: api/admin/usuarios/listar.php?rol=estudiante&estado=activo
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

$rol = $_GET["rol"] ?? "";
$estado = $_GET["estado"] ?? "";

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($rol !== "") {
    $where .= " AND u.rol = ?";
    $params[] = $rol;
    $types .= "s";
}
if ($estado === "activo") {
    $where .= " AND u.estado = 1";
} elseif ($estado === "inactivo") {
    $where .= " AND u.estado = 0";
}

$sql = "SELECT u.id_usuario, u.nombres, u.apellidos, u.correo, u.aliasUsuario,
               u.rol, u.tipo_plan, u.estado, u.fecha_registro,
               (SELECT COUNT(*) FROM sanciones_usuarios WHERE id_usuario = u.id_usuario AND estado = 'activa') AS sanciones_activas
        FROM usuarios u
        $where
        ORDER BY u.fecha_registro DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

$usuarios = [];
while ($u = $resultado->fetch_assoc()) {
    $usuarios[] = [
        'id_usuario' => intval($u['id_usuario']),
        'nombres' => $u['nombres'],
        'apellidos' => $u['apellidos'],
        'correo' => $u['correo'],
        'aliasUsuario' => $u['aliasUsuario'],
        'rol' => $u['rol'],
        'tipo_plan' => $u['tipo_plan'],
        'estado' => intval($u['estado']),
        'fecha_registro' => $u['fecha_registro'],
        'sanciones_activas' => intval($u['sanciones_activas'])
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $usuarios
]);

$conn->close();

?>
