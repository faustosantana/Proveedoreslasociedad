# FASE 3 — IDOR proveedores

Fecha: 2026-08-25. Producción UP. DB no tocada.

Parches en `patches/idor/`. Backups UTC `*.bak-20260825-160300`.

Pruebas autenticadas Justech (2026-08-25, solo lectura):

- Preview Justech total 58; tamper `id_proveedor`/`proveedor` sin ampliar (mismo total, solo Justech).
- Excel 25: 25 filas, Proveedor=Justech, FILAS AJENAS=0.
- Excel Todos: 58 filas, Proveedor=Justech, FILAS AJENAS=0.
- Detalle/API/saldo/documento de factura ajena: 404 genérico, sin datos.
- `php/acciones-factura.php` producción: GET sin sesión, UPDATE estado. Auditado; no ejecutado; no parcheado.
