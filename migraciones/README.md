# Migraciones de Base de Datos — APP COAR

## Descripción

Scripts SQL de migración seguros para adaptar la base de datos `app_coar` a los nuevos requisitos de la Fase I.

## Instrucciones de Ejecución

**Importante:** Ejecutar los scripts en el orden numérico indicado. Cada script es idempotente y no destructivo.

### 1. Respaldo previo

Antes de ejecutar cualquier migración, crea un respaldo completo de la base de datos:

```bash
mysqldump -u root -P 3307 app_coar > respaldo_anterior_a_migracion.sql
```

### 2. Ejecutar migraciones SQL

Ejecutar en este orden exacto:

1. `001_agregar_columnas_usuarios.sql` — Columnas `tipo_plan`, `dni`, `sede_coar`, `cole_procedencia`; ENUM de `rol` con 'profesor'; migrar rol vacío a 'admin'.
2. `002_agregar_columnas_temas.sql` — Columna `es_premium` en `temas`.
3. `003_modificar_tema_materiales.sql` — Eliminar índice único, agregar columnas de gestión de profesor.
4. `004_agregar_id_simulacro_intentos.sql` — Columna `id_simulacro` en `intentos`.
5. `005_crear_tablas_nuevas.sql` — Tablas nuevas: recuperación, progreso, simulacros, auditoría, sanciones.

```bash
mysql -u root -P 3307 app_coar < 001_agregar_columnas_usuarios.sql
mysql -u root -P 3307 app_coar < 002_agregar_columnas_temas.sql
mysql -u root -P 3307 app_coar < 003_modificar_tema_materiales.sql
mysql -u root -P 3307 app_coar < 004_agregar_id_simulacro_intentos.sql
mysql -u root -P 3307 app_coar < 005_crear_tablas_nuevas.sql
```

### 3. Migrar contraseña de texto plano

Después de las migraciones SQL, ejecutar el script PHP para convertir la contraseña:

```bash
php migrar_contrasena.php
```

O desde el navegador:

```
http://localhost/projectos-PHP/APP_COAR/migraciones/migrar_contrasena.php
```

**Eliminar este archivo después de ejecutarlo exitosamente.**

### 4. Verificación

Después de ejecutar todas las migraciones, verificar:

```sql
-- Verificar usuarios
SELECT id_usuario, nombres, correo, rol, tipo_plan, dni, sede_coar, cole_procedencia FROM usuarios;

-- Verificar temas
SELECT id_tema, nombre, es_premium FROM temas;

-- Verificar materiales
SELECT id_material, id_tema, id_profesor, tipo, titulo, estado_aprobacion FROM tema_materiales;

-- Verificar intentos
SELECT id_intento, id_usuario, id_simulacro, tipo, estado FROM intentos LIMIT 5;

-- Verificar tablas nuevas
SHOW TABLES LIKE 'recuperacion%';
SHOW TABLES LIKE 'progreso%';
SHOW TABLES LIKE 'simulacros%';
SHOW TABLES LIKE 'simulacro_preguntas';
SHOW TABLES LIKE 'auditoria%';
SHOW TABLES LIKE 'sanciones%';

-- Verificar conteos
SELECT COUNT(*) AS total_usuarios FROM usuarios;
SELECT COUNT(*) AS total_areas FROM areas;
SELECT COUNT(*) AS total_temas FROM temas;
SELECT COUNT(*) AS total_preguntas FROM preguntas;
SELECT COUNT(*) AS total_alternativas FROM alternativas;
SELECT COUNT(*) AS total_intentos FROM intentos;
SELECT COUNT(*) AS total_intento_preguntas FROM intento_preguntas;
SELECT COUNT(*) AS total_respuestas FROM respuestas;
SELECT COUNT(*) AS total_materiales FROM tema_materiales;
```

## Datos Preservados

Los siguientes datos se conservan intactos después de las migraciones:

- 1 usuario (rol migrado a 'admin', plan 'GRATIS')
- 5 áreas
- 11 temas (todos gratuitos)
- 74 preguntas
- 299 alternativas
- 118 intentos (con `id_simulacro = NULL`)
- 3887 intento_preguntas
- 161 respuestas
- 2 tema_materiales (estado 'aprobado')

## Rollback

Si es necesario revertir las migraciones, restaurar desde el respaldo previo:

```bash
mysql -u root -P 3307 app_coar < respaldo_anterior_a_migracion.sql
```

Cada script incluye instrucciones manuales de rollback comentadas al final.

## Notas

- Los scripts usan `ADD COLUMN IF NOT EXISTS` y `CREATE TABLE IF NOT EXISTS` para ser idempotentes.
- No se eliminan ni modifican datos existentes.
- Los valores predeterminados aseguran compatibilidad con los datos históricos.
- El script `migrar_contrasena.php` debe eliminarse después de su ejecución.
