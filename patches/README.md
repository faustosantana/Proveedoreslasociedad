# Parche en producción — reporte de facturas

Archivos en `/public_html/SystemSuplidor/historial/`

| Archivo | Rol |
|---|---|
| `reporte_config.php` | Wizard. POST de `limit` (incluido `0` = Todos) vía `FormData`. Descarga Excel con `fetch` + Blob; cierra el overlay al recibir la respuesta. |
| `generar_reporte.php` | Excel. Allowlist `0, 25, 50, 100`; `LIMIT` solo si el entero es `> 0`. Cabecera `X-Report-File: 1` en éxito. Errores en texto plano sin SQL. |

Backups UTC:

- Límite 25: `*.bak-20260825-123835`
- Overlay de descarga: `*.bak-20260825-131203`

No hay secretos en estos archivos. No desplegar el ZIP de marzo.
