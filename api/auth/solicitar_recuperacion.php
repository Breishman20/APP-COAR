<?php
/**
 * ============================================================================
 * Endpoint: Solicitar recuperación de contraseña
 * ============================================================================
 * Propósito: Generar token seguro y enviar correo de recuperación.
 * Método: POST
 * URL: api/auth/solicitar_recuperacion.php
 * Body: { "correo": "usuario@ejemplo.com" }
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

// Recibir datos JSON
$datos = json_decode(file_get_contents("php://input"), true);
$correo = trim($datos['correo'] ?? '');

if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ingrese un correo válido']);
    exit;
}

// Rate limiting: 1 solicitud por 15 minutos por correo
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM recuperacion_contrasena
    WHERE id_usuario = (
        SELECT id_usuario FROM usuarios WHERE correo = ?
    )
    AND usado = 0
    AND vence_en > NOW()
");
$stmt->bind_param("s", $correo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($fila['total'] > 0) {
    // Respuesta genérica para no revelar si el correo está registrado
    echo json_encode([
        'success' => true,
        'mensaje' => 'Si el correo está registrado, se ha enviado un enlace de recuperación.'
    ]);
    $conn->close();
    exit;
}

// Buscar usuario
$stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    // Respuesta genérica — no revelar si el correo existe
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'mensaje' => 'Si el correo está registrado, se ha enviado un enlace de recuperación.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$usuario = $resultado->fetch_assoc();
$idUsuario = $usuario['id_usuario'];
$stmt->close();

// Generar token seguro
$token = bin2hex(random_bytes(32));
// Usar hash SHA-256 para poder buscar el token en la BD
$tokenHash = hash('sha256', $token);

// Vencimiento: 30 minutos
$venceEn = date('Y-m-d H:i:s', strtotime('+30 minutes'));

// Guardar hash del token
$stmt = $conn->prepare("
    INSERT INTO recuperacion_contrasena (token_hash, id_usuario, creado_en, vence_en, usado)
    VALUES (?, ?, NOW(), ?, 0)
");
$stmt->bind_param("sis", $tokenHash, $idUsuario, $venceEn);
$stmt->execute();
$stmt->close();

// Enviar correo (si está configurado PHPMailer)
$smtpConfigPath = __DIR__ . '/../../../config_smtp.php';
$correoEnviado = false;

if (file_exists($smtpConfigPath)) {
    require_once $smtpConfigPath;

    // Intentar enviar con PHPMailer si está disponible
    $phpmailerPath = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';

        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtp_host ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username ?? '';
            $mail->Password = $smtp_password ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_port ?? 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($smtp_from_email ?? 'noreply@appcoar.com', 'APP COAR');
            $mail->addAddress($correo);

            // Enlace de recuperación
            $recuperacionUrl = ($smtp_base_url ?? 'http://localhost/appcoar') . '/recuperar.html?token=' . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de contraseña — APP COAR';
            $mail->Body = "
                <h2>Recuperación de contraseña</h2>
                <p>Has solicitado recuperar tu contraseña de APP COAR.</p>
                <p>Haz clic en el siguiente enlace para establecer una nueva contraseña:</p>
                <p><a href=\"$recuperacionUrl\">Recuperar contraseña</a></p>
                <p>Este enlace expirará en 30 minutos.</p>
                <p>Si no solicitaste esta recuperación, ignora este correo.</p>
            ";
            $mail->AltBody = "Recuperar contraseña: $recuperacionUrl";

            $mail->send();
            $correoEnviado = true;
        } catch (Exception $e) {
            // En desarrollo, si no se puede enviar, mostrar token en respuesta
            // En producción, registrar error en log
            error_log('Error enviando correo de recuperación: ' . $e->getMessage());
        }
    }
}

// Respuesta
if ($correoEnviado) {
    echo json_encode([
        'success' => true,
        'mensaje' => 'Se ha enviado un enlace de recuperación a tu correo.'
    ]);
} else {
    // En desarrollo sin SMTP configurado, retornar el token para pruebas
    // En producción, esto NO se debe hacer
    echo json_encode([
        'success' => true,
        'mensaje' => 'Si el correo está registrado, se ha enviado un enlace de recuperación.',
        'debug_token' => $token // SOLO EN DESARROLLO — eliminar en producción
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();

?>
