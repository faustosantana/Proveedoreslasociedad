# Security hardening — proveedoreslasociedad.com

Fecha: 2026-08-25  
Producción: UP. **Datos contables: NO modificados. Base de datos: NO modificada.**

Este informe no incluye valores de secretos, dumps ni facturas.

---

## CRITICAL — mitigado

### 1. `SystemSuplidor.zip` público

| | |
|---|---|
| URL | `https://proveedoreslasociedad.com/SystemSuplidor.zip` |
| Ruta | `/public_html/SystemSuplidor.zip` |
| Tamaño | 30440720 bytes (~29.0 MiB) |
| Fecha HTTP | Mon, 16 Mar 2026 01:39:53 GMT |
| SHA256 | `a9680372862c1f074bb365a883ba178f79a081550776f023fe7807fa060d5e97` |
| Antes HTTP | **200** `application/zip` |
| Después HTTP | **403** (archivo movido + regla `.zip`) |
| Backup | `/security_quarantine/SystemSuplidor-20260316.zip` (fuera de `public_html`) |

Contenido del ZIP (sin valores):

```
.env presente: Sí (SystemSuplidor/.env)
.git presente: Sí
DB_HOST: presente
DB_NAME: presente
DB_USER: presente
DB_PASSWORD: presente (clave DB_PASS)
SMTP_HOST: presente (MAIL_HOST)
SMTP_USER: presente (MAIL_USER)
SMTP_PASSWORD: presente (MAIL_PASS)
API keys: reCAPTCHA site + secret
otros: APP_ENV, APP_DEBUG, APP_URL, APP_URL_PRODUCTION, MAIL_FROM, MAIL_FROM_NAME, MAIL_PORT
SQL dumps: 6 archivos .sql en Base_de_datos/asset/
```

### 2. Exportador Excel anónimo

`/SystemSuplidor/export/exportar_historial_excel.php`

| | |
|---|---|
| Antes | GET anónimo **200** XLSX (~423 facturas), `Content-Disposition: attachment` |
| Después | GET anónimo **401** HTML “Acceso No Autorizado” (mismo guard que `historial.php` + scope por rol) |

```
ARCHIVO: export/exportar_historial_excel.php
SHA ANTES: dd6d45a175e25fecab845ef0c58a234b2f909fecad9588d4f2dbb0655615558a
BACKUP: export/exportar_historial_excel.php.bak-20260825-143200
SHA DESPUÉS: adfd705902b3b4112a03c296c1ac52b6e48932af16a407981986ad74c394cde4
ROLLBACK: restaurar el .bak por FTP
```

### 3. Dumps SQL públicos

`/SystemSuplidor/Base_de_datos/asset/*.sql` respondían **200**. Ahora **403** vía `.htaccess`. Archivos **no** borrados.

### 4. Copias obsoletas de la app en document root

`/SystemSuplidor -- legacy/` y `/SystemSuplidor -- obsoleto/` servían el splash de la plataforma. Se añadió `Require all denied`. HTTP final **403**. No se borraron directorios.

---

## HIGH — documentado / parcialmente mitigado

| Hallazgo | Acción |
|---|---|
| `/Legacy/` y `/VCARD/` siguen públicos (otras apps) | No se bloqueó: podrían estar en uso. Pendiente de confirmación. |
| Wizard `generar_reporte.php` autenticado **sin** filtro empresa/proveedor | No se tocó (evitar cambiar el Excel de 423 del admin). IDOR autenticado pendiente. |
| `php/get_factura_detalle.php` IDOR autenticado | Parche de autorización por rol (mismo criterio que historial). Anónimo: 401 JSON. |
| `acciones/actualizar_estado_factura.php` HMAC con secreto fijo en código + token en GET cambia estado | No se cambió (rompería enlaces de correo). Rotar secreto en ventana controlada. |
| `registrar_abono.php` sin comprobar `$_SESSION['tipo']` | Se exige sesión. CSRF ya existía en producción. |
| Secretos del ZIP (DB, SMTP, reCAPTCHA, cPanel/FTP de esta intervención) | **Rotar** (no se rotaron aquí). |
| `.env` en disco de producción (`SystemSuplidor/.env`, 3578 bytes) | HTTP ya 403; sigue en el servidor. |

```
ARCHIVO: php/get_factura_detalle.php
SHA ANTES: 7f0cde918e86d209efb49bd6a66fad756d29431679680712af60667f09e2476a
BACKUP: php/get_factura_detalle.php.bak-20260825-143200
SHA DESPUÉS: 6e618f61012f17066197b6aff6aa10ea35aa82233d111c5f84e73e6d2ac81415
ROLLBACK: restaurar el .bak

ARCHIVO: php/registrar_abono.php
SHA ANTES: d3531990541be92b45d475b0f86b35d4b70746cd9ebb7d6a5a3bd0747067c475
BACKUP: php/registrar_abono.php.bak-20260825-143200
SHA DESPUÉS: 3dff0668b7bed38c2cdfd627ead9d2d37fb43129949e39a06f16308052180aec
ROLLBACK: restaurar el .bak
```

---

## MEDIUM

