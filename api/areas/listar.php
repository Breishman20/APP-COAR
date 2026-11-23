<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";

// Consulta de áreas activas
$sql = "SELECT id_area, nombre, descripcion
        FROM areas
        WHERE estado = TRUE
        ORDER BY id_area";

$resultado = $conn->query($sql);

$areas = [];

while ($fila = $resultado->fetch_assoc()) {
    $areas[] = $fila;
}

// Respuesta JSON
echo json_encode([
    "success" => true,
    "total" => count($areas),
    "data" => $areas
], JSON_UNESCAPED_UNICODE);

$conn->close();

?>