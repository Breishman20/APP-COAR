
<?php

require_once __DIR__ . "/../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

// Valores que estamos probando
$id_area = 4;
$id_tema = 8;
$cantidad = 12;

// Consultar preguntas con la misma condición de iniciar.php
$sql = "
    SELECT
        p.id_pregunta,
        p.id_tema,
        t.id_area,
        p.estado
    FROM preguntas p
    INNER JOIN temas t ON p.id_tema = t.id_tema
    WHERE t.id_area = ?
      AND p.id_tema = ?
      AND p.estado = TRUE
    ORDER BY RAND()
    LIMIT ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $id_area, $id_tema, $cantidad);
$stmt->execute();

$resultado = $stmt->get_result();

$preguntas = [];

while ($fila = $resultado->fetch_assoc()) {
    $preguntas[] = $fila;
}

echo json_encode([
    "success" => true,
    "base_datos" => $conn->query("SELECT DATABASE()")->fetch_row()[0],
    "puerto" => $conn->query("SELECT @@port")->fetch_row()[0],
    "id_area_enviado" => $id_area,
    "id_tema_enviado" => $id_tema,
    "total_encontrado" => count($preguntas),
    "preguntas" => $preguntas,
    "base_datos" => $conn->query("SELECT DATABASE()")->fetch_row()[0],
    "puerto" => $conn->query("SELECT @@port")->fetch_row()[0],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>
