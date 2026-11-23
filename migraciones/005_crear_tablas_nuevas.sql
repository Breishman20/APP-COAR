-- ============================================================================
-- Migración 005: Crear tablas nuevas
-- Propósito: Soportar recuperación de contraseña, progreso, simulacros
--           administrados, auditoría y sanciones
-- No destructiva: solo crea tablas nuevas
-- ============================================================================

USE `app_coar`;

-- ============================================================================
-- 1. Tabla `recuperacion_contrasena`
-- Propósito: Almacenar tokens seguros para recuperación de contraseña
-- ============================================================================

CREATE TABLE IF NOT EXISTS `recuperacion_contrasena` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `token_hash` VARCHAR(255) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `vence_en` TIMESTAMP NULL DEFAULT NULL,
  `usado` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token_hash` (`token_hash`),
  KEY `idx_recuperacion_id_usuario` (`id_usuario`),
  KEY `idx_recuperacion_vence_en` (`vence_en`),
  CONSTRAINT `recuperacion_contrasena_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Tabla `progreso_actividad`
-- Propósito: Registrar el avance real del estudiante por actividad
-- ============================================================================

CREATE TABLE IF NOT EXISTS `progreso_actividad` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `id_tema` INT(11) NOT NULL,
  `tipo` ENUM('video','pdf','practica','evaluacion') NOT NULL,
  `porcentaje` DECIMAL(5,2) DEFAULT 0.00,
  `completado` TINYINT(1) DEFAULT 0,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_tema_tipo` (`id_usuario`,`id_tema`,`tipo`),
  KEY `idx_progreso_id_usuario` (`id_usuario`),
  KEY `idx_progreso_id_tema` (`id_tema`),
  CONSTRAINT `progreso_actividad_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `progreso_actividad_ibfk_2`
    FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Tabla `simulacros_administrados`
-- Propósito: Almacenar simulacros creados por el administrador
-- ============================================================================

