<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$datos = json_decode(file_get_contents("php://input"), true);

$id_intento = $datos["id_intento"] ?? null;
$id_pregunta = $datos["id_pregunta"] ?? null;
$id_alternativa = $datos["id_alternativa"] ?? null;

if (!$id_intento || !$id_pregunta || !$id_alternativa) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "Faltan datos"
    ]);

    exit;
}

try {

    // Verificar que el intento siga abierto.
    $stmt = $conn->prepare(
        "SELECT id_intento
         FROM intentos
         WHERE id_intento = ?
         AND estado = 'en_proceso'
         AND (
             tiempo_limite IS NULL
             OR DATE_ADD(fecha_inicio, INTERVAL tiempo_limite MINUTE) > NOW()
         )"
    );

    $stmt->bind_param("i", $id_intento);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("El intento no está disponible o venció");
    }

    // Verificar que la pregunta pertenezca al intento.
    $stmt = $conn->prepare(
        "SELECT id_pregunta
         FROM intento_preguntas
         WHERE id_intento = ?
         AND id_pregunta = ?"
    );

    $stmt->bind_param("ii", $id_intento, $id_pregunta);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("La pregunta no pertenece al intento");
    }

    // Verificar que la alternativa pertenezca a la pregunta.
    $stmt = $conn->prepare(
        "SELECT id_alternativa, es_correcta
         FROM alternativas
         WHERE id_alternativa = ?
         AND id_pregunta = ?"
    );

    $stmt->bind_param("ii", $id_alternativa, $id_pregunta);
    $stmt->execute();

    $alternativa = $stmt->get_result()->fetch_assoc();

    if (!$alternativa) {
        throw new Exception("La alternativa no pertenece a la pregunta");
    }

    // Insertar o actualizar la respuesta.
    $stmt = $conn->prepare(
        "INSERT INTO respuestas
        (id_intento, id_pregunta, id_alternativa, es_correcta)
        VALUES (?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE
        id_alternativa = VALUES(id_alternativa),
        es_correcta = VALUES(es_correcta),
        fecha_respuesta = CURRENT_TIMESTAMP"
    );

    $correcta = (int)$alternativa["es_correcta"];

    $stmt->bind_param(
        "iiii",
        $id_intento,
        $id_pregunta,
        $id_alternativa,
        $correcta
    );

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "mensaje" => "Respuesta guardada"
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}