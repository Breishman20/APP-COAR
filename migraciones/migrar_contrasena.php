<?php
/**
 * ============================================================================
 * Migración de contraseña: texto plano → password_hash()
 * ============================================================================
 * Propósito: Convertir la contraseña del usuario actual de texto plano a hash
 *            seguro sin perder el acceso a la cuenta.
 *
 * Uso: Ejecutar una sola vez desde el navegador o CLI:
 *   php migrar_contrasena.php
 *
 * IMPORTANTE: Eliminar este archivo después de ejecutarlo exitosamente.
 * ============================================================================
 */

// Desactivar visualización de errores en producción
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexion.php';

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

// Buscar usuarios con contraseña en texto plano
// Un hash de password_hash() comienza con $2y$, $2b$, $2a$, $argon2$, etc.
// Si no comienza con $, es texto plano.
$sql = "SELECT id_usuario, pswUsuario FROM usuarios WHERE pswUsuario NOT LIKE '$%' AND pswUsuario != ''";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al preparar la consulta: ' . $conn->error
    ]);
    exit;
}
$stmt->execute();
$resultado = $stmt->get_result();

$migrados = 0;
$errores = 0;
$ya_hash = 0;

if ($resultado->num_rows > 0) {
    while ($usuario = $resultado->fetch_assoc()) {
        $idUsuario = $usuario['id_usuario'];
        $contrasenaPlano = $usuario['pswUsuario'];

        // Generar hash seguro
        $hash = password_hash($contrasenaPlano, PASSWORD_DEFAULT);

        // Verificar que el hash funciona correctamente
        if (!password_verify($contrasenaPlano, $hash)) {
            $errores++;
            continue;
        }

        // Actualizar la contraseña en la base de datos
        $updateSql = "UPDATE usuarios SET pswUsuario = ? WHERE id_usuario = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $hash, $idUsuario);

        if ($updateStmt->execute()) {
            $migrados++;
        } else {
            $errores++;
        }
        $updateStmt->close();
    }
} else {
    $ya_hash = 1;
}

$stmt->close();
$conn->close();

// Respuesta
http_response_code(200);
header("Content-Type: application/json; charset=UTF-8");

if ($ya_hash) {
    echo json_encode([
        'success' => true,
        'mensaje' => 'No se encontraron contraseñas en texto plano. Todas ya están hasheadas.',
        'migrados' => 0,
        'errores' => 0
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => $errores === 0,
        'mensaje' => $errores === 0
            ? "Migración completada exitosamente."
            : "Migración completada con errores.",
        'migrados' => $migrados,
        'errores' => $errores
    ], JSON_UNESCAPED_UNICODE);
}

?>
