<?php
/**
 * ============================================================================
 * Endpoint: Verificar token de recuperación
 * ============================================================================
 * Propósito: Validar que un token de recuperación es válido y no expirado.
 * Método: GET
 * URL: api/auth/verificar_token.php?token=xxx
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error de conexión al servidor']);
    exit;
}

// Recibir token
$token = trim($_GET['token'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Token requerido']);
    exit;
}

// Hash del token para buscar en la BD
$tokenHash = hash('sha256', $token);

// Buscar token válido
$stmt = $conn->prepare("
    SELECT r.id, r.id_usuario, r.vence_en, r.usado, u.correo
    FROM recuperacion_contrasena r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.token_hash = ?
    AND r.usado = 0
    AND r.vence_en > NOW()
    ORDER BY r.id DESC
    LIMIT 1
");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $fila = $resultado->fetch_assoc();
    echo json_encode([
        'success' => true,
        'mensaje' => 'Token válido',
        'correo' => $fila['correo']
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Token inválido o expirado'
    ]);
}

$stmt->close();
$conn->close();

?>
