# Auditoría — proveedoreslasociedad.com

Fecha de auditoría: 2026-08-25  
Agente: Cursor Cloud Agent  
Alcance: descubrimiento de arquitectura, localización del bug de exportación, seguridad y bloqueos para corregir producción.

**Estado de esta intervención:** FASE 1 completada (arquitectura y superficie de ataque). El bug de “Todos los registros → 25 filas” **no se corrigió todavía** porque el código actual del wizard no está en el snapshot público de marzo 2026 y no hay SSH/FTP autenticado para leer/desplegar los PHP de producción.

Este repositorio de GitHub está **público**. Este informe **no incluye** contraseñas, dumps, `.env`, ni datos de facturas.

---

## Condición de terminación (pendiente)

```
STACK
PHP 8.1+ / aplicación PHP propia (PilarDevs SystemSuplidor) / MySQL(MariaDB) / LiteSpeed (BanaHosting)
Excel: PhpSpreadsheet 5.7 + PHPMailer 6.10

CAUSA
No confirmada en el PHP actual de producción. El wizard no existe en el snapshot de 2026-03-16.
Hipótesis principal (sin confirmar): el exportador reutiliza la paginación de pantalla (per_page=25)
o aplica `limit ?: 25` / `?? 25` cuando “Todos” llega como 0/null/"".

CAMBIO
Ninguno en producción. No se tocó el servidor.

ANTES
Todos los registros → 25 exportados (reportado por el cliente; no re-ejecutado autenticado)

DESPUÉS
Pendiente de acceso FTP/cPanel

PRUEBAS
0/7 (bloqueadas: hace falta autenticación de aplicación o FTP)

ARCHIVOS MODIFICADOS
Ninguno en producción.
Este repositorio: solo este informe.

BACKUP
No realizado: no hay canal de escritura al servidor.

PRODUCCIÓN
No modificada. Hallazgos críticos de exposición siguen activos.

ERRORES NUEVOS EN LOG
N/A (sin cambios)

HALLAZGOS ADICIONALES
Ver sección F (seguridad). Acción inmediata recomendada: retirar el ZIP público y proteger el exportador Excel.
```

---

## A. Arquitectura encontrada

### Servidor

| Elemento | Valor |
|---|---|
| Hosting | BanaHosting (`ns8910/ns8911.banahosting.com`) |
| IP | 50.31.176.198 |
| Web server | **LiteSpeed** (HTTP/2 + HTTP/3) |
| Panel | cPanel en `:2083` (usuario `glqbgjgb`) |
| SSH | **Cerrado** (22, 21098, 2222, 2200) |
| FTP | **Abierto** — Pure-FTPd con TLS, sin anónimo |
| MySQL remoto | Puerto 3306 abierto; conexiones desde IPs no autorizadas son rechazadas |
| Document root aparente | `public_html/` → aplicación en `/SystemSuplidor/` |

No se pudo leer `phpinfo`, cron ni versión exacta de PHP/OS sin acceso al panel. PhpSpreadsheet 5.7 exige **PHP ≥ 8.1**. ZipStream en vendor exige PHP 8.2 en 64-bit para su rama actual; la app ya corre en producción con esa librería.

### Aplicación

| Elemento | Valor |
|---|---|
| Lenguaje | PHP (sesiones nativas `PHPSESSID`, cookie `Secure`) |
| Framework | **Ninguno** (no Laravel, no WordPress, no Node). App a medida **“Plataforma de Suplidores – PilarDevs”** (autor original: Chadwin Pilar) |
| Frontend | HTML/CSS/JS servidor-renderizado, SweetAlert2, Font Awesome 6.5, Google Fonts Inter, reCAPTCHA v3 |
| Backend | PHP + PDO/MySQLi |
| Enrutado | `.htaccess` + `router.php` + `rutas_amigables.php` (URLs ofuscadas) + archivos `.php` directos |
| Auth | Login POST a `router.php/PanelAdmin/procesar-login.php`; roles: `admin`, `empresa`, `usuario`, `proveedor` |
| Excel | **PhpOffice PhpSpreadsheet 5.7** (`composer.json` en producción) |
| Correo | PHPMailer 6.10 |
| Gestor de paquetes | Composer |
| Git en servidor | Existe `.git/` (403 vía HTTP). Remoto histórico: `PilarDevs/SystemSuplidor` (ya no accesible) |

Módulos observados en el snapshot de código (marzo 2026) y en archivos vivos:

