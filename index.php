<?php

header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "success" => true,
    "proyecto" => "APP COAR",
    "version" => "1.0",
    "mensaje" => "API REST funcionando correctamente",
    "endpoints" => [
        "login" => "/api/login.php",
        "areas" => "/api/areas/listar.php",
        "temas" => "/api/temas/listar.php",
        "preguntas" => "/api/preguntas/listar.php"
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>