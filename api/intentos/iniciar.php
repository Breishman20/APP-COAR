
<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// 1. RECIBIR DATOS
// ==========================================

$datos = json_decode(file_get_contents("php://input"), true);

$id_usuario = $datos["id_usuario"] ?? null;
$tipo = $datos["tipo"] ?? null;
$id_area = $datos["id_area"] ?? null;
$id_tema = $datos["id_tema"] ?? null;
$dificultad = $datos["dificultad"] ?? null;

$cantidad = ($tipo === "simulacro") ? 50 : 10;
$tiempo = ($tipo === "simulacro") ? 120 : null;

$debug = [];
$transaccionIniciada = false;

// ==========================================
// 2. VALIDAR DATOS
// ==========================================

if (!$id_usuario || !in_array($tipo, ["practica", "simulacro"])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "Datos incorrectos"
    ]);

    exit;
}

if ($tipo === "practica" && (!$id_area || !$id_tema)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => "La práctica necesita área y tema"
    ]);

    exit;
}

// ==========================================
// 3. INICIAR PROCESO
// ==========================================

try {

    $conn->begin_transaction();
    $transaccionIniciada = true;

    // ======================================
    // 4. VERIFICAR USUARIO
    // ======================================

    $stmt = $conn->prepare(
        "SELECT id_usuario
         FROM usuarios
         WHERE id_usuario = ? AND estado = TRUE"
    );

    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("Usuario no válido");
    }

    // ======================================
    // 5. DIAGNÓSTICO DE BASE DE DATOS
    // ======================================

    $debug = [
        "base_datos" => $conn->query("SELECT DATABASE()")->fetch_row()[0],
        "puerto" => $conn->query("SELECT @@port")->fetch_row()[0],
        "id_usuario" => $id_usuario,
        "tipo" => $tipo,
        "id_area" => $id_area,
        "id_tema" => $id_tema,
        "dificultad" => $dificultad,
        "cantidad_solicitada" => $cantidad
    ];

    // ======================================
    // 6. CONTAR PREGUNTAS DISPONIBLES
    // ======================================

    if ($tipo === "practica") {

        $sqlConteo = "
            SELECT COUNT(*) AS total
            FROM preguntas p
            INNER JOIN temas t ON p.id_tema = t.id_tema
            WHERE t.id_area = ?
            AND p.id_tema = ?
            AND p.estado = 1
        ";

        if (!empty($dificultad)) {
            $sqlConteo .= " AND p.dificultad = ?";
        }

        $stmt = $conn->prepare($sqlConteo);

        if (!empty($dificultad)) {
            $stmt->bind_param("iis", $id_area, $id_tema, $dificultad);
        } else {
            $stmt->bind_param("ii", $id_area, $id_tema);
        }

    } else {

        $sqlConteo = "
            SELECT COUNT(*) AS total
            FROM preguntas
            WHERE estado = 1
        ";

        $stmt = $conn->prepare($sqlConteo);
    }

    $stmt->execute();

    $conteo = $stmt->get_result()->fetch_assoc();

    $debug["preguntas_disponibles"] = (int)$conteo["total"];

    // ======================================
    // 7. CREAR INTENTO
    // ======================================

    $stmt = $conn->prepare(
        "INSERT INTO intentos
        (id_usuario, tipo, estado, tiempo_limite)
        VALUES (?, ?, 'en_proceso', ?)"
    );

    $stmt->bind_param("isi", $id_usuario, $tipo, $tiempo);
    $stmt->execute();

    $id_intento = $conn->insert_id;

    // ======================================
    // 8. SELECCIONAR PREGUNTAS
    // ======================================

    $idsPreguntas = [];

    if ($tipo === "practica") {

        // Primero intentar con la dificultad seleccionada
        $sqlPractica = "
            SELECT p.id_pregunta
             FROM preguntas p
             INNER JOIN temas t ON p.id_tema = t.id_tema
             WHERE t.id_area = ?
             AND p.id_tema = ?
             AND p.estado = 1
        ";

        $conDificultad = false;
        if (!empty($dificultad)) {
            $sqlPractica .= " AND p.dificultad = ?";
            $conDificultad = true;
        }

        $sqlPractica .= " ORDER BY RAND() LIMIT ?";

        $stmt = $conn->prepare($sqlPractica);

        if ($conDificultad) {
            $stmt->bind_param("iisi", $id_area, $id_tema, $dificultad, $cantidad);
        } else {
            $stmt->bind_param("iii", $id_area, $id_tema, $cantidad);
        }

        $stmt->execute();
        $resultado = $stmt->get_result();

        $debug["preguntas_encontradas"] = $resultado->num_rows;
        $debug["dificultad"] = $dificultad;

        while ($fila = $resultado->fetch_assoc()) {
            $idsPreguntas[] = $fila["id_pregunta"];
        }

        // Si no se encontraron preguntas con la dificultad, intentar sin filtro de dificultad
        if (empty($idsPreguntas) && $conDificultad) {
            $debug["fallback_sin_dificultad"] = true;

            $stmt2 = $conn->prepare(
                "SELECT p.id_pregunta
                 FROM preguntas p
                 INNER JOIN temas t ON p.id_tema = t.id_tema
                 WHERE t.id_area = ?
                 AND p.id_tema = ?
                 AND p.estado = 1
                 ORDER BY RAND()
                 LIMIT ?"
            );
            $stmt2->bind_param("iii", $id_area, $id_tema, $cantidad);
            $stmt2->execute();
            $resultado2 = $stmt2->get_result();

            while ($fila = $resultado2->fetch_assoc()) {
                $idsPreguntas[] = $fila["id_pregunta"];
            }

            $debug["preguntas_encontradas"] = count($idsPreguntas);
        }

    } else {

        /*
         * SIMULACRO: hasta 50 preguntas
         * - Primero: todas las disponibles de Razonamiento Verbal (id_area = 5)
         * - Luego: completar con preguntas de otras áreas hasta 50
         */

        // Obtener todas las preguntas de Razonamiento Verbal disponibles
        $stmtVerbal = $conn->prepare(
            "SELECT p.id_pregunta
             FROM preguntas p
             INNER JOIN temas t ON p.id_tema = t.id_tema
             WHERE t.id_area = 5
             AND p.estado = 1
             ORDER BY RAND()"
        );
        $stmtVerbal->execute();
        $resultadoVerbal = $stmtVerbal->get_result();

        $verbalIds = [];
        while ($fila = $resultadoVerbal->fetch_assoc()) {
            $verbalIds[] = $fila["id_pregunta"];
        }

        $debug["verbal_disponibles"] = count($verbalIds);

        // Agregar las de Razonamiento Verbal
        foreach ($verbalIds as $id) {
            $idsPreguntas[] = $id;
        }

        // Calcular cuántas más necesitamos para llegar a 50
        $faltantes = $cantidad - count($idsPreguntas);

        if ($faltantes > 0) {
            // Completar con preguntas de otras áreas (excluyendo las ya seleccionadas)
            $placeholders = str_repeat('?,', $faltantes - 1) . '?';
            $stmtResto = $conn->prepare(
                "SELECT p.id_pregunta
                 FROM preguntas p
                 INNER JOIN temas t ON p.id_tema = t.id_tema
                 WHERE t.id_area != 5
                 AND p.estado = 1
                 AND p.id_pregunta NOT IN (" . implode(',', array_fill(0, count($verbalIds), '?')) . ")
                 ORDER BY RAND()
                 LIMIT ?"
            );

            $params = array_merge($verbalIds, [$faltantes]);
            $types = str_repeat('i', count($verbalIds) + 1);
            $stmtResto->bind_param($types, ...$params);
            $stmtResto->execute();
            $resultadoResto = $stmtResto->get_result();

            while ($fila = $resultadoResto->fetch_assoc()) {
                $idsPreguntas[] = $fila["id_pregunta"];
            }

            $debug["resto_agregadas"] = $faltantes;
        }

        $debug["preguntas_encontradas"] = count($idsPreguntas);
    }

    error_log("DIAGNOSTICO INICIAR: " . json_encode($debug));

    // ======================================
    // 9. GUARDAR PREGUNTAS DEL INTENTO
    // ======================================

    $numero = 1;

    foreach ($idsPreguntas as $id_pregunta) {

        $insertar = $conn->prepare(
            "INSERT INTO intento_preguntas
            (id_intento, id_pregunta, numero_pregunta)
            VALUES (?, ?, ?)"
        );

        $insertar->bind_param(
            "iii",
            $id_intento,
            $id_pregunta,
            $numero
        );

        $insertar->execute();

        $numero++;
    }

    $total = $numero - 1;

    // ======================================
    // 10. VALIDAR PREGUNTAS
    // ======================================

    if ($total === 0) {
        throw new Exception("No hay preguntas disponibles para esta evaluación");
    }

    // ======================================
    // 11. CONFIRMAR
    // ======================================

    $conn->commit();
    $transaccionIniciada = false;

    echo json_encode([
        "success" => true,
        "mensaje" => "Evaluación iniciada",
        "data" => [
            "id_intento" => $id_intento,
            "tipo" => $tipo,
            "total_preguntas" => $total,
            "tiempo_limite" => $tiempo
        ],
        "debug" => $debug
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        $conn->rollback();
    }

    error_log("ERROR INICIAR: " . $e->getMessage());

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage(),
        "debug" => $debug
    ], JSON_UNESCAPED_UNICODE);
}
