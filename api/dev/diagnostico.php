<?php

require_once __DIR__ . "/../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

// Comprobar qué base de datos y puerto está utilizando PHP.
$conexion = $conn->query("
    SELECT DATABASE() AS base_datos, @@port AS puerto
");

$info = $conexion->fetch_assoc();

// Contar preguntas activas.
$conteo = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 1) AS activas
    FROM preguntas
");

$preguntas = $conteo->fetch_assoc();

// Contar preguntas activas por área y tema.
$consulta = $conn->query("
    SELECT
        t.id_area,
        t.id_tema,
        t.nombre AS tema,
        COUNT(p.id_pregunta) AS total
    FROM temas t
    LEFT JOIN preguntas p
        ON p.id_tema = t.id_tema
        AND p.estado = 1
    GROUP BY t.id_area, t.id_tema, t.nombre
    ORDER BY t.id_area, t.id_tema
");

$porTema = [];

while ($fila = $consulta->fetch_assoc()) {
    $porTema[] = $fila;
}

echo json_encode([
    "success" => true,
    "conexion" => $info,
    "preguntas" => $preguntas,
    "por_tema" => $porTema
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>
