<?php
/**
 * ============================================================================
 * Endpoint: Iniciar simulacro administrado
 * ============================================================================
 * Método: POST
 * Rol requerido: estudiante
 * Cuerpo JSON: {"id_simulacro": int}
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('estudiante');
    $idUsuario = $sesion['id_usuario'];
    $tipoPlan = $sesion['tipo_plan'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null || empty($input['id_simulacro'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'id_simulacro es requerido']);
        exit;
    }

    $idSimulacro = (int)$input['id_simulacro'];

    // Verificar que el simulacro existe y está activo
    $stmt = $conn->prepare("
        SELECT * FROM simulacros_administrados
        WHERE id_simulacro = ? AND estado = 'activo'
        AND (disponible_desde IS NULL OR disponible_desde <= NOW())
        AND (disponible_hasta IS NULL OR disponible_hasta >= NOW())
    ");
    $stmt->bind_param("i", $idSimulacro);
    $stmt->execute();
    $simulacro = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($simulacro === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'mensaje' => 'Simulacro no disponible']);
        exit;
    }

    // Verificar límite semanal para plan GRATIS
    if ($tipoPlan === 'GRATIS') {
        date_default_timezone_set('America/Lima');
        $inicioSemana = date('Y-m-d', strtotime('monday this week'));
        $finSemana = date('Y-m-d', strtotime('sunday this week'));

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total FROM intentos
            WHERE id_usuario = ?
            AND tipo = 'simulacro'
            AND fecha_inicio >= ?
            AND fecha_inicio <= ?
        ");
        $stmt->bind_param("iss", $idUsuario, $inicioSemana, $finSemana);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fila['total'] >= 1) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'mensaje' => 'Has alcanzado el límite semanal de simulacros gratuitos'
            ]);
            exit;
        }
    }

    // Verificar si ya tiene un intento en proceso para este simulacro
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM intentos
        WHERE id_usuario = ? AND id_simulacro = ? AND estado = 'en_proceso'
    ");
    $stmt->bind_param("ii", $idUsuario, $idSimulacro);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila['total'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Ya tienes un intento en proceso para este simulacro'
        ]);
        exit;
    }

    // Crear intento
    $conn->begin_transaction();
    try {
        $tipo = 'simulacro';
        $estado = 'en_proceso';
        $tiempoLimite = (int)$simulacro['duracion_minutos'];

        $stmt = $conn->prepare("
            INSERT INTO intentos (id_usuario, tipo, estado, id_simulacro, tiempo_limite, fecha_inicio)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isssi", $idUsuario, $tipo, $estado, $idSimulacro, $tiempoLimite);
        $stmt->execute();
        $idIntento = $conn->insert_id;
        $stmt->close();

        // Asignar preguntas del simulacro (versión congelada)
        $stmt = $conn->prepare("
            SELECT sp.* FROM simulacro_preguntas sp
            WHERE sp.id_simulacro = ?
            ORDER BY sp.numero_orden
        ");
        $stmt->bind_param("i", $idSimulacro);
        $stmt->execute();
        $preguntasResult = $stmt->get_result();

        $numero = 1;
        while ($sp = $preguntasResult->fetch_assoc()) {
            $idPregunta = (int)$sp['id_pregunta'];
            $stmt2 = $conn->prepare("
                INSERT INTO intento_preguntas (id_intento, id_pregunta, numero_pregunta)
                VALUES (?, ?, ?)
            ");
            $stmt2->bind_param("iii", $idIntento, $idPregunta, $numero);
            $stmt2->execute();
            $stmt2->close();
            $numero++;
        }
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Simulacro iniciado',
            'data' => [
                'id_intento' => $idIntento,
                'id_simulacro' => $idSimulacro,
                'duracion_minutos' => $tiempoLimite,
                'num_preguntas' => $numero - 1
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
        'mensaje' => 'Error al iniciar el simulacro'
    ]);
} finally {
    $conn->close();
}

?>
