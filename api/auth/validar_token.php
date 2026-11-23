<?php
/**
 * ============================================================================
 * Endpoint: Validar token de sesión
 * ============================================================================
 * Propósito: Verificar si un token de sesión es válido y retornar datos del usuario.
 * Método: GET
 * URL: api/auth/validar_token.php
 * Cabecera: Authorization: Bearer <token>
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

// Autenticar solicitud
$sesion = autenticar_solicitud();

// Verificar que la cuenta sigue activa en la BD
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error de conexión al servidor']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id_usuario, nombres, apellidos, correo, aliasUsuario, rol, tipo_plan, estado
    FROM usuarios
    WHERE id_usuario = ?
");
$stmt->bind_param("i", $sesion['id_usuario']);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $usuario = $resultado->fetch_assoc();

    if ($usuario['estado'] != 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Su cuenta está desactivada'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Token válido',
        'usuario' => $usuario
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Usuario no encontrado'
    ]);
}

$stmt->close();
$conn->close();

?>