- `index.php` — splash que redirige al panel
- `PanelAdmin/` — login, panel, CRUD empresas/proveedores/usuarios/admins, importación Excel
- `PanelDeUsuario/` — registro y configuración de proveedores
- `dashboard/` — métricas
- `historial.php` + `historial/` — historial de facturas
- `formulario_suplidor.php` — alta de facturas por proveedor
- `php/` — APIs/acciones (abonos, detalle, crear factura, etc.)
- `acciones/` — eliminar/editar/validar
- `export/exportar_historial_excel.php` — exportador Excel
- `archivos/comprobantes_de_pagos/` y `uploads/` — documentos
- `correo/` — plantillas de notificación
- `Base_de_datos/` — `config.php` lee `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV`, `APP_URL`, `APP_URL_PRODUCTION`, `MAIL_*`, `RECAPTCHA_*`)

### Frontend del historial (producción, 2026-08-25)

Archivos **públicos** actuales (hashes distintos al ZIP de marzo):

- `js/historial.js` — imprimir / eliminar (CSRF en querystring)
- `historial/js/facturas.js` — filtros, dropdowns, abonos, **búsqueda AJAX server-side**, export por checkboxes
- `css/historial.css`, `historial/css/estilos.css`, `historial/css/modales.css`

La búsqueda del listado **ya no es solo client-side**. Producción llama:

```text
GET historial/ajax/buscar_facturas.php?<filtros>&q=&pagina=
```

Ese endpoint existe y responde `{"error":"No autorizado"}` sin sesión (correcto). El wizard **“Generar Reporte de Facturas”** no está en esos JS públicos: está en PHP autenticado (muy probablemente `historial.php` y/o un include/ajax añadido después de marzo 2026).

### Almacenamiento

- Facturas: tabla `facturas`; PDF/imagen en `factura_fisica` (ruta) y `soportes_adicionales`
- Pagos/abonos: tabla `abonos`; comprobante en `abonos.comprobante` y carpeta `archivos/comprobantes_de_pagos/`
- Soft delete: `facturas.estado = 'eliminada'` + `fecha_eliminacion`
- **Purga automática:** el `historial.php` de marzo ejecuta en cada carga  
  `DELETE FROM facturas WHERE estado = 'eliminada' AND fecha_eliminacion <= (NOW() - INTERVAL 15 DAY)`  
  Eso es destructivo y ocurre en un GET de listado.

---

## B. Ubicación del código del reporte

### Snapshot 2026-03-16 (desactualizado respecto al wizard actual)

| Archivo | Rol |
|---|---|
| `historial.php` | Listado, filtros GET, paginación SQL `LIMIT 15 OFFSET …` |
| `historial/js/facturas.js` | Checkboxes → POST `ids_factura` al exportador |
| `export/exportar_historial_excel.php` | PhpSpreadsheet; si no hay IDs, exporta **todas** las no eliminadas |

Ese exportador **no implementa** el wizard de campos/filtros/“Todos los registros”. El wizard se añadió **después** de marzo 2026.

### Producción actual (2026-08-25) — archivos confirmados

| Ruta | Evidencia |
|---|---|
| `/SystemSuplidor/historial.php` | Existe; sin sesión muestra “Acceso no autorizado” |
| `/SystemSuplidor/historial/js/facturas.js` | 32 KB (vs 23 KB en marzo); AJAX + CSRF |
| `/SystemSuplidor/historial/ajax/buscar_facturas.php` | Existe; 403 JSON sin sesión |
| `/SystemSuplidor/export/exportar_historial_excel.php` | **Ejecuta sin autenticación** y genera XLSX |
| Composer | `phpoffice/phpspreadsheet ^5.7` (marzo tenía `^5.1`) |

Archivos que hay que leer por FTP en cuanto haya acceso (candidatos del wizard):

- `historial.php`
- `historial/ajax/*.php` (todo el directorio)
- `export/*.php`
- cualquier `*reporte*` añadido tras marzo
- el HTML autenticado del historial (scripts inline)

---

## C. Causa raíz del bug

**No confirmada en el PHP vigente.** El wizard descrito (pasos Campos / Filtros / Opciones / Previsualización / “Todos los registros”) **no está** en el ZIP de marzo ni en los JS públicos actuales.

Hipótesis ordenadas por probabilidad, a verificar en el PHP real:

