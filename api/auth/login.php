<?php
/**
 * ============================================================================
 * Endpoint: Inicio de sesión
 * ============================================================================
 * Propósito: Autenticar usuario con correo (o alias) y contraseña, crear sesión segura.
 * Método: POST
 * URL: api/auth/login.php
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

// Aceptar correo como identificador principal; alias como fallback para compatibilidad
$identificador = $datos["correo"] ?? $datos["aliasUsuario"] ?? "";
$clave = $datos["pswUsuario"] ?? "";

if ($identificador === "" || $clave === "") {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Complete todos los campos'
    ]);
    exit;
}

// Buscar usuario por correo o alias
$sql = "SELECT
            id_usuario,
            nombres,
            apellidos,
            correo,
            aliasUsuario,
            pswUsuario,
            rol,
            tipo_plan,
            estado,
            dni,
            sede_coar,
            cole_procedencia
        FROM usuarios
        WHERE correo = ? OR aliasUsuario = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $identificador, $identificador);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $usuario = $resultado->fetch_assoc();

    // Verificar que la cuenta esté activa
    if ($usuario['estado'] != 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Su cuenta está desactivada. Contacte al administrador.'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // Verificar contraseña
    $contrasenaValida = false;

    // Soportar password_hash()
    if (password_verify($clave, $usuario['pswUsuario'])) {
        $contrasenaValida = true;
    }
    // Compatibilidad temporal con texto plano (transición)
    // Se eliminará después de migrar todas las contraseñas
    elseif ($usuario['pswUsuario'] === $clave) {
        $contrasenaValida = true;

        // Migrar automáticamente a hash en el próximo login
        $nuevoHash = password_hash($clave, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE usuarios SET pswUsuario = ? WHERE id_usuario = ?");
        $updateStmt->bind_param("si", $nuevoHash, $usuario['id_usuario']);
        $updateStmt->execute();
        $updateStmt->close();
        $usuario['pswUsuario'] = $nuevoHash;
    }

    if (!$contrasenaValida) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Usuario o contraseña incorrectos'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // Verificar si el usuario está suspendido o deshabilitado
    if (function_exists('verificar_estado_cuenta')) {
        if (!verificar_estado_cuenta($conn, $usuario['id_usuario'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'mensaje' => 'Su cuenta está suspendida. Contacte al administrador.'
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
    }

    // Asignar valores por defecto si faltan
    $rol = $usuario['rol'] ?: 'estudiante';
    $tipoPlan = $usuario['tipo_plan'] ?: 'GRATIS';

    // Crear sesión
    $token = crear_sesion_usuario(
        $usuario['id_usuario'],
        $rol,
        $tipoPlan
    );

    // Preparar respuesta sin la contraseña
    unset($usuario['pswUsuario']);
    $usuario['rol'] = $rol;
    $usuario['tipo_plan'] = $tipoPlan;

    echo json_encode([
        'success' => true,
        'mensaje' => 'Inicio de sesión exitoso',
        'token' => $token,
        'usuario' => $usuario
    ], JSON_UNESCAPED_UNICODE);

} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Usuario o contraseña incorrectos'
    ]);
}

$stmt->close();
$conn->close();

?>
