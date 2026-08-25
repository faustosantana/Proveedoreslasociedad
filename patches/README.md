# Parche en producción — reporte de facturas

Archivos desplegados el 2026-08-25 en:

`/public_html/SystemSuplidor/historial/`

| Archivo | Rol |
|---|---|
| `reporte_config.php` | Wizard. El submit ahora copia `limit` (incluido `0` = Todos) a inputs hidden. |
| `generar_reporte.php` | Excel. Allowlist `0, 25, 50, 100`; `LIMIT` solo si el entero es `> 0`. |

Backups en el servidor (UTC):

- `historial/generar_reporte.php.bak-20260825-123835`
- `historial/reporte_config.php.bak-20260825-123835`

No hay secretos en estos archivos. No desplegar el ZIP de marzo: el wizard no existía ahí.