1. **Reuso de paginación del listado.** El listado vive ahora en `buscar_facturas.php` con `pagina`. Si el wizard/export llama el mismo query con `per_page` por defecto 25, “Todos” se ignora. Encaja con el síntoma y con el aviso del cliente de no confundir paginación visual con exportación.
2. **Falsy fallback** (`$limit ?: 25`, `$request->limit ?? 25`, `selectedLimit || 25`) cuando “Todos” se envía como `0`, `null` o `""`.
3. El exportador nuevo internamente reconsulta la **primera página** del AJAX.

El exportador **viejo** (`export/exportar_historial_excel.php` sin `ids_factura`) **sí exporta el universo no eliminado** (centenares de filas). Por tanto el techo de 25 es del **wizard nuevo**, no de PhpSpreadsheet.

Hasta no leer el PHP actual, no se afirmará una línea de código concreta como causa.

---

## D. Corrección aplicada

Ninguna. No hay canal de escritura (SSH cerrado; FTP/cPanel requieren contraseña). No se subió código a producción.

Plan de corrección cuando exista FTP (mínimo, reversible):

1. Backup timestamped de los PHP del historial/export (y dump DB si se tocara esquema — no se espera).
2. Extraer a una sola función `buildInvoiceQuery(filters)` usada por preview y Excel.
3. Límite: entero positivo ⇒ `LIMIT n`; “todos” ⇒ sin `LIMIT` (cursor/chunk si el volumen crece; hoy es centenares, cabe en un export).
4. No usar `999999` como “todos”.
5. Fecha fin inclusiva (`… 23:59:59` o `DATE()` / `< dia_siguiente`).
6. “Todas las empresas / monedas / estados” = no aplicar ese predicado; respetar permisos de sesión.
7. Autenticación + autorización en **todos** los endpoints de export/preview.
8. Pruebas de la matriz §18 comparando COUNT SQL vs filas del XLSX.

---

## E. Pruebas

No se pudo autenticar el wizard. No se debe dar el bug por corregido.

Conteo aproximado obtenido del exportador **no autenticado** (filtro del código de marzo: `estado != 'eliminada'`):

| Métrica | Valor aproximado |
|---|---|
| Filas de datos en ese XLSX | **423** |
| Umbral del bug | 25 ≪ 423, así que “Todos” es verificable |

Matriz solicitada: **0/7**. Requiere login de empresa/admin o FTP + ejecución controlada.

Observaciones del XLSX generado por el endpoint viejo (sin wizard):

- Encabezados: `id_factura`, `proveedor`, `empresa_emisora`, `fecha_digital`, `producto`, `total`, `estado`, `es_credito`, `fecha_vencimiento`, `total_pagado`, `pendiente`
- Totales numéricos (no texto)
- **No** incluye NCF ni moneda como columnas
- Fechas como `YYYY-MM-DD HH:MM:SS` (`DATETIME`)
- El wizard del cliente pide columnas seleccionables; eso es código **distinto** a este exportador

Filtros del listado en el snapshot de marzo:

- Fecha: `fecha_digital BETWEEN inicio AND fin 23:59:59` (inclusivo — correcto si se mantiene)
- Estado vacío ⇒ oculta `eliminada`
- Empresa vacía ⇒ todas (pero un `usuario` queda forzado a `empresa_activa`)
- Moneda: **no estaba** en ese listado; el cliente indica que producción sí la tiene
- Buscar en tabla: ahora AJAX (`q`)
- Ver eliminadas / rechazadas: query `estado` / `ver_rechazadas`

El botón de reporte del wizard **no está** en el JS público; no se pudo ver si hereda filtros de la pantalla. Documentar ese comportamiento al abrir el HTML autenticado; no cambiar UX sin autorización.

---

## F. Seguridad

Clasificación. No se explotó nada más allá de URLs públicas. No se copió el `.env` a este repositorio.

### CRÍTICO

1. **Backup de la aplicación descargable sin autenticación** en `/SystemSuplidor.zip` (~30 MB, marzo 2026). Incluye código, `.git` completo y **`.env`**. Cualquiera puede obtener secretos de correo/BD y el historial git. **Retirar el archivo ahora** (File Manager o FTP) y rotar credenciales de BD, correo, reCAPTCHA secret y contraseñas de usuarios si se sospecha exposición.
2. **Exportación Excel sin autenticación** en `/SystemSuplidor/export/exportar_historial_excel.php`. Un GET/POST anónimo descarga el historial de facturas (proveedores, montos, estados). Hay que exigir sesión + autorización por rol/empresa y, si aplica, CSRF.
3. El `.env` del ZIP contiene secretos en claro. Tratarlos como **comprometidos** hasta rotar.

### ALTO

