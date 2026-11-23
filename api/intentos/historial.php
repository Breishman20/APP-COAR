<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$id_usuario = filter_input(INPUT_GET, "id_usuario", FILTER_VALIDATE_INT);

if (!$id_usuario) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => "ID de usuario inválido"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        id_intento,
        tipo,
        estado,
        fecha_inicio,
        fecha_fin,
        puntaje
     FROM intentos
     WHERE id_usuario = ?
     AND estado = 'finalizado'
     ORDER BY fecha_inicio DESC"
);

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result();

$historial = [];

while ($fila = $resultado->fetch_assoc()) {
    $historial[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($historial),
    "data" => $historial
]);