<?php

$host = "localhost";
$user = "root";
$pass = "";
$bd = "app_coar";

// Si MySQL utiliza otro puerto, agrégalo como quinto parámetro.
$conn = new mysqli($host, $user, $pass, $bd, 3307);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


?>