<?php
/**
 * ============================================================================
 * Endpoint: Verificar límite semanal de simulacros gratuitos
 * ============================================================================
 * Propósito: Verificar si un estudiante gratuito puede iniciar un simulacro.
 * Método: GET
 * URL: api/plan/verificar_limite_simulacro.php
 * ============================================================================
 */

header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error de conexión al servidor']);
    exit;
}

$sesion = autenticar_solicitud();

// Solo aplica a estudiantes gratuitos
if ($sesion['tipo_plan'] === 'PREMIUM') {
    echo json_encode([
        'success' => true,
        'puede_iniciar' => true,
        'mensaje' => 'Plan PREMIUM - sin límite de simulacros'
    ]);
    $conn->close();
    exit;
}

// Zona horaria de Perú
date_default_timezone_set('America/Lima');

// Obtener el lunes de la semana actual
$inicioSemana = date('Y-m-d 00:00:00', strtotime('monday this week'));
$finSemana = date('Y-m-d 23:59:59', strtotime('sunday this week'));

// Contar simulacros iniciados esta semana
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM intentos
    WHERE id_usuario = ?
    AND tipo = 'simulacro'
    AND fecha_inicio >= ?
    AND fecha_inicio <= ?
");
$stmt->bind_param("iss", $sesion['id_usuario'], $inicioSemana, $finSemana);
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalSemana = intval($resultado['total']);
$puedeIniciar = $totalSemana < 1;

echo json_encode([
    'success' => true,
    'puede_iniciar' => $puedeIniciar,
    'mensaje' => $puedeIniciar ? 'Puede iniciar simulacro' : 'Límite semanal alcanzado (1 simulacro/semana)',
    'data' => [
        'simulacros_usados' => $totalSemana,
        'limite_semanal' => 1,
        'inicio_semana' => $inicioSemana,
        'fin_semana' => $finSemana
    ]
]);

$conn->close();

?>
