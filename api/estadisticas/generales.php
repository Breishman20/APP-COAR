<?php
/**
 * ============================================================================
 * Endpoint: Estadísticas generales del estudiante
 * ============================================================================
 * Propósito: Calcular estadísticas reales desde intentos, respuestas y preguntas.
 * Método: GET
 * URL: api/estadisticas/generales.php
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
$idUsuario = $sesion['id_usuario'];

// Intentos finalizados
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
           SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
           SUM(CASE WHEN tipo = 'simulacro' THEN 1 ELSE 0 END) AS simulacros,
           SUM(CASE WHEN tipo = 'practica' THEN 1 ELSE 0 END) AS practicas
    FROM intentos WHERE id_usuario = ?
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$intentos = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Respuestas correctas e incorrectas
$stmt = $conn->prepare("
    SELECT
        COUNT(r.id_respuesta) AS total_respondidas,
        SUM(CASE WHEN r.es_correcta = 1 THEN 1 ELSE 0 END) AS correctas,
        SUM(CASE WHEN r.es_correcta = 0 THEN 1 ELSE 0 END) AS incorrectas
    FROM respuestas r
    JOIN intentos i ON r.id_intento = i.id_intento
    WHERE i.id_usuario = ? AND i.estado = 'finalizado'
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total = intval($respuestas['total_respondidas'] ?? 0);
$correctas = intval($respuestas['correctas'] ?? 0);
$incorrectas = intval($respuestas['incorrectas'] ?? 0);
$precision = $total > 0 ? round(($correctas / $total) * 100, 2) : 0;

// Estadísticas por área
$areas = [];
$stmt = $conn->prepare("
    SELECT a.id_area, a.nombre,
           COUNT(DISTINCT r.id_respuesta) AS total,
           SUM(CASE WHEN r.es_correcta = 1 THEN 1 ELSE 0 END) AS correctas
    FROM respuestas r
    JOIN intentos i ON r.id_intento = i.id_intento
    JOIN intento_preguntas ip ON r.id_intento = ip.id_intento AND r.id_pregunta = ip.id_pregunta
    JOIN preguntas p ON r.id_pregunta = p.id_pregunta
    JOIN temas t ON p.id_tema = t.id_tema
    JOIN areas a ON t.id_area = a.id_area
    WHERE i.id_usuario = ? AND i.estado = 'finalizado'
    GROUP BY a.id_area, a.nombre
    ORDER BY a.nombre
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();
while ($area = $resultado->fetch_assoc()) {
    $aTotal = intval($area['total']);
    $aCorrectas = intval($area['correctas']);
    $areas[] = [
        'id_area' => intval($area['id_area']),
        'nombre' => $area['nombre'],
        'total_respondidas' => $aTotal,
        'correctas' => $aCorrectas,
        'incorrectas' => $aTotal - $aCorrectas,
        'precision' => $aTotal > 0 ? round(($aCorrectas / $aTotal) * 100, 2) : 0
    ];
}
$stmt->close();

// Recomendaciones basadas en debilidades
$recomendaciones = [];
foreach ($areas as $area) {
    if ($area['precision'] < 60 && $area['total_respondidas'] >= 5) {
        $recomendaciones[] = [
            'area' => $area['nombre'],
            'precision' => $area['precision'],
            'sugerencia' => "Reforzar {$area['nombre']} - precisión por debajo del 60%"
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'intentos' => [
            'total' => intval($intentos['total'] ?? 0),
            'finalizados' => intval($intentos['finalizados'] ?? 0),
            'en_proceso' => intval($intentos['en_proceso'] ?? 0),
            'simulacros' => intval($intentos['simulacros'] ?? 0),
            'practicas' => intval($intentos['practicas'] ?? 0)
        ],
        'respuestas' => [
            'total' => $total,
            'correctas' => $correctas,
            'incorrectas' => $incorrectas,
            'precision' => $precision
        ],
        'por_area' => $areas,
        'recomendaciones' => $recomendaciones
    ]
]);

$conn->close();

?>
