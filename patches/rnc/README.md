# Parche validación RNC/Cédula (registro de proveedores)

Desplegado en producción 2026-08-26. Sin PII.

## Archivos vivos

- `PanelDeUsuario/modulos/js/FormFieldHandler.js`
- `PanelDeUsuario/modulos/js/PanelManager.js`
- `PanelDeUsuario/modulos/js/FormRegistrationApp.js`
- `PanelDeUsuario/php/validar_rnc.php`

## Backups

- `*.bak-20260826-201500` junto a cada archivo (HTTP 403)
- `validar_rnc.php.bak-20260826-201500` es copia del archivo **nuevo** (RETR del vivo anterior 1968 bytes falló por timeout PASV)

## No tocado

- Schema / unique index
- `ValidacionesFormulario.php` (tamaño vivo ≠ copia local)
- DGII (no existe en este flujo)
