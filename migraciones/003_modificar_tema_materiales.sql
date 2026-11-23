-- ============================================================================
-- Migración 003: Modificar `tema_materiales` para gestión de profesor
-- Propósito: Permitir múltiples materiales por tema y agregar gestión de aprobación
-- No destructiva: elimina índice único y agrega columnas
-- ============================================================================

USE `app_coar`;

-- 1. Eliminar el índice único `unique_tema_tipo` para permitir varios videos
-- y varios PDF por tema.
-- Antes de eliminar, verificar que no existan duplicados actuales.
-- Los 2 materiales existentes (1 video, 1 PDF para tema 1) no son duplicados
-- porque tienen tipos diferentes ('video' y 'pdf').
ALTER TABLE `tema_materiales`
DROP INDEX `unique_tema_tipo`;

-- 2. Agregar columna `id_profesor` (nullable para materiales históricos)
ALTER TABLE `tema_materiales`
ADD COLUMN IF NOT EXISTS `id_profesor` INT NULL DEFAULT NULL AFTER `id_tema`;

-- 3. Agregar columna `estado_aprobacion`
-- Los materiales existentes quedan como 'aprobado' para no perder disponibilidad.
ALTER TABLE `tema_materiales`
ADD COLUMN IF NOT EXISTS `estado_aprobacion` ENUM('borrador','pendiente','aprobado','rechazado','desactivado') DEFAULT 'aprobado' AFTER `id_profesor`;

-- 4. Agregar columna `observaciones` (comentarios del administrador)
ALTER TABLE `tema_materiales`
ADD COLUMN IF NOT EXISTS `observaciones` TEXT NULL DEFAULT NULL AFTER `estado_aprobacion`;

-- 5. Agregar columna `descripcion` (descripción del material)
ALTER TABLE `tema_materiales`
ADD COLUMN IF NOT EXISTS `descripcion` TEXT NULL DEFAULT NULL AFTER `observaciones`;

-- 6. Agregar columna `visibilidad_youtube` (solo para videos)
ALTER TABLE `tema_materiales`
ADD COLUMN IF NOT EXISTS `visibilidad_youtube` ENUM('publico','no_listado','privado') NULL DEFAULT NULL AFTER `descripcion`;

-- 7. Asegurar que los materiales existentes tengan estado 'aprobado'
UPDATE `tema_materiales` SET `estado_aprobacion` = 'aprobado' WHERE `estado_aprobacion` IS NULL;

-- 8. Agregar foreign key para `id_profesor` → `usuarios.id_usuario`
-- Usar ON DELETE SET NULL para que si se elimina un profesor, los materiales
-- conserven el registro pero pierdan la referencia.
ALTER TABLE `tema_materiales`
ADD CONSTRAINT `tema_materiales_ibfk_2`
FOREIGN KEY (`id_profesor`) REFERENCES `usuarios` (`id_usuario`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- 9. Agregar índices para consultas frecuentes
ALTER TABLE `tema_materiales`
ADD INDEX `idx_tema_materiales_estado_aprobacion` (`estado_aprobacion`);

ALTER TABLE `tema_materiales`
ADD INDEX `idx_tema_materiales_id_profesor` (`id_profesor`);

-- ============================================================================
-- Verificación
-- ============================================================================
-- SELECT id_material, id_tema, id_profesor, tipo, titulo, estado_aprobacion, visibilidad_youtube FROM tema_materiales;
-- SHOW INDEX FROM tema_materiales;

-- ============================================================================
-- Rollback manual
-- ALTER TABLE `tema_materiales` DROP FOREIGN KEY `tema_materiales_ibfk_2`;
-- ALTER TABLE `tema_materiales` DROP COLUMN `id_profesor`;
-- ALTER TABLE `tema_materiales` DROP COLUMN `estado_aprobacion`;
-- ALTER TABLE `tema_materiales` DROP COLUMN `observaciones`;
-- ALTER TABLE `tema_materiales` DROP COLUMN `descripcion`;
-- ALTER TABLE `tema_materiales` DROP COLUMN `visibilidad_youtube`;
-- ALTER TABLE `tema_materiales` ADD UNIQUE INDEX `unique_tema_tipo` (`id_tema`, `tipo`);
-- ============================================================================
