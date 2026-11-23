<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";

// Recibir filtros
$id_area = filter_input(INPUT_GET, "id_area", FILTER_VALIDATE_INT);
$id_tema = filter_input(INPUT_GET, "id_tema", FILTER_VALIDATE_INT);
$dificultad = $_GET["dificultad"] ?? "";

// Validar dificultad
$dificultades = ["facil", "media", "dificil"];

if ($dificultad !== "" &&
    !in_array($dificultad, $dificultades, true)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "Dificultad no válida"
    ]);

    exit;
}

// Construir consulta
$sql = "SELECT
            p.id_pregunta,
            p.enunciado,
            p.imagen,
            p.dificultad,
            a.id_alternativa,
            a.contenido

        FROM preguntas p

        INNER JOIN temas t
            ON p.id_tema = t.id_tema

        INNER JOIN alternativas a
            ON p.id_pregunta = a.id_pregunta

        WHERE p.estado = TRUE
        AND t.estado = TRUE";

$tipos = "";
$parametros = [];

// Filtro por área
if ($id_area !== null && $id_area !== false) {
    $sql .= " AND t.id_area = ?";
    $tipos .= "i";
    $parametros[] = $id_area;
}

// Filtro por tema
if ($id_tema !== null && $id_tema !== false) {
    $sql .= " AND p.id_tema = ?";
    $tipos .= "i";
    $parametros[] = $id_tema;
}

// Filtro por dificultad
if ($dificultad !== "") {
    $sql .= " AND p.dificultad = ?";
    $tipos .= "s";
    $parametros[] = $dificultad;
}

$sql .= " ORDER BY p.id_pregunta, a.id_alternativa";

$stmt = $conn->prepare($sql);

// Vincular parámetros
if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();

$resultado = $stmt->get_result();

$preguntas = [];

// Agrupar alternativas por pregunta
while ($fila = $resultado->fetch_assoc()) {

    $id = $fila["id_pregunta"];

    if (!isset($preguntas[$id])) {

        $preguntas[$id] = [
            "id_pregunta" => $id,
            "enunciado" => $fila["enunciado"],
            "imagen" => $fila["imagen"],
            "dificultad" => $fila["dificultad"],
            "alternativas" => []
        ];
    }

    $preguntas[$id]["alternativas"][] = [
        "id_alternativa" => $fila["id_alternativa"],
        "contenido" => $fila["contenido"]
    ];
}

// Convertir a índices numéricos
$preguntas = array_values($preguntas);

echo json_encode([
    "success" => true,
    "total" => count($preguntas),
    "data" => $preguntas
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();

?>