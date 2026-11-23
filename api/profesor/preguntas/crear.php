<?php
/**
 * ============================================================================
 * Endpoint: Crear pregunta (Profesor)
 * ============================================================================
 * Método: POST
 * Rol requerido: profesor
 * Cuerpo JSON: {
 *   "id_tema": int,
 *   "enunciado": string,
 *   "dificultad": "facil"|"media"|"dificil",
 *   "imagen": string|null,
 *   "solucion": string|null,
 *   "imagen_solucion": string|null,
 *   "es_premium": bool,
 *   "alternativas": [
 *     {"contenido": string, "es_correcta": bool}
 *   ]
 * }
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('profesor');
    $idUsuario = $sesion['id_usuario'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'JSON inválido']);
        exit;
    }

    // Validaciones
    $errores = [];
    if (empty($input['id_tema']) || !is_int($input['id_tema'])) {
        $errores[] = 'id_tema es requerido y debe ser un número';
    }
    if (empty($input['enunciado']) || !is_string($input['enunciado'])) {
        $errores[] = 'enunciado es requerido';
    }
    if (empty($input['dificultad']) || !in_array($input['dificultad'], ['facil', 'media', 'dificil'], true)) {
        $errores[] = 'dificultad debe ser facil, media o dificil';
    }
    if (empty($input['alternativas']) || !is_array($input['alternativas']) || count($input['alternativas']) < 2) {
        $errores[] = 'Se requieren al menos 2 alternativas';
    }

    // Verificar que haya exactamente una alternativa correcta
    $correctas = 0;
    foreach ($input['alternativas'] as $alt) {
        if (!empty($alt['contenido']) && is_string($alt['contenido'])) {
            if (!empty($alt['es_correcta'])) $correctas++;
        }
    }
    if ($correctas !== 1) {
        $errores[] = 'Debe haber exactamente una alternativa correcta';
    }

    if (!empty($errores)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => implode('; ', $errores)]);
        exit;
    }

    // Verificar que el tema existe
    $stmt = $conn->prepare("SELECT id_tema FROM temas WHERE id_tema = ? AND estado = 1");
    $stmt->bind_param("i", $input['id_tema']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'El tema no existe o está desactivado']);
        exit;
    }
    $stmt->close();

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Crear pregunta
        $enunciado = $input['enunciado'];
        $dificultad = $input['dificultad'];
        $imagen = $input['imagen'] ?? null;
        $solucion = $input['solucion'] ?? null;
        $imagen_solucion = $input['imagen_solucion'] ?? null;
        $es_premium = !empty($input['es_premium']) ? 1 : 0;
        $id_tema = $input['id_tema'];

        $stmt = $conn->prepare("
            INSERT INTO preguntas (id_tema, enunciado, imagen, dificultad, solucion, imagen_solucion, es_premium, estado, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->bind_param("isssssi", $id_tema, $enunciado, $imagen, $dificultad, $solucion, $imagen_solucion, $es_premium);
        $stmt->execute();
        $idPregunta = $conn->insert_id;
        $stmt->close();

        // Crear alternativas
        $stmt = $conn->prepare("
            INSERT INTO alternativas (id_pregunta, contenido, es_correcta)
            VALUES (?, ?, ?)
        ");
        foreach ($input['alternativas'] as $alt) {
            $contenido = $alt['contenido'];
            $es_correcta = !empty($alt['es_correcta']) ? 1 : 0;
            $stmt->bind_param("isi", $idPregunta, $contenido, $es_correcta);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();

        // Registrar en auditoría
        $realizadoPor = $idUsuario;
        $idContenido = (string)$idPregunta;
        $tipo = 'pregunta';
        $accion = 'crear';
        $observaciones = 'Pregunta creada por profesor';
        $stmt = $conn->prepare("
            INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, fecha, observaciones)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("ssiss", $idContenido, $tipo, $accion, $realizadoPor, $observaciones);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Pregunta creada exitosamente',
            'data' => ['id_pregunta' => $idPregunta, 'estado' => 'borrador']
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al crear la pregunta'
    ]);
} finally {
    $conn->close();
}

?>
