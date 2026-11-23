<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$id_intento = filter_input(INPUT_GET, "id_intento", FILTER_VALIDATE_INT);

if (!$id_intento) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => "ID inválido"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        id_intento,
        tipo,
        estado,
        fecha_inicio,
        tiempo_limite,
        CASE
            WHEN tiempo_limite IS NULL THEN NULL
            ELSE GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    NOW(),
                    DATE_ADD(fecha_inicio, INTERVAL tiempo_limite MINUTE)
                )
            )
        END AS segundos_restantes
    FROM intentos
    WHERE id_intento = ?"
);

$stmt->bind_param("i", $id_intento);
$stmt->execute();

$intento = $stmt->get_result()->fetch_assoc();

if (!$intento) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "mensaje" => "Intento no encontrado"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $intento
]);