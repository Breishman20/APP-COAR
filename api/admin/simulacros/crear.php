<?php
/**
 * ============================================================================
 * Endpoint: Crear simulacro administrado (Admin)
 * ============================================================================
 * Método: POST
 * Rol requerido: admin
 * Cuerpo JSON: {
 *   "nombre": string,
 *   "descripcion": string|null,
 *   "duracion_minutos": int,
 *   "disponible_desde": "YYYY-MM-DD HH:ii:ss"|null,
 *   "disponible_hasta": "YYYY-MM-DD HH:ii:ss"|null,
 *   "preguntas": [
 *     {"id_pregunta": int, "numero_orden": int}
 *   ]
 * }
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('admin');
    $idAdmin = $sesion['id_usuario'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'JSON inválido']);
        exit;
    }

    // Validaciones
    $errores = [];
    if (empty($input['nombre']) || !is_string($input['nombre'])) {
        $errores[] = 'nombre es requerido';
    }
    if (empty($input['duracion_minutos']) || !is_int($input['duracion_minutos']) || $input['duracion_minutos'] <= 0) {
        $errores[] = 'duracion_minutos debe ser un número positivo';
    }
    if (empty($input['preguntas']) || !is_array($input['preguntas']) || count($input['preguntas']) === 0) {
        $errores[] = 'Se requiere al menos una pregunta';
    }

    if (!empty($errores)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => implode('; ', $errores)]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Crear simulacro
        $nombre = $input['nombre'];
        $descripcion = $input['descripcion'] ?? null;
        $duracion = (int)$input['duracion_minutos'];
        $estado = 'activo';
        $disponibleDesde = $input['disponible_desde'] ?? null;
        $disponibleHasta = $input['disponible_hasta'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO simulacros_administrados (nombre, descripcion, duracion_minutos, estado, disponible_desde, disponible_hasta)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissi", $nombre, $descripcion, $duracion, $estado, $disponibleDesde, $disponibleHasta);
        $stmt->execute();
        $idSimulacro = $conn->insert_id;
        $stmt->close();

        // Crear versiones congeladas de las preguntas
        $stmtPregunta = $conn->prepare("
            INSERT INTO simulacro_preguntas (
                id_simulacro, id_pregunta, numero_orden,
                enunciado_snapshot, alternativas_snapshot,
                respuesta_correcta_snapshot, explicacion_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($input['preguntas'] as $pq) {
            $idPregunta = (int)$pq['id_pregunta'];
            $numeroOrden = (int)$pq['numero_orden'];

            // Obtener datos actuales de la pregunta
            $stmtData = $conn->prepare("
                SELECT enunciado, solucion FROM preguntas WHERE id_pregunta = ?
            ");
            $stmtData->bind_param("i", $idPregunta);
            $stmtData->execute();
            $pData = $stmtData->get_result()->fetch_assoc();
            $stmtData->close();

            if ($pData === null) {
                continue; // Saltar preguntas que no existen
            }

            // Obtener alternativas
            $altStmt = $conn->prepare("
                SELECT id_alternativa, contenido, es_correcta FROM alternativas WHERE id_pregunta = ? ORDER BY id_alternativa
            ");
            $altStmt->bind_param("i", $idPregunta);
            $altStmt->execute();
            $altResult = $altStmt->get_result();
            $alternativas = [];
            $respuestaCorrecta = null;
            while ($alt = $altResult->fetch_assoc()) {
                $alternativas[] = [
                    'id' => $alt['id_alternativa'],
                    'contenido' => $alt['contenido'],
                    'es_correcta' => (bool)$alt['es_correcta']
                ];
                if ($alt['es_correcta']) {
                    $respuestaCorrecta = $alt['id_alternativa'];
                }
            }
            $altStmt->close();

            $enunciado = $pData['enunciado'];
            $alternativasJson = json_encode($alternativas, JSON_UNESCAPED_UNICODE);
            $explicacion = $pData['solucion'] ?? null;

            $stmtPregunta->bind_param(
                "iisssss",
                $idSimulacro, $idPregunta, $numeroOrden,
                $enunciado, $alternativasJson, $respuestaCorrecta, $explicacion
            );
            $stmtPregunta->execute();
        }
        $stmtPregunta->close();

        $conn->commit();

        // Registrar en auditoría
        $idContenido = (string)$idSimulacro;
        $tipo = 'simulacro';
        $accion = 'crear';
        $observaciones = "Simulacro '{$nombre}' creado con " . count($input['preguntas']) . " preguntas";
        $stmt = $conn->prepare("
            INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, fecha, observaciones)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("ssiss", $idContenido, $tipo, $accion, $idAdmin, $observaciones);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Simulacro creado exitosamente',
            'data' => [
                'id_simulacro' => $idSimulacro,
                'num_preguntas' => count($input['preguntas'])
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al crear el simulacro'
    ]);
} finally {
    $conn->close();
}

?>