CREATE TABLE IF NOT EXISTS `simulacros_administrados` (
  `id_simulacro` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL DEFAULT NULL,
  `duracion_minutos` INT(11) NOT NULL DEFAULT 120,
  `estado` ENUM('borrador','publicado','desactivado') DEFAULT 'borrador',
  `disponible_desde` TIMESTAMP NULL DEFAULT NULL,
  `disponible_hasta` TIMESTAMP NULL DEFAULT NULL,
  `fecha_creacion` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_simulacro`),
  KEY `idx_simulacros_estado` (`estado`),
  KEY `idx_simulacros_disponible` (`disponible_desde`,`disponible_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. Tabla `simulacro_preguntas`
-- Propósito: Versión congelada de las preguntas de cada simulacro
--           administrado. Conserva snapshot del contenido para que los
--           cambios posteriores en el banco no afecten simulacros publicados.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `simulacro_preguntas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_simulacro` INT(11) NOT NULL,
  `id_pregunta_original` INT(11) NULL DEFAULT NULL,
  `numero_orden` INT(11) NOT NULL,
  `enunciado_snapshot` TEXT NOT NULL,
  `imagen_snapshot` VARCHAR(255) NULL DEFAULT NULL,
  `dificultad_snapshot` ENUM('facil','media','dificil') DEFAULT 'media',
  `alternativas_snapshot` JSON NOT NULL,
  `respuesta_correcta_snapshot` INT(11) NOT NULL,
  `explicacion_snapshot` TEXT NULL DEFAULT NULL,
  `imagen_solucion_snapshot` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_simulacro_orden` (`id_simulacro`,`numero_orden`),
  KEY `idx_simulacro_preguntas_id_simulacro` (`id_simulacro`),
  CONSTRAINT `simulacro_preguntas_ibfk_1`
    FOREIGN KEY (`id_simulacro`) REFERENCES `simulacros_administrados` (`id_simulacro`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. Tabla `auditoria_plan`
-- Propósito: Registrar cambios de plan de usuarios
-- ============================================================================

CREATE TABLE IF NOT EXISTS `auditoria_plan` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `plan_anterior` ENUM('GRATIS','PREMIUM') NULL DEFAULT NULL,
  `plan_nuevo` ENUM('GRATIS','PREMIUM') NOT NULL,
  `cambiado_por` INT(11) NOT NULL,
  `cambiado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_plan_id_usuario` (`id_usuario`),
  KEY `idx_auditoria_plan_cambiado_por` (`cambiado_por`),
  CONSTRAINT `auditoria_plan_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `auditoria_plan_ibfk_2`
    FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. Tabla `auditoria_contenido`
-- Propósito: Registrar acciones sobre contenido (preguntas, videos, PDFs)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `auditoria_contenido` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_contenido` INT(11) NOT NULL,
  `tipo` ENUM('pregunta','video','pdf') NOT NULL,
  `accion` ENUM('creacion','modificacion','envio_revision','aprobacion','rechazo','desactivacion','reactivacion') NOT NULL,
  `realizado_por` INT(11) NOT NULL,
  `fecha` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL DEFAULT NULL,
  `datos_anteriores` JSON NULL DEFAULT NULL,
  `datos_nuevos` JSON NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_contenido_id` (`id_contenido`),
  KEY `idx_auditoria_contenido_realizado_por` (`realizado_por`),
  KEY `idx_auditoria_contenido_fecha` (`fecha`),
  CONSTRAINT `auditoria_contenido_ibfk_1`
    FOREIGN KEY (`realizado_por`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. Tabla `sanciones_usuarios`
-- Propósito: Registrar sanciones, suspensiones y revocaciones de permisos
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sanciones_usuarios` (
  `id_sancion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `id_admin` INT(11) NOT NULL,
  `tipo_sancion` ENUM('suspension','deshabilitacion','revocacion_permisos') NOT NULL,
  `motivo` TEXT NOT NULL,
  `fecha_inicio` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` TIMESTAMP NULL DEFAULT NULL,
  `fecha_reactivacion` TIMESTAMP NULL DEFAULT NULL,
  `estado` ENUM('activa','vencida','revocada') DEFAULT 'activa',
  `observaciones` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id_sancion`),
  KEY `idx_sanciones_id_usuario` (`id_usuario`),
  KEY `idx_sanciones_id_admin` (`id_admin`),
  KEY `idx_sanciones_estado` (`estado`),
  KEY `idx_sanciones_fecha_vencimiento` (`fecha_vencimiento`),
  CONSTRAINT `sanciones_usuarios_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `sanciones_usuarios_ibfk_2`
    FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Agregar foreign key de `intentos.id_simulacro` → `simulacros_administrados`
-- (Se hace aquí porque la tabla destino se creó en esta migración)
-- ============================================================================

ALTER TABLE `intentos`
ADD CONSTRAINT `intentos_ibfk_2`
FOREIGN KEY (`id_simulacro`) REFERENCES `simulacros_administrados` (`id_simulacro`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- ============================================================================
-- Verificación
-- ============================================================================
-- SHOW TABLES LIKE 'recuperacion%';
-- SHOW TABLES LIKE 'progreso%';
-- SHOW TABLES LIKE 'simulacros%';
-- SHOW TABLES LIKE 'simulacro_preguntas';
-- SHOW TABLES LIKE 'auditoria%';
-- SHOW TABLES LIKE 'sanciones%';

-- ============================================================================
-- Rollback manual (orden inverso)
-- ALTER TABLE `intentos` DROP FOREIGN KEY `intentos_ibfk_2`;
-- DROP TABLE IF EXISTS `sanciones_usuarios`;
-- DROP TABLE IF EXISTS `auditoria_contenido`;
-- DROP TABLE IF EXISTS `auditoria_plan`;
-- DROP TABLE IF EXISTS `simulacro_preguntas`;
-- DROP TABLE IF EXISTS `simulacros_administrados`;
-- DROP TABLE IF EXISTS `progreso_actividad`;
-- DROP TABLE IF EXISTS `recuperacion_contrasena`;
-- ============================================================================
