# FASE 3 — IDOR proveedores

Fecha: 2026-08-25. Producción UP. DB no tocada.

Parches en `patches/idor/`. Backups UTC `*.bak-20260825-160300` (lecturas) y `php/acciones-factura.php.bak-20260825-180631` (escritura).

## Aislamiento de lectura (Justech)

- Preview Justech total 58; tamper `id_proveedor`/`proveedor` sin ampliar (mismo total, solo Justech).
- Excel 25: 25 filas, Proveedor=Justech, FILAS AJENAS=0.
- Excel Todos: 58 filas, Proveedor=Justech, FILAS AJENAS=0.
- Detalle/API/saldo/documento de factura ajena: 404 genérico, sin datos.
- `PROVEEDOR ISOLATION = PASS`

## `php/acciones-factura.php` (parche 2026-08-25)

GET LEGACY — AUTHENTICATED ONLY. No se migró a POST+CSRF: la UI no llama este archivo.

- Backup: `php/acciones-factura.php.bak-20260825-180631` (HTTP 403; SHA `090c02f82d983a160a16ffccfabffb9c1cdc4009f05a7e824f6899ebacf6b24a`)
- SHA antes: el mismo del backup
- SHA después: `9e9d6bc448f6a5bf999f32b8972aa105969e4eced5f67d5d6ccae300034ce75b`
- Rollback: FTP STOR del `.bak` sobre el vivo
- Sesión + rol (`admin`/`empresa` solamente) + ownership en el UPDATE + allowlist `aceptar|eliminar|restaurar`
- Proveedor y usuario: 403 (mismo criterio que `acciones/eliminar_factura.php`)
- Empresa: `WHERE id_factura = ? AND id_empresa = ?` (sesión, no GET)
- Admin: permisos actuales, sin ampliar
- Anónimo GET con id/acción reales: **401**, body `No autorizado`. Home 200. Backup 403.
- `acciones/actualizar_estado_factura.php` (HMAC de emails) **no** se tocó.

Consumidores: ningún botón/JS/formulario actual. Correos vigentes usan HMAC. `php/enviar-correos.php` (legado, sin callers) apunta a `{APP_URL}/acciones-factura.php` (ruta incorrecta, falta `/php/`). EMAIL DEPENDENCY actual: **NO**.

### Pruebas (sin writes)

Anónimo GET con ids reales (`aceptar`/`eliminar`/`restaurar`/`xxxx`): **401** `No autorizado`. Home **200**. Backup HTTP **403**.

Justech (sesión proveedor), propia `fact_6a7c79e56fb4c` y ajena `fact_69b20cab54c30`: **403** `No autorizado` en aceptar/eliminar/restaurar. Sin SweetAlert de éxito. Sin redirect a historial.

`accion=xxxx` con Justech: **403** `No autorizado` (el rol proveedor corta antes de la allowlist en la versión probada). Anónimo `xxxx`: **401**. Código vivo actual: sesión → allowlist **400** → rol **403**.

`fact_6a7c79e56fb4c` (NCF B0100001638, MacBook Air) ya estaba **Aceptada** en las pruebas de aislamiento Justech (`browser_results.txt`, DETALLE_PROPIO) **antes** de este parche. Tras los GET rechazados sigue Aceptada. **FACTURAS MODIFICADAS DURANTE PRUEBA = 0.** No se revirtió ni se escribió estado.
