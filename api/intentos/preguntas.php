<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$id_intento = filter_input(INPUT_GET, "id_intento", FILTER_VALIDATE_INT);

if (!$id_intento) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "ID de intento inválido"
    ]);

    exit;
}

$stmt = $conn->prepare(
    "SELECT
        ip.numero_pregunta,
        p.id_pregunta,
        p.enunciado,
        p.imagen,
        p.dificultad,
        p.solucion,
        p.imagen_solucion AS url,
        (SELECT a2.id_alternativa FROM alternativas a2 WHERE a2.id_pregunta = p.id_pregunta AND a2.es_correcta = 1 LIMIT 1) AS id_alternativa_correcta,
        a.id_alternativa,
        a.contenido,
        a.es_correcta,
        r.id_alternativa AS alternativa_seleccionada

    FROM intento_preguntas ip

    INNER JOIN preguntas p
        ON ip.id_pregunta = p.id_pregunta

    INNER JOIN alternativas a
        ON p.id_pregunta = a.id_pregunta

    LEFT JOIN respuestas r
        ON r.id_pregunta = p.id_pregunta
        AND r.id_intento = ip.id_intento

    INNER JOIN intentos i
        ON i.id_intento = ip.id_intento

    WHERE ip.id_intento = ?
    AND i.estado = 'en_proceso'

    ORDER BY ip.numero_pregunta, a.id_alternativa"
);

$stmt->bind_param("i", $id_intento);
$stmt->execute();

$resultado = $stmt->get_result();

$preguntas = [];

while ($fila = $resultado->fetch_assoc()) {

    $id = $fila["id_pregunta"];

    if (!isset($preguntas[$id])) {

        $preguntas[$id] = [
            "id_pregunta" => $id,
            "numero_pregunta" => $fila["numero_pregunta"],
            "enunciado" => $fila["enunciado"],
            "imagen" => $fila["imagen"],
            "dificultad" => $fila["dificultad"],
            "solucion" => $fila["solucion"],
            "url" => $fila["url"] ?? null,
            "id_alternativa_correcta" =>
                $fila["id_alternativa_correcta"],
            "alternativa_seleccionada" =>
                $fila["alternativa_seleccionada"],
            "alternativas" => []
        ];
    }

    $preguntas[$id]["alternativas"][] = [
        "id_alternativa" => $fila["id_alternativa"],
        "contenido" => $fila["contenido"],
        "es_correcta" => (bool) $fila["es_correcta"]
    ];
}

echo json_encode([
    "success" => true,
    "total" => count($preguntas),
    "data" => array_values($preguntas)
]);