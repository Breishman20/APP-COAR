<?php
/**
 * ============================================================================
 * Endpoint: Registro de estudiante
 * ============================================================================
 * Propósito: Registrar un nuevo estudiante con validación completa.
 * Método: POST
 * URL: api/auth/registro.php
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

// Validar campos requeridos
$camposRequeridos = ['correo', 'pswUsuario', 'nombres', 'apellidos', 'dni', 'sede_coar', 'cole_procedencia'];
$faltantes = [];

foreach ($camposRequeridos as $campo) {
    if (empty($datos[$campo])) {
        $faltantes[] = $campo;
    }
}

if (!empty($faltantes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Campos requeridos: ' . implode(', ', $faltantes)
    ]);
    exit;
}

$correo = trim($datos['correo']);
$contrasena = $datos['pswUsuario'];
$nombres = trim($datos['nombres']);
$apellidos = trim($datos['apellidos']);
$dni = trim($datos['dni']);
$sedeCoar = trim($datos['sede_coar']);
$coleProcedencia = trim($datos['cole_procedencia']);

// Validar formato de correo
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Formato de correo inválido']);
    exit;
}

// Validar longitud de contraseña (mínimo 6 caracteres)
if (strlen($contrasena) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

// Validar DNI (8 dígitos numéricos para Perú)
if (!preg_match('/^\d{8}$/', $dni)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'El DNI debe tener 8 dígitos numéricos']);
    exit;
}

// Generar aliasUsuario a partir del correo (parte antes del @)
$aliasUsuario = explode('@', $correo)[0];

// Verificar duplicados
$duplicado = false;
$motivo = '';

// Verificar correo
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($fila['total'] > 0) {
    $duplicado = true;
    $motivo = 'El correo ya está registrado';
}

// Verificar DNI
if (!$duplicado) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila['total'] > 0) {
        $duplicado = true;
        $motivo = 'El DNI ya está registrado';
    }
}

// Verificar aliasUsuario
if (!$duplicado) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE aliasUsuario = ?");
    $stmt->bind_param("s", $aliasUsuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila['total'] > 0) {
        // Si el alias ya existe, agregar un sufijo numérico
        $baseAlias = $aliasUsuario;
        $contador = 1;
        do {
            $aliasUsuario = $baseAlias . $contador;
            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE aliasUsuario = ?");
            $stmt->bind_param("s", $aliasUsuario);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $contador++;
        } while ($fila['total'] > 0);
    }
}

if ($duplicado) {
    http_response_code(409);
    echo json_encode(['success' => false, 'mensaje' => $motivo]);
    exit;
}

// Hash de contraseña
$hash = password_hash($contrasena, PASSWORD_DEFAULT);

// Insertar usuario
$rol = 'estudiante';
$tipoPlan = 'GRATIS';
$estado = 1;

$stmt = $conn->prepare("
    INSERT INTO usuarios (
        nombres, apellidos, correo, aliasUsuario, pswUsuario,
        rol, tipo_plan, estado, dni, sede_coar, cole_procedencia
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssssisss",
    $nombres,
    $apellidos,
    $correo,
    $aliasUsuario,
    $hash,
    $rol,
    $tipoPlan,
    $estado,
    $dni,
    $sedeCoar,
    $coleProcedencia
);

if ($stmt->execute()) {
    $idUsuario = $stmt->insert_id;

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'mensaje' => 'Registro exitoso. Puede iniciar sesión.',
        'usuario' => [
            'id_usuario' => $idUsuario,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $correo,
            'rol' => $rol,
            'tipo_plan' => $tipoPlan
        ]
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al registrar el usuario'
    ]);
}

$stmt->close();
$conn->close();

?>
