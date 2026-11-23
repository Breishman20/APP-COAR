<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";

// Recibir ID del área
$id_area = filter_input(INPUT_GET, "id_area", FILTER_VALIDATE_INT);

if (!$id_area || $id_area <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "Debe proporcionar un ID de área válido"
    ]);

    exit;
}

// Consultar temas
$sql = "SELECT id_tema, nombre, descripcion
        FROM temas
        WHERE id_area = ? AND estado = TRUE
        ORDER BY nombre";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_area);
$stmt->execute();

$resultado = $stmt->get_result();

$temas = [];

while ($fila = $resultado->fetch_assoc()) {
    $temas[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($temas),
    "data" => $temas
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();

?>