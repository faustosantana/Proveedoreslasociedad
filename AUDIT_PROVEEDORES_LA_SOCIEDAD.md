# Auditoría — proveedoreslasociedad.com

Fecha: 2026-08-25  
Alcance: bug de Excel “Todos los registros” (25 filas) + hallazgos de seguridad.  
Este repositorio es **público**. Este informe **no incluye** contraseñas, `.env`, dumps ni datos de facturas.

---

## Estado (2026-08-25, tarde UTC)

**El bug del wizard está identificado y parcheado en producción.** FTP/cPanel permitieron leer el fuente, respaldar y subir el hotfix. El parche se re-leyó por FTP: los SHA-256 coinciden con los archivos en `patches/historial/`.

| Pieza | Estado |
|---|---|
| Causa raíz | **Confirmada** (JS `cloneNode()` + default PHP 25) |
| Hotfix en servidor | **Desplegado** 12:38:35 UTC; re-verificado por RETR |
| SQL “Todos” vs 25 | **423** vs **25** (misma query del wizard, sin filtros) |
| Excel del wizard (UI) | Pendiente de **login de la aplicación** (la clave de cPanel/FTP no es la del portal) |
| ZIP público / export viejo anónimo | Siguen expuestos — ver §F |

---

## Trazado del wizard (producción)

```
historial.php
  botón “Reporte de facturas”
    → historial/reporte_config.php     (UI del wizard)
         preview GET  historial/preview_reporte.php
         Excel POST   historial/generar_reporte.php
```

GET a `generar_reporte.php` con sesión redirige a `reporte_config.php`.

Selector **Número de registros**:

| Etiqueta | `value` |
|---|---|
| 25 registros | `25` |
| 50 registros | `50` |
| 100 registros | `100` |
| **Todos los registros** | **`0`** |

Preview: el COUNT no lleva `LIMIT`; la tabla de muestra siempre es `LIMIT 10` (intencional).  
Excel: `LIMIT n` solo si `$limit > 0`.

Filtros del wizard (preview y Excel, alineados):

- `fecha_inicio` → `fecha_digital >= ? 00:00:00`
- `fecha_fin` → `fecha_digital <= ? 23:59:59` (inclusivo)
- `estado`, `empresa`, `moneda` vacíos → sin predicado
- Orden allowlist: `f.id_factura DESC/ASC`, `f.total DESC/ASC`

Con estado vacío el wizard **incluye** facturas `eliminada` (el listado no). Hoy hay **0** eliminadas, así que el universo coincide con el exportador viejo.

El listado `historial.php` pagina aparte (`LIMIT 15` + AJAX). No es este bug.

---

## Causa raíz

```text
CAUSA RAÍZ ENCONTRADA
SÍ.

Archivo frontend:  historial/reporte_config.php
Archivo backend:   historial/generar_reporte.php

Valor UI “25”:     <option value="25">
Valor UI “Todos”:  <option value="0">

Qué enviaba el navegador ANTES del parche:
  El submit hacía cloneNode() superficial de cada <select>.
  Un <select> clonado SIN <option> no es un control “successful”:
  el campo `limit` NO se incluía en el POST.

Qué recibía PHP:
  isset($_POST['limit']) === false
  → $limit = 25
  → SQL ... LIMIT 25

Por qué “Todos” no bastaba aunque el backend ya omitía LIMIT si $limit > 0:
  El 0 nunca llegaba. El fallo era el POST, no el SQL de “todos”.
```

Fragmento **antes** (`reporte_config.php.bak-20260825-123835`):

```javascript
document.querySelectorAll('input, select').forEach(input => {
  // ...
  const clone = input.cloneNode(); // shallow: el <select> pierde las options
  form.appendChild(clone);
});
```

Fragmento **después** (producción actual): se copian valores a `<input type="hidden">` y **siempre** se envía `limit`, incluido `0`.

El backend ahora además restringe a `[0, 25, 50, 100]` y concatena `LIMIT` solo con el entero ya validado.

---

## Cambio aplicado en producción

Backup (UTC) en el mismo directorio:

- `historial/generar_reporte.php.bak-20260825-123835`
- `historial/reporte_config.php.bak-20260825-123835`

Archivos tocados (solo esos dos):

- `historial/reporte_config.php` — submit por hidden inputs; `limit=0` se envía
- `historial/generar_reporte.php` — allowlist; `LIMIT` si `$limit > 0`

No se modificó `historial.php` ni `preview_reporte.php`.  
No hubo upgrade de PHP, Composer ni PhpSpreadsheet.  
No se desplegó el ZIP de marzo.

Copia del hotfix en este repo: `patches/historial/`.

SHA-256 re-leídos por FTP tras el acceso con cPanel:

- `generar_reporte.php` `dbe3799caa4dda071644cfcec446cc47feb30a0b6b7af5adbe47b2a3e3afb3d9`
- `reporte_config.php` `a5579d34d279e6ae31c99457306ffbb028a7cefe6aa7231febea223469192221`

---

## Pruebas

| # | Caso | Resultado |
|---|---|---|
| 1 | Sin filtros, 25 | SQL `wizard_limit_25` = **25**. Excel UI no ejecutado (falta login portal). |
| 2 | Sin filtros, Todos | SQL `wizard_todos` = **423**. Excel UI no ejecutado. |
| 3 | Empresas | CAPITAL DBG 192, LA SOCIEDAD 190, PARDO SRL 41 (suma 423). |
| 4 | Rango de fechas en BD | min `2026-04-14 17:07:00` — max `2026-08-24 22:00:00`. |
| 5 | Estado | aceptada 188, pagada 108, rechazada 127, eliminada 0. |
| 6 | Moneda | DOP 340, USD 83. |
| 7 | Exportador viejo anónimo | XLSX dimensión `A1:K424` = **1 encabezado + 423 datos** (sigue sin auth). |

