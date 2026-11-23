<?php
/**
 * ============================================================================
 * Endpoint: Registrar material (video o PDF) - Profesor
 * ============================================================================
 * Método: POST
 * Rol requerido: profesor
 * Cuerpo JSON: {
 *   "id_tema": int,
 *   "tipo": "video"|"pdf",
 *   "titulo": string,
 *   "url": string,
 *   "descripcion": string|null,
 *   "visibilidad_youtube": "publico"|"no_listado"|"privado"|null
 * }
 * ============================================================================
 */

header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../middleware/autenticacion.php';

try {
    $sesion = autenticar_y_verificar_rol('profesor');
    $idUsuario = $sesion['id_usuario'];

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'JSON inválido']);
        exit;
    }

    // Validaciones
    $errores = [];
    if (empty($input['id_tema']) || !is_int($input['id_tema'])) {
        $errores[] = 'id_tema es requerido';
    }
    if (empty($input['tipo']) || !in_array($input['tipo'], ['video', 'pdf'], true)) {
        $errores[] = 'tipo debe ser video o pdf';
    }
    if (empty($input['titulo']) || !is_string($input['titulo'])) {
        $errores[] = 'titulo es requerido';
    }
    if (empty($input['url']) || !is_string($input['url'])) {
        $errores[] = 'url es requerida';
    }

    // Validar URL de YouTube si es video
    if ($input['tipo'] === 'video') {
        $url = $input['url'];
        $videoId = null;
        // Extraer ID de YouTube de formatos comunes
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('/^([a-zA-Z0-9_-]{11})$/', $url)) {
            $videoId = $url;
        }
        if ($videoId === null) {
            $errores[] = 'URL de YouTube no válida';
        }
    }

    if (!empty($errores)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => implode('; ', $errores)]);
        exit;
    }

    // Verificar que el tema existe
    $stmt = $conn->prepare("SELECT id_tema FROM temas WHERE id_tema = ? AND estado = 1");
    $stmt->bind_param("i", $input['id_tema']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'El tema no existe o está desactivado']);
        exit;
    }
    $stmt->close();

    // Insertar material
    $id_tema = $input['id_tema'];
    $tipo = $input['tipo'];
    $titulo = $input['titulo'];
    $url = $input['url'];
    $descripcion = $input['descripcion'] ?? null;
    $visibilidad_youtube = ($input['tipo'] === 'video' && !empty($input['visibilidad_youtube'])) ? $input['visibilidad_youtube'] : null;
    $id_profesor = $idUsuario;
    $estado_aprobacion = 'pendiente';
    $orden = 0;

    $stmt = $conn->prepare("
        INSERT INTO tema_materiales (
            id_tema, tipo, titulo, url, orden, estado, fecha_registro,
            id_profesor, estado_aprobacion, descripcion, visibilidad_youtube
        ) VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssiiss", $id_tema, $tipo, $titulo, $url, $orden, $id_profesor, $estado_aprobacion, $descripcion, $visibilidad_youtube);
    $stmt->execute();
    $idMaterial = $conn->insert_id;
    $stmt->close();

    // Registrar en auditoría
    $realizadoPor = $idUsuario;
    $idContenido = (string)$idMaterial;
    $tipoAuditoria = $input['tipo'];
    $accion = 'crear';
    $observaciones = "Material {$tipo} registrado por profesor, pendiente de aprobación";
    $stmt = $conn->prepare("
        INSERT INTO auditoria_contenido (id_contenido, tipo, accion, realizado_por, fecha, observaciones)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param("ssiss", $idContenido, $tipoAuditoria, $accion, $realizadoPor, $observaciones);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Material registrado exitosamente, pendiente de aprobación',
        'data' => [
            'id_material' => $idMaterial,
            'estado_aprobacion' => 'pendiente'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al registrar el material'
    ]);
} finally {
    $conn->close();
}

?>
