# FASE 3 — IDOR proveedores

Fecha: 2026-08-25. Producción UP. DB no tocada.

Parches en `patches/idor/`. Backups UTC `*.bak-20260825-160300` (lecturas), `php/acciones-factura.php.bak-20260825-180631` y `php/limpiar_facturas.php.bak-20260825-184750`.

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

`accion=xxxx` anónimo: **401**. Justech autenticado (recheck): **400** `Acción no válida.` Proveedor + aceptar: **403** `No autorizado`.

`fact_6a7c79e56fb4c` (NCF B0100001638, MacBook Air) ya estaba **Aceptada** en las pruebas de aislamiento Justech (`browser_results.txt`, DETALLE_PROPIO) **antes** de este parche. Tras los GET rechazados sigue Aceptada. **FACTURAS MODIFICADAS DURANTE PRUEBA = 0.** No se revirtió ni se escribió estado.

## `php/limpiar_facturas.php` (P0, 2026-08-25)

Herramienta de mantenimiento **sin callers** (PHP/JS/HTML/email/cron). No se borró el archivo.

Antes: cualquier GET ejecutaba transacción + `DELETE FROM facturas` de todas las `eliminada` con `fecha_eliminacion` ≥ 15 días. Sin sesión, sin CSRF, sin `unlink`. No borra abonos. No toma IDs del request.

Después: sesión → POST → CSRF (`validarTokenCSRF` / `$_SESSION['csrf_token']`) → rol **admin** → el mismo DELETE (prepared, sin input). GET autenticado **405**. Anónimo **401**. Proveedor/empresa/usuario **403** en POST.

- Backup: `php/limpiar_facturas.php.bak-20260825-184750` (HTTP 403; SHA `54fa05d1216c03791628c75a2b134beb18985307d5e6f76d03c0fd5bd9ef6738`)
- SHA después: `3f582a8401cc4358019dcb0480a98889fd9184d4da1e5799603874b79dc778d8`
- Anónimo GET/POST: **401** `No autorizado`. Home **200**.
- Justech GET: **405** `Método no permitido`. POST sin CSRF y con `csrf_token=fake`: **403** `Token de seguridad inválido.` DELETE no se alcanzó (rol admin va después del CSRF).
- No se ejecutó POST de admin. **REGISTROS ELIMINADOS DURANTE PRUEBAS = 0.** **ARCHIVOS ELIMINADOS = 0.**

La misma condición de purge sigue en `historial.php` (cada carga autenticada). Inventario; no parcheado en esta subfase.
