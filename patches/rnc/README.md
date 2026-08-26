# Parche validación RNC/Cédula (cierre)

Desplegado en producción 2026-08-26. Sin PII. Sin INSERT/UPDATE de proveedores.

## Qué cambia

- Normalización de dígitos (`044-1234567-8` ≡ `04412345678` ≡ espacios/puntos) en AJAX y en `registro.php` / `validarUnicos()`.
- Estados: `INVALIDO` | `DUPLICADO` | `DOCUMENTO_DISPONIBLE` | `ERROR`.
- No hay checksum ni DGII en el proyecto. "Disponible" ≠ identidad validada.
- Dummy `000-0000000-0` → `INVALIDO` (ceros).

## Archivos vivos

- `PanelDeUsuario/modulos/DocumentoIdentidad.php` (nuevo)
- `PanelDeUsuario/modulos/ValidacionesFormulario.php`
- `PanelDeUsuario/registro.php`
- `PanelDeUsuario/php/validar_rnc.php`
- `PanelDeUsuario/modulos/js/FormFieldHandler.js`
- `PanelDeUsuario/modulos/js/PanelManager.js`
- `PanelDeUsuario/modulos/js/FormRegistrationApp.js`

## Backups de esta intervención

- `*.bak-20260826-204823` = copia **RETR del vivo anterior** (rollback de este cambio)
- HTTP de esos `.bak` debe ser 403/404

## Backup original de `validar_rnc.php`

- `validar_rnc.php.bak-20260826-201500` es copia del archivo **nuevo** de la intervención previa (SIZE 2520). **NO** es rollback del código previo (vivo anterior 1968 bytes; RETR falló).
- **BACKUP ORIGINAL validar_rnc.php: NO**
- No borrar esa copia; no presentarla como original.

## No tocado

- Filas de `proveedores` / `empresa_proveedor`
- Estados `activo`
- Servicios externos
