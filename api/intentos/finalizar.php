<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$datos = json_decode(file_get_contents("php://input"), true);

$id_intento = $datos["id_intento"] ?? null;

if (!$id_intento) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => "Falta el ID del intento"
    ]);
    exit;
}

try {

    $conn->begin_transaction();

    // Bloquear el intento mientras se calcula el resultado.
    $stmt = $conn->prepare(
        "SELECT id_intento, estado
         FROM intentos
         WHERE id_intento = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $id_intento);
    $stmt->execute();

    $intento = $stmt->get_result()->fetch_assoc();

    if (!$intento || $intento["estado"] !== "en_proceso") {
        throw new Exception("El intento no está disponible para finalizar");
    }

    // Contar preguntas asignadas.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM intento_preguntas
         WHERE id_intento = ?"
    );

    $stmt->bind_param("i", $id_intento);
    $stmt->execute();

    $total = (int)$stmt->get_result()->fetch_assoc()["total"];

    // Contar respuestas correctas e incorrectas.
    $stmt = $conn->prepare(
        "SELECT
            COUNT(r.id_respuesta) AS respondidas,
            COALESCE(SUM(r.es_correcta = 1), 0) AS correctas,
            COALESCE(SUM(r.es_correcta = 0), 0) AS incorrectas
         FROM intento_preguntas ip
         LEFT JOIN respuestas r
            ON r.id_intento = ip.id_intento
            AND r.id_pregunta = ip.id_pregunta
         WHERE ip.id_intento = ?"
    );

    $stmt->bind_param("i", $id_intento);
    $stmt->execute();

    $datosResultado = $stmt->get_result()->fetch_assoc();

    $correctas = (int)$datosResultado["correctas"];
    $puntaje = $correctas;

    // Finalizar intento.
    $stmt = $conn->prepare(
        "UPDATE intentos
         SET estado = 'finalizado',
             fecha_fin = NOW(),
             puntaje = ?
         WHERE id_intento = ?"
    );

    $stmt->bind_param("ii", $puntaje, $id_intento);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "mensaje" => "Evaluación finalizada",
        "data" => [
            "total_preguntas" => $total,
            "respondidas" => (int)$datosResultado["respondidas"],
            "correctas" => $correctas,
            "incorrectas" => (int)$datosResultado["incorrectas"],
            "sin_responder" => $total - (int)$datosResultado["respondidas"],
            "puntaje" => $puntaje
        ]
    ]);

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}