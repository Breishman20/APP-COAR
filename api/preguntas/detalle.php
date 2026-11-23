<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "ID de pregunta inválido"
    ]);

    exit;
}

// Consultar pregunta
$sql = "SELECT
            id_pregunta,
            enunciado,
            imagen,
            dificultad
        FROM preguntas
        WHERE id_pregunta = ?
        AND estado = TRUE";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "mensaje" => "Pregunta no encontrada"
    ]);

    exit;
}

$pregunta = $resultado->fetch_assoc();

// Consultar alternativas
$sql2 = "SELECT id_alternativa, contenido
         FROM alternativas
         WHERE id_pregunta = ?
         ORDER BY id_alternativa";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $id);
$stmt2->execute();

$resultado2 = $stmt2->get_result();

$alternativas = [];

while ($fila = $resultado2->fetch_assoc()) {
    $alternativas[] = $fila;
}

$pregunta["alternativas"] = $alternativas;

echo json_encode([
    "success" => true,
    "data" => $pregunta
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$stmt2->close();
$conn->close();

?>