-- ============================================================================
-- Migración 002: Agregar columna `es_premium` a la tabla `temas`
-- Propósito: Diferenciar temas gratuitos de temas premium
-- No destructiva: solo agrega columna con valor predeterminado 0
-- ============================================================================

USE `app_coar`;

-- 1. Agregar columna `es_premium` a `temas`
-- Los 11 temas existentes quedan como gratuitos (0) por defecto.
ALTER TABLE `temas`
ADD COLUMN IF NOT EXISTS `es_premium` TINYINT(1) DEFAULT 0 AFTER `descripcion`;

-- 2. Asegurar que los temas existentes sean gratuitos
UPDATE `temas` SET `es_premium` = 0 WHERE `es_premium` IS NULL;

-- ============================================================================
-- Verificación
-- ============================================================================
-- SELECT id_tema, nombre, es_premium FROM temas;

-- ============================================================================
-- Rollback manual
-- ALTER TABLE `temas` DROP COLUMN `es_premium`;
-- ============================================================================
