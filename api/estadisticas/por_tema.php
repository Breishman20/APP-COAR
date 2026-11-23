<?php
/**
 * ============================================================================
 * Endpoint: Estadísticas por tema
 * ============================================================================
 * Método: GET
 * Rol requerido: estudiante
 * Query: id_area (opcional)
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('estudiante');
    $idUsuario = $sesion['id_usuario'];

    $idArea = $_GET['id_area'] ?? null;

    $where = "1=1";
    $params = [];
    $types = '';

    if ($idArea !== null && is_numeric($idArea)) {
        $where .= " AND t.id_area = ?";
        $params[] = (int)$idArea;
        $types .= 'i';
    }

    $sql = "
        SELECT
            t.id_tema,
            t.nombre AS tema_nombre,
            a.id_area,
            a.nombre AS area_nombre,
            COUNT(DISTINCT r.id_respuesta) AS total_respondidas,
            SUM(CASE WHEN r.es_correcta = 1 THEN 1 ELSE 0 END) AS correctas,
            SUM(CASE WHEN r.es_correcta = 0 THEN 1 ELSE 0 END) AS incorrectas,
            ROUND(
                SUM(CASE WHEN r.es_correcta = 1 THEN 1 ELSE 0 END) * 100.0 /
                NULLIF(COUNT(DISTINCT r.id_respuesta), 0),
                1
            ) AS precision
        FROM temas t
        LEFT JOIN areas a ON t.id_area = a.id_area
        LEFT JOIN preguntas p ON p.id_tema = t.id_tema
        LEFT JOIN intento_preguntas ip ON ip.id_pregunta = p.id_pregunta
        LEFT JOIN intentos i ON i.id_intento = ip.id_intento AND i.id_usuario = ?
        LEFT JOIN respuestas r ON r.id_intento = i.id_intento AND r.id_pregunta = p.id_pregunta
        WHERE $where AND t.estado = 1
        GROUP BY t.id_tema, t.nombre, a.id_area, a.nombre
        HAVING total_respondidas > 0
        ORDER BY precision ASC NULLS LAST
    ";

    $params[] = $idUsuario;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $temas = [];
    while ($fila = $resultado->fetch_assoc()) {
        $fila['precision'] = (float)($fila['precision'] ?? 0);
        $fila['total_respondidas'] = (int)$fila['total_respondidas'];
        $fila['correctas'] = (int)$fila['correctas'];
        $fila['incorrectas'] = (int)$fila['incorrectas'];

        // Clasificar
        if ($fila['total_respondidas'] < 3) {
            $fila['clasificacion'] = 'insuficiente';
        } elseif ($fila['precision'] >= 70) {
            $fila['clasificacion'] = 'fuerte';
        } elseif ($fila['precision'] >= 40) {
            $fila['clasificacion'] = 'intermedio';
        } else {
            $fila['clasificacion'] = 'refuerzo';
        }

        $temas[] = $fila;
    }
    $stmt->close();

    // Recomendaciones basadas en debilidades
    $recomendaciones = [];
    foreach ($temas as $tema) {
        if ($tema['clasificacion'] === 'refuerzo') {
            $recomendaciones[] = [
                'tema' => $tema['tema_nombre'],
                'area' => $tema['area_nombre'],
                'precision' => $tema['precision'],
                'accion' => 'reforzar',
                'mensaje' => "Reforzar {$tema['tema_nombre']} ({$tema['precision']}% precisión)"
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Estadísticas por tema obtenidas',
        'data' => [
            'temas' => $temas,
            'recomendaciones' => $recomendaciones
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener las estadísticas'
    ]);
} finally {
    $conn->close();
}

?>
