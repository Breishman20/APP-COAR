<?php
/**
 * ============================================================================
 * Endpoint: Cerrar sesión
 * ============================================================================
 * Propósito: Destruir la sesión actual del usuario.
 * Método: POST
 * URL: api/auth/logout.php
 * Cabecera: Authorization: Bearer <token>
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../middleware/autenticacion.php';

// Obtener token
$token = obtener_token_de_cabecera();

if ($token === null) {
    // Si no hay token, cerrar sesión de todos modos
    cerrar_sesion();
    echo json_encode([
        'success' => true,
        'mensaje' => 'Sesión cerrada'
    ]);
    exit;
}

// Validar y cerrar sesión
$sesion = validar_token_sesion($token);

if ($sesion !== null) {
    cerrar_sesion();
    echo json_encode([
        'success' => true,
        'mensaje' => 'Sesión cerrada exitosamente'
    ]);
} else {
    // Ya no hay sesión activa
    echo json_encode([
        'success' => true,
        'mensaje' => 'No había sesión activa'
    ]);
}

?>