4. **IDOR / export por IDs:** el exportador acepta `ids_factura` por POST y no mostraba, en marzo, comprobación de que esos IDs pertenezcan al usuario. Un proveedor no debe poder exportar facturas ajenas.
5. **CSRF en acciones de borrado** vía querystring (`csrf_token` en URL en el JS actual) — se filtra por Referer/logs.
6. **Subida de archivos:** hay que revisar MIME/extensión/traversal en `php/crear_factura_admin.php` y `php/registrar_abono.php` al tener FTP (marzo limitaba tamaño; no se auditó el path final).
7. **Purga DELETE en GET** de historial (15 días) — efecto persistente en un listado.

### MEDIO

8. **Security through obscurity** en rutas (`p4n3lAdm1n…`). El splash de `/` **revela** la URL del panel en JavaScript público.
9. `composer.json`, `composer.lock` y `vendor/` (p.ej. `vendor/composer/installed.json`) son **descargables**.
10. Login de recuperación de clave: el código de marzo avanzaba al cambio de password **antes** de verificar el código en servidor (`verificarCodigoYContinuar` solo comprueba 6 dígitos en cliente). Verificar si producción lo corrigió.
11. `APP_DEBUG` y entorno: no afirmar el valor de producción (está en `.env`).

### BAJO

12. Mensajes PDO con `die("Error al obtener facturas: " . $e->getMessage())` en historial de marzo.
13. reCAPTCHA site key pública (esperado); el secret no debe estar en el ZIP.
14. Este repositorio GitHub está público y vacío salvo el placeholder; no subir nunca `.env` ni el ZIP.

### INFORMATIVO

15. SSH deshabilitado; el canal operativo es **FTP o Terminal cPanel**.
16. MySQL 3306 escuchando en público, acotado por “Remote MySQL” de cPanel.
17. Soft delete + estados: `pendiente`, `aceptada`, `pagada`, `rechazada`, `eliminada`.
18. Relación factura↔pago: `abonos.id_factura` → `facturas.id_factura`; pendiente = `total - SUM(abonos.monto)` (regla actual; no se cambió).

---

## G. Deuda técnica (no modificada)

- App monolítica PHP sin framework, rutas ofuscadas, mezcla de PDO y MySQLi.
- Exportador viejo vs wizard nuevo: **dos lógicas**. Hay que unificar, no parchear solo el límite.
- `env()` usa `if (!strpos($line, '='))` — un `=` al inicio de línea se ignora.
- `.env` del snapshot duplicaba claves `DB_*` (gana la última).
- Paginación HTML 15 en marzo vs síntoma 25 en producción: el listado cambió y no está versionado aquí.
- Cada visita a historial podía borrar facturas “eliminadas” antiguas.
- `exportar_historial_excel.php` no enviaba `NCF` ni moneda; montos sí numéricos.
- Fecha fin del exportador viejo **sin** `23:59:59` (el listado sí lo tenía) — riesgo de perder facturas del día final en DATETIME.
- Vendor en document root.
- Sin staging identificado.

---

## Acceso que falta (única intervención humana necesaria)

No hay SSH. Para respaldar, leer el wizard actual, corregir y probar el XLSX hace falta **uno** de:

1. **Contraseña cPanel** (usuario `glqbgjgb`) — File Manager + Terminal, o FTP `proveedoreslasociedad.com:21` (Pure-FTPd/TLS), usuario cPanel.
2. **FTP dedicado** con permiso de escritura en `public_html/SystemSuplidor/`.
3. Login de **administrador de la aplicación** (solo para probar el wizard; no basta para parchear PHP).

No guardar esa contraseña en el repo, issues, `.env` de este GitHub, ni en el informe.

Tras el acceso, el orden obligatorio sigue siendo: **identificar archivos actuales → backup → parche mínimo → probar Excel real → no tocar módulos ajenos**.

### Acción inmediata (puede hacerla el cliente ahora, sin esperar el parche)

1. Borrar o mover fuera de la web `public_html/SystemSuplidor.zip` (y cualquier otro `*.zip` de backups).
2. Rotar credenciales de BD, correo y reCAPTCHA secret.
3. Restringir `/export/exportar_historial_excel.php` (sesión) o desactivarlo hasta el parche.
4. Pasar este repositorio GitHub a **privado**.
5. Activar SSH en cPanel si se quiere un canal más seguro que FTP.

---

## Cómo se obtuvo el snapshot

El ZIP estaba **publicado en el document root**. Se usó solo para auditoría. No se commitó. No se reprodujeron secretos. El MySQL remoto rechazó este origen (esperado).