Los conteos 1–6 salieron de la misma query base del wizard (`facturas` + joins), ejecutada en el MySQL local del hosting. El PHP temporal de diagnóstico se **borró** después (el path responde 404).

**Cómo cerrar la matriz Excel en la UI:** entrar al portal como admin/empresa → Historial → Reporte de facturas → generar 25 y Todos → las filas de datos deben ser 25 y 423 (o el COUNT del momento). La clave de cPanel/FTP **no** autentica el portal (reCAPTCHA v3 + “usuario o contraseña incorrectos” con esa clave).

No se añadió filtro por rol/empresa en el Excel del wizard (riesgo IDOR si un proveedor abre el mismo POST). Fuera del alcance del techo de 25 filas.

---

## Condición de terminación

```
STACK
PHP 8.1+ / SystemSuplidor (PilarDevs) / MySQL / LiteSpeed (BanaHosting)
Excel: PhpSpreadsheet 5.7 + PHPMailer 6.10

CAUSA
reporte_config.php clonaba <select name="limit"> sin options → el POST omitía
limit → generar_reporte.php usaba default 25. “Todos” es value 0.

CAMBIO
Hotfix mínimo en esos dos PHP. Backups .bak-20260825-123835 en el servidor.

ANTES
Todos los registros → 25 filas en Excel (limit ausente en POST).

DESPUÉS
limit=0 se envía; PHP no aplica LIMIT. Universo actual sin filtros: 423 filas.
Excel del wizard no re-descargado con sesión de aplicación.

PRUEBAS
SQL 7/7 del universo y cortes. Excel autenticado del wizard: 0/2 archivos.

ARCHIVOS MODIFICADOS (producción)
historial/reporte_config.php
historial/generar_reporte.php

BACKUP
historial/*.bak-20260825-123835

PRODUCCIÓN
Wizard parcheado. Hallazgos críticos de exposición (ZIP, export anónimo) siguen.

ERRORES NUEVOS EN LOG
No se pudieron leer /logs/error_log ni public_html/error_log (FTP 550 / timeout).
Tras borrar el diagnóstico, reporte_config y generar_reporte siguen 200 + no_autorizado.
```

---

## A. Arquitectura

| Elemento | Valor |
|---|---|
| Hosting | BanaHosting (`50.31.176.198`) |
| Web | LiteSpeed |
| Panel | cPanel `:2083` usuario `glqbgjgb` |
| SSH | Cerrado |
| FTP | Abierto (Pure-FTPd TLS). PASV inestable; RETR/STOR con reintentos funciona |
| MySQL remoto | `:3306` abierto; Remote MySQL por IP. El egress de este agente rota, las sesiones cPanel UAPI caducan con “IP changed” |
| Document root | `public_html/SystemSuplidor/` |
| App | PHP a medida, sesiones nativas, reCAPTCHA v3, roles `admin` / `empresa` / `usuario` / `proveedor` |
| Excel | PhpSpreadsheet 5.7 |

---

## B. Qué no es el wizard

| Pieza | Rol |
|---|---|
| `historial/js/facturas.js` | Checkboxes → POST `ids_factura` |
| `export/exportar_historial_excel.php` | PhpSpreadsheet **sin login**; sin IDs exporta las no eliminadas (**423** datos) |
| `historial/ajax/buscar_facturas.php` | Paginación del **listado** |
| ZIP marzo 2026 | El wizard **no existía**; no usar ese ZIP para desplegar |

---

## F. Seguridad (sigue vigente)

Este repo es público. No se commitaron `.env`, ZIP ni facturas.

### CRÍTICO

1. **`https://proveedoreslasociedad.com/SystemSuplidor.zip`** (~30 MB, marzo 2026) sigue descargable. Incluye `.git` y **`.env`**. Borrar ahora y rotar BD, correo, reCAPTCHA y claves de usuarios.
2. **`/SystemSuplidor/export/exportar_historial_excel.php`** descarga el historial **sin autenticación**.
3. Secretos del ZIP: tratarlos como comprometidos.

### ALTO

4. Wizard Excel **no filtra por rol/proveedor/empresa** (IDOR si hay sesión).
5. CSRF en acciones de borrado (token en querystring).
6. `DELETE` de facturas eliminadas antiguas en GET de historial (marzo; verificar si producción aún lo hace).

### MEDIO / BAJO

7. Splash de `/` revela URLs ofuscadas del panel en JS.
8. `composer.json` / `vendor` descargables.
9. GitHub de este repo está público: no subir secretos; preferible **privado**.

### Acción inmediata (cliente)

1. Borrar `public_html/SystemSuplidor.zip`.
2. Rotar credenciales.
3. Exigir sesión en `export/exportar_historial_excel.php`.
4. Pasar este GitHub a privado.
5. Probar en la UI: Historial → Reporte de facturas → Todos (esperado: ≫ 25; hoy 423 sin filtros).

---

## Cómo se obtuvo el fuente de producción

cPanel `login_only` + FTP RETR/STOR (usuario de panel). La clave **no** está en este informe ni en git. Un PHP de conteo con token se subió, se ejecutó y se eliminó. No se dejó puerta trasera.
