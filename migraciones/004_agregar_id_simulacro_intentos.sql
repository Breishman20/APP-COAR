-- ============================================================================
-- Migración 004: Agregar `id_simulacro` a la tabla `intentos`
-- Propósito: Vincular intentos con simulacros administrados
-- No destructiva: agrega columna nullable
-- ============================================================================

USE `app_coar`;

-- 1. Agregar columna `id_simulacro` nullable a `intentos`
-- Los 118 intentos existentes mantendrán NULL (son simulacros aleatorios).
ALTER TABLE `intentos`
ADD COLUMN IF NOT EXISTS `id_simulacro` INT NULL DEFAULT NULL AFTER `id_usuario`;

-- 2. Agregar índice para consultas frecuentes
ALTER TABLE `intentos`
ADD INDEX `idx_intentos_id_simulacro` (`id_simulacro`);

-- ============================================================================
-- Verificación
-- ============================================================================
-- SELECT id_intento, id_usuario, id_simulacro, tipo, estado FROM intentos LIMIT 10;

-- ============================================================================
-- Rollback manual
-- ALTER TABLE `intentos` DROP INDEX `idx_intentos_id_simulacro`;
-- ALTER TABLE `intentos` DROP COLUMN `id_simulacro`;
-- ============================================================================
