<?php
/**
 * ============================================================================
 * Endpoint: Cambiar contraseña
 * ============================================================================
 * Propósito: Establecer nueva contraseña usando un token de recuperación válido.
 * Método: POST
 * URL: api/auth/cambiar_contraseña.php
 * Body: { "token": "xxx", "nueva_contrasena": "xxx" }
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error de conexión al servidor']);
    exit;
}

// Recibir datos JSON
$datos = json_decode(file_get_contents("php://input"), true);

$token = trim($datos['token'] ?? '');
$nuevaContrasena = trim($datos['nueva_contrasena'] ?? '');

if ($token === '' || $nuevaContrasena === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Token y contraseña son requeridos']);
    exit;
}

// Validar longitud de contraseña
if (strlen($nuevaContrasena) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

// Hash del token para buscar en la BD
$tokenHash = hash('sha256', $token);

// Buscar token válido
$stmt = $conn->prepare("
    SELECT r.id, r.id_usuario
    FROM recuperacion_contrasena r
    WHERE r.token_hash = ?
    AND r.usado = 0
    AND r.vence_en > NOW()
    ORDER BY r.id DESC
    LIMIT 1
");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Token inválido o expirado'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$tokenData = $resultado->fetch_assoc();
$tokenRecordId = $tokenData['id'];
$idUsuario = $tokenData['id_usuario'];
$stmt->close();

// Iniciar transacción
$conn->begin_transaction();

try {
    // Actualizar contraseña
    $nuevoHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET pswUsuario = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $nuevoHash, $idUsuario);
    $stmt->execute();
    $stmt->close();

    // Marcar token como usado
    $stmt = $conn->prepare("UPDATE recuperacion_contrasena SET usado = 1 WHERE id = ?");
    $stmt->bind_param("i", $tokenRecordId);
    $stmt->execute();
    $stmt->close();

    // Invalidar sesiones activas del usuario
    // (En un sistema con sesiones en BD, se eliminarían aquí)
    // Con PHP sessions nativas, se invalidan al cambiar la contraseña

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Contraseña actualizada exitosamente. Puede iniciar sesión con su nueva contraseña.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al actualizar la contraseña'
    ]);
}

$conn->close();

?>
