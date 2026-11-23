<?php
/**
 * ============================================================================
 * Middleware de autenticación y permisos
 * ============================================================================
 * Propósito: Validar tokens de sesión y verificar roles de usuario.
 * Uso: Incluir al inicio de cada endpoint protegido.
 * ============================================================================
 */

/**
 * Iniciar sesión si no está activa.
 * Usa PHP sessions nativas para gestionar tokens.
 */
function iniciar_sesion_si_es_necesario() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Generar un token de sesión seguro.
 * @return string Token aleatorio de 32 bytes (64 caracteres hex).
 */
function generar_token_sesion() {
    return bin2hex(random_bytes(32));
}

/**
 * Autenticar usuario y crear sesión.
 * @param int $idUsuario ID del usuario autenticado.
 * @param string $rol Rol del usuario.
 * @param string $tipoPlan Plan del usuario.
 * @return string Token de sesión.
 */
function crear_sesion_usuario($idUsuario, $rol, $tipoPlan) {
    iniciar_sesion_si_es_necesario();

    $token = generar_token_sesion();

    $_SESSION['id_usuario'] = $idUsuario;
    $_SESSION['rol'] = $rol;
    $_SESSION['tipo_plan'] = $tipoPlan;
    $_SESSION['token'] = $token;
    $_SESSION['inicio_sesion'] = time();

    return $token;
}

/**
 * Validar token de sesión.
 * @param string $token Token recibido en la cabecera Authorization.
 * @return array|null Datos de la sesión o null si es inválido.
 */
function validar_token_sesion($token) {
    iniciar_sesion_si_es_necesario();

    // Verificar que la sesión existe y el token coincide
    if (
        isset($_SESSION['token']) &&
        isset($_SESSION['id_usuario']) &&
        isset($_SESSION['rol']) &&
        $_SESSION['token'] === $token
    ) {
        return [
            'id_usuario' => $_SESSION['id_usuario'],
            'rol' => $_SESSION['rol'],
            'tipo_plan' => $_SESSION['tipo_plan'] ?? 'GRATIS'
        ];
    }

    return null;
}

/**
 * Obtener token de la cabecera Authorization.
 * Formato: "Bearer <token>"
 * @return string|null Token o null.
 */
function obtener_token_de_cabecera() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
        return substr($authHeader, 7);
    }

    return null;
}

/**
 * Autenticar solicitud entrante.
 * Valida el token y retorna los datos del usuario.
 * Si falla, responde con HTTP 401 y termina la ejecución.
 * @return array Datos del usuario autenticado.
 */
function autenticar_solicitud() {
    $token = obtener_token_de_cabecera();

    if ($token === null) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'mensaje' => 'No se proporcionó token de autenticación'
        ]);
        exit;
    }

    $sesion = validar_token_sesion($token);

    if ($sesion === null) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Token inválido o expirado'
        ]);
        exit;
    }

    return $sesion;
}

/**
 * Verificar que el usuario tiene el rol requerido.
 * @param array $sesion Datos de la sesión autenticada.
 * @param string|array $rolesPermitidos Rol o array de roles permitidos.
 * Si falla, responde con HTTP 403 y termina la ejecución.
 */
function verificar_rol($sesion, $rolesPermitidos) {
    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    if (!in_array($sesion['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'mensaje' => 'No tiene permisos para realizar esta acción'
        ]);
        exit;
    }
}

/**
 * Autenticar y verificar rol en una sola llamada.
 * @param string|array $rolesPermitidos Rol o array de roles permitidos.
 * @return array Datos del usuario autenticado con rol verificado.
 */
function autenticar_y_verificar_rol($rolesPermitidos) {
    $sesion = autenticar_solicitud();
    verificar_rol($sesion, $rolesPermitidos);
    return $sesion;
}

/**
 * Revocar todas las sesiones de un usuario.
 * @param int $idUsuario ID del usuario cuyas sesiones se revocan.
 */
function revocar_sesiones_usuario($idUsuario) {
    iniciar_sesion_si_es_necesario();

    if (isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] == $idUsuario) {
        session_destroy();
        session_start();
    }
}

/**
 * Cerrar sesión actual.
 */
function cerrar_sesion() {
    iniciar_sesion_si_es_necesario();
    session_unset();
    session_destroy();
}

/**
 * Verificar si un usuario está suspendido o deshabilitado.
 * @param mysqli $conn Conexión a la base de datos.
 * @param int $idUsuario ID del usuario.
 * @return bool True si el usuario puede acceder, false si está bloqueado.
 */
function verificar_estado_cuenta($conn, $idUsuario) {
    // Verificar estado básico
    $stmt = $conn->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        if ($usuario['estado'] != 1) {
            $stmt->close();
            return false;
        }
    }
    $stmt->close();

    // Verificar si tiene una sanción activa de suspensión o deshabilitación
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM sanciones_usuarios
        WHERE id_usuario = ?
        AND estado = 'activa'
        AND tipo_sancion IN ('suspension','deshabilitacion')
        AND (fecha_vencimiento IS NULL OR fecha_vencimiento > NOW())
    ");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila['total'] == 0;
}

?>
