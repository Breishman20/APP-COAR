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

// Obtener intento y plan del usuario.
$stmt = $conn->prepare(
    "SELECT
        i.id_intento,
        i.id_usuario,
        i.tipo,
        i.estado,
        i.puntaje,
        i.fecha_inicio,
        i.fecha_fin,
        u.tipo_plan
     FROM intentos i
     INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
     WHERE i.id_intento = ?"
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

if ($intento["estado"] !== "finalizado") {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "mensaje" => "La evaluación todavía no ha finalizado"
    ]);
    exit;
}

$esPremium = $intento["tipo_plan"] === "premium";

$stmt = $conn->prepare(
    "SELECT
        ip.numero_pregunta,
        p.id_pregunta,
        p.enunciado,
        p.solucion,
        p.imagen_solucion,
        p.es_premium,
        r.id_alternativa AS seleccionada,
        r.es_correcta,
        correcta.id_alternativa AS alternativa_correcta,
        correcta.contenido AS respuesta_correcta

     FROM intento_preguntas ip

     INNER JOIN preguntas p
        ON ip.id_pregunta = p.id_pregunta

     LEFT JOIN respuestas r
        ON r.id_intento = ip.id_intento
        AND r.id_pregunta = ip.id_pregunta

     INNER JOIN alternativas correcta
        ON correcta.id_pregunta = p.id_pregunta
        AND correcta.es_correcta = TRUE

     WHERE ip.id_intento = ?
     ORDER BY ip.numero_pregunta"
);

$stmt->bind_param("i", $id_intento);
$stmt->execute();

$resultado = $stmt->get_result();

$preguntas = [];

while ($fila = $resultado->fetch_assoc()) {

    $puedeVerSolucion = !$fila["es_premium"] || $esPremium;

    $preguntas[] = [
        "numero_pregunta" => (int)$fila["numero_pregunta"],
        "id_pregunta" => (int)$fila["id_pregunta"],
        "enunciado" => $fila["enunciado"],
        "seleccionada" => $fila["seleccionada"],
        "es_correcta" => (bool)$fila["es_correcta"],
        "alternativa_correcta" => (int)$fila["alternativa_correcta"],
        "respuesta_correcta" => $fila["respuesta_correcta"],
        "solucion" => $puedeVerSolucion ? $fila["solucion"] : null,
        "imagen_solucion" => $puedeVerSolucion
            ? $fila["imagen_solucion"]
            : null,
        "requiere_premium" => !$puedeVerSolucion
    ];
}

echo json_encode([
    "success" => true,
    "data" => [
        "intento" => $intento,
        "preguntas" => $preguntas
    ]
]);