| Hallazgo | Acción |
|---|---|
| Directory listing | Carpetas sensibles ahora 403. `Options -Indexes` en `.htaccess`. |
| `composer.json` / lock / `installed.json` | 403 |
| Cookies de sesión: `Secure` sí; **HttpOnly / SameSite no visibles** | No se cambió `session_set_cookie_params` (la mayoría de PHP hace `session_start()` antes de `config.php`). |
| CSRF mixto | `registrar_abono.php` producción **PROTEGIDO**. Muchas otras escrituras (eliminar/estado por GET) **NO PROTEGIDAS**. No se implantó CSRF global. |
| SQL: la mayoría PDO prepared; `LIMIT` del listado usa enteros | Sin reescritura global. |
| Uploads en `archivos/` (~800 archivos) con URL predecible `archivos/<uniqid>.ext` | No se movió almacenamiento. Se denegó ejecución `.php` en `archivos/` y `archivos/comprobantes_de_pagos/`. MIME confía en `$_FILES['type']` (spoofable). Extensión no allowlisteada de forma estricta en crear factura. |
| Passwords: `password_hash` / `password_verify` (bcrypt) | Sin hashes legacy detectados en código. Sin rate-limit de login (reCAPTCHA v3 sí). |

```
ARCHIVO: public_html/.htaccess
SHA ANTES: 73096ea1904e3dd36ec3f5e6b159e8f925bd7da745f67364b3bb25fece18f437
BACKUP: public_html/.htaccess.bak-20260825-143200
SHA DESPUÉS: 459a6bbdc7bdfcf7da88ba2ac312c98cfd0041b2fa82f3ff0572680d3928a146
ROLLBACK: restaurar el .bak (conserva el handler ea-php82)

ARCHIVO: SystemSuplidor/.htaccess
SHA ANTES: ba7fb6d93695be6d51a5d4bb578d5bd90c0d4c2280417d86c0e12770a9d84fe5
BACKUP: SystemSuplidor/.htaccess.bak-20260825-143200
SHA DESPUÉS: cfc79d189c1b54f52f05ccd429612dd43fafc35d189e19b10fd8b1e472a1273a
ROLLBACK: restaurar el .bak
```

---

## LOW

| Hallazgo | Acción |
|---|---|
| Headers | Añadidos `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. **Sin CSP ni HSTS** (riesgo de romper embeds/HTTP). |
| `vendor/autoload.php` ejecutable | Informativo. |
| XSS: salidas mixtas con/sin `htmlspecialchars` | Sin parche masivo. |

---

## IDOR / autorización (lectura)

| Recurso | AUTHENTICATION | AUTHORIZATION | RESULTADO |
|---|---|---|---|
| Exportador viejo | Ahora sí | Scope rol como historial | Anónimo 401 |
| Wizard Excel | Sí | **No** (admin y resto ven el mismo universo) | Pendiente |
| `get_factura_detalle.php` | Sí | Ahora por proveedor/empresa/usuario; admin OK | Anónimo 401 |
| Pagos (abonos) via detalle | vía factura | igual que factura | |
| Uploads `archivos/*` | **No** (URL directa) | No | No se movieron; PHP denegado |
| Proveedores/empresas CRUD | sesión en paneles | IDs GET; no se explotó en escritura | Documentado |

---

## Secrets — rotación (sin valores)

| Secreto | Expuesto | Riesgo | Rotar | Impacto |
|---|---|---|---|---|
| DB user/password | Sí (ZIP `.env`) | Crítico | Sí | Reconectar app; usuarios DB |
| SMTP MAIL_* | Sí | Alto (envío de correo) | Sí | Reconfigurar `.env` / panel |
| reCAPTCHA secret | Sí | Alto (bypass de bot) | Sí | Actualizar claves Google + `.env` |
| cPanel/FTP | Usada en esta intervención | Crítico | **Sí** | Actualizar FTP/panel; no se cambió aquí |
| HMAC `secret_key_2026_factura_token` | En código PHP | Alto | Sí, con recut de mails | Enlaces de correo de estado |
| GitHub de este repo | Público; sin `.env` en history de este repo | Medio | Hacer **privado** | — |

No hay `SECRET IN GIT HISTORY` en este repositorio (solo placeholder + auditorías + parches). El ZIP público **sí** contenía `.git` de SystemSuplidor.

---

## Rollback

FTP restaurar `*.bak-20260825-143200` sobre el archivo vivo.  
ZIP: mover `/security_quarantine/SystemSuplidor-20260316.zip` de vuelta a `public_html/` (no recomendado).  
Quitar `.htaccess` deny-all de las copias obsoletas si hace falta servirlas otra vez.

---

## Regresión HTTP (sin login de aplicación)

| Flujo | Resultado |
|---|---|
| Login (página) | PASS — 200, formulario visible |
| Historial anónimo | PASS — plantilla no autorizado |
| Wizard `reporte_config.php` anónimo | PASS — no autorizado |
| Exportador anónimo | PASS — ya no entrega Excel |
| SQL dumps | PASS — 403 |
| ZIP | PASS — 403 |
| VCARD / Legacy | Siguen 200 (no tocados) |
| Login completo / Excel 25 / Todos / pagos autenticados | **No ejecutado** (no hay credencial de portal) |

`historial/generar_reporte.php` **no** se modificó en este hardening (límite 423 intacto a nivel de código).
