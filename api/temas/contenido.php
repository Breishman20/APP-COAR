<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";

// Recibir ID del tema
$id_tema = filter_input(INPUT_GET, "id_tema", FILTER_VALIDATE_INT);

if (!$id_tema || $id_tema <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "Debe proporcionar un ID de tema válido"
    ]);

    exit;
}

// Consultar información del tema
$sqlTema = "SELECT id_tema, nombre, descripcion
            FROM temas
            WHERE id_tema = ? AND estado = TRUE";

$stmtTema = $conn->prepare($sqlTema);
$stmtTema->bind_param("i", $id_tema);
$stmtTema->execute();

$resultadoTema = $stmtTema->get_result();

if ($resultadoTema->num_rows === 0) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "mensaje" => "Tema no encontrado"
    ]);

    $stmtTema->close();
    $conn->close();

    exit;
}

$tema = $resultadoTema->fetch_assoc();
$stmtTema->close();

// Consultar materiales del tema (video y PDF)
$materiales = [
    "video" => null,
    "pdf" => null
];

// Verificar si existe la tabla tema_materiales
$checkTable = $conn->query("SHOW TABLES LIKE 'tema_materiales'");

if ($checkTable && $checkTable->num_rows > 0) {
    $sqlMateriales = "SELECT tipo, titulo, url
                      FROM tema_materiales
                      WHERE id_tema = ? AND estado = TRUE
                      ORDER BY orden";

    $stmtMateriales = $conn->prepare($sqlMateriales);
    $stmtMateriales->bind_param("i", $id_tema);
    $stmtMateriales->execute();

    $resultadoMateriales = $stmtMateriales->get_result();

    while ($fila = $resultadoMateriales->fetch_assoc()) {
        $tipo = $fila["tipo"];

        if ($tipo === "video" || $tipo === "pdf") {
            $materiales[$tipo] = [
                "titulo" => $fila["titulo"],
                "url" => $fila["url"]
            ];
        }
    }

    $stmtMateriales->close();
}

echo json_encode([
    "success" => true,
    "tema" => [
        "id_tema" => $tema["id_tema"],
        "nombre" => $tema["nombre"],
        "descripcion" => $tema["descripcion"]
    ],
    "materiales" => $materiales
], JSON_UNESCAPED_UNICODE);

$conn->close();

?>
