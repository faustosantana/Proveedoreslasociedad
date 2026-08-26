# SECURITY HARDENING FINAL

proveedoreslasociedad.com / SystemSuplidor  
Fecha: 2026-08-25. Sin secretos ni credenciales.

```
SECURITY HARDENING FINAL
proveedoreslasociedad.com

PRODUCCIÓN:
UP

DATOS CONTABLES MODIFICADOS:
NO

BASE DE DATOS DATA MODIFICADA:
NO

FACTURAS ELIMINADAS:
0

PAGOS MODIFICADOS:
0

DOCUMENTOS ELIMINADOS:
0


================================
PASO 1 — DELETE / WRITE SECURITY
================================

Endpoints auditados:
- php/acciones-factura.php (previo PASS)
- php/limpiar_facturas.php (previo PASS)
- historial.php purge embebido
- acciones/eliminar_factura.php (soft delete)
- acciones/eliminar_factura_permanente.php (hard delete + unlink)
- php/restaurar_factura.php
- HMAC acciones/actualizar_estado_factura.php (NO tocado)
- PanelAdmin/eliminar_* y toggle_* (inventario)

Protegidos:
- historial.php: DELETE de 15 días retirado del GET
- eliminar_factura.php: POST + sesión + CSRF + admin/empresa + UPDATE con id_empresa
- eliminar_factura_permanente.php: POST + sesión + CSRF + hard delete scoped (no ejecutado)
- restaurar_factura.php: POST + CSRF + allowlist pendiente|eliminada + scope
- UI historial/js/facturas.js y js/historial.js: POST form + csrf_token

Pendientes:
- acciones/actualizar_estado_factura.php HMAC GET (emails)
- acciones/editar_factura.php
- acciones/validar_proveedor.php (token alta)
- PanelAdmin eliminar_*/toggle_* GET
- php/quitar_empresa_proveedor.php

GET destructivos restantes:
HMAC email + PanelAdmin GET + validar_proveedor. Los GET de factura eliminar/restaurar/limpiar/historial-purge: cerrados.

Justech (proveedor): GET eliminar y permanente → **405** `Método no permitido`. POST sin CSRF eliminar/restaurar → **403** `Token de seguridad inválido.` Sin redirect de éxito. `fact_6a7c79e56fb4c` intacta.

Estado:
PARTIAL (núcleo facturas PASS; PanelAdmin/HMAC pendientes)


================================
PASO 2 — FILES / UPLOADS
================================

MIME:
- php/procesar_formulario.php: finfo + pdf/jpg/png (previo)
- php/registrar_abono.php: parche finfo preparado (despliegue pendiente de FTP)
- php/crear_factura_admin.php: pendiente finfo en vivo

PHP execution:
- /archivos/ listing HTTP 403; deny PHP en uploads (hardening previo)

Traversal:
- descargar_documento.php: realpath + prefijo archivos/

Document controller:
- php/descargar_documento.php sesión + ownership (previo)

Acceso directo:
- /archivos/{file} sigue público (emails/UI histórica). No bloqueado de golpe.

Estado:
PARTIAL


================================
PASO 3 — AUTH / IDOR / CSRF
================================

Proveedor isolation:
PASS (Justech)

Admin:
permisos actuales conservados, no ampliados

Empresa:
scope id_empresa en UPDATE/DELETE de facturas

Sessions:
Secure=sí. HttpOnly/SameSite no centralizados (muchos session_start)

HttpOnly:
NO (pendiente prueba de login)

SameSite:
NO (pendiente; no Strict)

CSRF:
validarTokenCSRF + validarTokenCSRFDeterministico reutilizados en writes de factura

Estado:
PARTIAL


================================
PASO 4 — SERVER / CODE SECURITY
================================

.env:
HTTP 403

.git:
HTTP 403 HEAD

ZIP:
SystemSuplidor.zip HTTP 403 (cuarentena previa)

SQL dumps:
reglas .htaccess deny; no borrar

Backups:
*.bak HTTP 403; no borrar

SQL Injection:
writes de factura usan prepared; inventario de GET concat restante en filtros no masivo

XSS:
historial usa htmlspecialchars en celdas; no barrido total

Headers:
nosniff / SAMEORIGIN / referrer (hardening previo). Sin CSP estricta. Sin HSTS forzado.

Legacy/: KEEP público splash
VCARD/: KEEP

Estado:
PARTIAL


================================
PASO 5 — SECRETS
================================

cPanel:
READY TO ROTATE (FTP usado en esta intervención). No rotado aquí.

DB:
PENDING (ZIP histórico). Consumidores: app .env, posible Remote MySQL. No rotar sin plan coordinado.

SMTP:
PENDING (configMail.php + .env). Fallback hardcoded: quitar al rotar.

reCAPTCHA:
PENDING coordinado con login.

HMAC:
PENDING CURRENT/PREVIOUS + TTL. No rotar (rompería mails).

No mostrar valores.


================================
REGRESIÓN FINAL
================================

Login: no re-probado en este cierre (reCAPTCHA)
Dashboard: no en este cierre
Historial: anónimo → página "Acceso no autorizado" (HTTP 200 plantilla); sin tabla
Detalle: aislamiento previo PASS
Reporte 25 / Todos / Excel / Spinner: hotfix previo PASS; aislamiento Justech PASS
Pagos lectura: no write
Proveedor isolation: PASS
Documentos: listing 403; directo /archivos/ aún 200 si se conoce el nombre
Logout: no re-probado


================================
BACKUPS
================================

historial.php | aaa80c2f… | historial.php.bak-20260825-192944 | 403 | e6deb831…
historial/js/facturas.js | 288292c0… | facturas.js.bak-20260825-193811 | 403 | 8def076a…
js/historial.js | (prev) | backup en ciclo deploy | | a461b729…
acciones/eliminar_factura.php | c8dc9614… | backup ciclo deploy | | fd5c4269…
acciones/eliminar_factura_permanente.php | a16d8409… | backup ciclo deploy | | 1e252173…
php/limpiar_facturas.php | 54fa05d1… | .bak-20260825-184750 | 403 | 3f582a84…
php/acciones-factura.php | 090c02f8… | .bak-20260825-180631 | 403 | 9e9d6bc4…

Rollback: FTP STOR del .bak correspondiente sobre el vivo.


================================
HALLAZGOS PENDIENTES
================================

CRITICAL:
(ningún GET anónimo que borre facturas en los endpoints parcheados)

HIGH:
- HMAC GET sin TTL (emails)
- PanelAdmin eliminar_*/toggle por GET
- URLs directas /archivos/ (emails)
- editar_factura.php sesión débil

MEDIUM:
- Cookies sin HttpOnly/SameSite
- crear_factura_admin.php MIME $_FILES['type']
- session_regenerate_id ausente en login (snapshot)

LOW:
- historial anónimo HTTP 200 (plantilla no autorizado, no 401)
- CSRF token en onclick HTML (determinístico)


================================
ESTADO GENERAL
================================

PARTIAL
```

No se declara PASS global: HMAC GET, PanelAdmin GET, documentos directos y cookies siguen abiertos. Producción UP. Cero writes contables de prueba.
