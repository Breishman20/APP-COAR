<?php
/**
 * ============================================================================
 * Endpoint: Obtener progreso del estudiante
 * ============================================================================
 * Propósito: Obtener el progreso de actividades por tema y curso.
 * Método: GET
 * URL: api/progreso/obtener.php?id_area=X
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
$idArea = isset($_GET["id_area"]) ? intval($_GET["id_area"]) : 0;

// Obtener temas del área o todos
$where = $idArea > 0 ? "WHERE t.id_area = ?" : "";
$params = $idArea > 0 ? [$idArea] : [];

$sql = "SELECT t.id_tema, t.nombre, t.id_area, a.nombre AS nombre_area
        FROM temas t
        JOIN areas a ON t.id_area = a.id_area
        $where
        AND t.estado = 1
        ORDER BY a.nombre, t.nombre";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error en la consulta']);
    $conn->close();
    exit;
}

if ($idArea > 0) {
    $stmt->bind_param("i", $idArea);
}
$stmt->execute();
$resultado = $stmt->get_result();

$temas = [];
while ($tema = $resultado->fetch_assoc()) {
    $idTema = $tema['id_tema'];

    // Obtener progreso de cada tipo de actividad
    $progresoSql = "SELECT tipo, porcentaje, completado FROM progreso_actividad
                    WHERE id_usuario = ? AND id_tema = ?";
    $progStmt = $conn->prepare($progresoSql);
    $progStmt->bind_param("ii", $sesion['id_usuario'], $idTema);
    $progStmt->execute();
    $progResult = $progStmt->get_result();

    $actividades = [];
    while ($act = $progResult->fetch_assoc()) {
        $actividades[$act['tipo']] = [
            'porcentaje' => floatval($act['porcentaje']),
            'completado' => (bool)$act['completado']
        ];
    }
    $progStmt->close();

    // Calcular progreso del tema (promedio de actividades obligatorias)
    $totalPorcentaje = 0;
    $count = 0;
    foreach (['video', 'pdf', 'practica'] as $tipo) {
        if (isset($actividades[$tipo])) {
            $totalPorcentaje += $actividades[$tipo]['porcentaje'];
            $count++;
        }
    }
    $progresoTema = $count > 0 ? round($totalPorcentaje / $count, 2) : 0;

    $temas[] = [
        'id_tema' => intval($tema['id_tema']),
        'nombre' => $tema['nombre'],
        'id_area' => intval($tema['id_area']),
        'nombre_area' => $tema['nombre_area'],
        'progreso' => $progresoTema,
        'actividades' => $actividades
    ];
}
$stmt->close();

// Calcular progreso del área
$totalProgreso = 0;
foreach ($temas as $tema) {
    $totalProgreso += $tema['progreso'];
}
$progresoArea = count($temas) > 0 ? round($totalProgreso / count($temas), 2) : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'progreso_area' => $progresoArea,
        'temas' => $temas
    ]
]);

$conn->close();

?>
