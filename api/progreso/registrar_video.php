<?php
/**
 * ============================================================================
 * Endpoint: Registrar progreso de video
 * ============================================================================
 * Propósito: Registrar el tiempo y porcentaje de reproducción de un video.
 * Método: POST
 * URL: api/progreso/registrar_video.php
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

// Autenticar
$sesion = autenticar_solicitud();

// Recibir datos JSON
$datos = json_decode(file_get_contents("php://input"), true);

$idTema = intval($datos["id_tema"] ?? 0);
$porcentaje = floatval($datos["porcentaje"] ?? 0);
$tiempoReproducido = intval($datos["tiempo_reproducido"] ?? 0);
$duracionTotal = intval($datos["duracion_total"] ?? 0);

if ($idTema <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'ID de tema inválido']);
    exit;
}

// Calcular porcentaje si no se proporciona
if ($porcentaje <= 0 && $duracionTotal > 0) {
    $porcentaje = min(100, ($tiempoReproducido / $duracionTotal) * 100);
}

// Determinar si está completado (umbral 90%)
$completado = $porcentaje >= 90 ? 1 : 0;

// Insertar o actualizar progreso
$sql = "INSERT INTO progreso_actividad (id_usuario, id_tema, tipo, porcentaje, completado)
        VALUES (?, ?, 'video', ?, ?)
        ON DUPLICATE KEY UPDATE porcentaje = VALUES(porcentaje), completado = VALUES(completado), actualizado_en = CURRENT_TIMESTAMP";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al registrar progreso: ' . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("iiid", $sesion['id_usuario'], $idTema, $porcentaje, $completado);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'mensaje' => 'Progreso de video registrado',
        'data' => [
            'porcentaje' => round($porcentaje, 2),
            'completado' => (bool)$completado
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error al registrar progreso']);
}

$stmt->close();
$conn->close();

?>
