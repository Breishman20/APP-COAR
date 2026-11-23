<?php

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

// Validar el ID recibido
$id_usuario = filter_input(INPUT_GET, "id_usuario", FILTER_VALIDATE_INT);

if (!$id_usuario || $id_usuario <= 0) {
    echo json_encode([
        "success" => false,
        "mensaje" => "ID de usuario no válido"
    ]);
    exit;
}

// Consultar usuario
$sql = "SELECT 
            id_usuario,
            nombres,
            apellidos,
            correo,
            aliasUsuario,
            rol,
            estado,
            fecha_registro,
            dni,
            sede_coar,
            cole_procedencia,
            tipo_plan
        FROM usuarios
        WHERE id_usuario = ? AND estado = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "mensaje" => "Usuario no encontrado"
    ]);
    exit;
}

$usuario = $resultado->fetch_assoc();

echo json_encode([
    "success" => true,
    "mensaje" => "Perfil obtenido correctamente",
    "data" => $usuario
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>