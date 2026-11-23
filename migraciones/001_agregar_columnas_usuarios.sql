-- ============================================================================
-- Migración 001: Agregar columnas a la tabla `usuarios`
-- Propósito: Preparar la tabla para autenticación, roles, planes y registro
-- No destructiva: solo agrega columnas y modifica ENUM existente
-- ============================================================================

USE `app_coar`;

-- 1. Agregar columna `tipo_plan` (GRATIS/PREMIUM)
-- Todos los usuarios existentes quedan con GRATIS por defecto.
ALTER TABLE `usuarios`
ADD COLUMN IF NOT EXISTS `tipo_plan` ENUM('GRATIS','PREMIUM') DEFAULT 'GRATIS' AFTER `rol`;

-- 2. Agregar columna `dni` (documento de identidad)
ALTER TABLE `usuarios`
ADD COLUMN IF NOT EXISTS `dni` VARCHAR(20) NULL DEFAULT NULL AFTER `correo`;

-- 3. Agregar columna `sede_coar` (sede a la que postula)
ALTER TABLE `usuarios`
ADD COLUMN IF NOT EXISTS `sede_coar` VARCHAR(100) NULL DEFAULT NULL AFTER `dni`;

-- 4. Agregar columna `cole_procedencia` (colegio de procedencia)
ALTER TABLE `usuarios`
ADD COLUMN IF NOT EXISTS `cole_procedencia` VARCHAR(100) NULL DEFAULT NULL AFTER `sede_coar`;

-- 5. Actualizar ENUM de `rol` para incluir 'profesor'
-- El ENUM actual es ('admin','estudiante'). Se agrega 'profesor'.
ALTER TABLE `usuarios`
MODIFY COLUMN `rol` ENUM('admin','estudiante','profesor') DEFAULT 'estudiante';

-- 6. Migrar rol vacío ('') a 'admin' para el usuario actual
-- El usuario con rol vacío es el administrador del sistema.
UPDATE `usuarios` SET `rol` = 'admin' WHERE `rol` = '' OR `rol` IS NULL;

-- ============================================================================
-- Verificación
-- ============================================================================
-- SELECT id_usuario, nombres, apellidos, correo, rol, tipo_plan, dni, sede_coar, cole_procedencia FROM usuarios;

-- ============================================================================
-- Rollback manual (no automático)
-- Para revertir esta migración, se debe restaurar desde un respaldo previo,
-- ya que ALTER TABLE MODIFY COLUMN no es reversible automáticamente.
-- ============================================================================
