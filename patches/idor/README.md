# FASE 3 — IDOR de proveedores (producción 2026-08-25)

**Datos contables: no modificados. Base de datos: no modificada.**
Sin valores de secretos.

`php/acciones-factura.php` y `php/limpiar_facturas.php` parcheados en producción. HMAC `acciones/actualizar_estado_factura.php` no tocado.

## Modelo de autorización (real)

```
Login PanelAdmin/procesar-login.php
  tablas: administradores | empresas | proveedores | usuarios
  password_verify
    ↓
  $_SESSION['usuario']
  $_SESSION['tipo']          admin | empresa | proveedor | usuario
    proveedor → $_SESSION['id_proveedor'] = proveedores.id
    empresa   → $_SESSION['id_empresa']
    admin     → $_SESSION['id']
    usuario   → $_SESSION['id_usuario']  (+ empresa_activa al seleccionar)
    ↓
  facturas.id_proveedor = sesión (proveedor)
  facturas.id_empresa   = sesión (empresa/usuario)
  admin: sin predicado extra
```

`historial.php` ya aplicaba ese scope. El wizard no.

Helper nuevo: `php/autorizacion_facturas.php`
(`ss_require_tipo_sesion`, `ss_aplicar_scope_facturas`, `ss_puede_ver_factura`).
El ID de proveedor **nunca** se toma de GET/POST.

## HMAC (no rotado)

- Genera: `correo/correo_nueva_factura.php` (`hash_hmac sha256 id|estado`)
- Valida: `acciones/actualizar_estado_factura.php`
- Emails: aceptar/rechazar factura
- TTL: **ninguno** (válido indefinidamente)
- Token de correo actúa con privilegio admin
- Rotación CURRENT/PREVIOUS: pendiente (no implementada)

## Cookies (no cambiadas)

`Secure` sí. `HttpOnly`/`SameSite` no. `session_start()` en ~80 archivos antes de config. Sin prueba de login no se centralizó.

## GET que escriben (inventario)

| ENDPOINT | MÉTODO | AUTH | AUTORIZACIÓN | CSRF | HMAC | CAMBIA DATOS | RIESGO | PRIORIDAD |
|---|---|---|---|---|---|---|---|---|
| php/acciones-factura.php | GET LEGACY | sesión | admin/empresa + ownership en UPDATE | NO | NO | SÍ estado | — | **PARCHEADO** |
| php/limpiar_facturas.php | POST | sesión | **admin** + CSRF | SÍ | NO | SÍ DELETE masivo (≥15d eliminada) | — | **PARCHEADO** |
| historial.php (purge embebido) | GET página | sesión | cualquiera autenticado | NO | NO | SÍ DELETE masivo (≥15d) | CRITICAL | P0 restante (no este PR) |
| acciones/eliminar_factura.php | GET | sesión | admin/empresa (UPDATE sin scope en WHERE) | NO | NO | SÍ estado | HIGH | P1 (UI historial) |
| acciones/eliminar_factura_permanente.php | GET | sesión | admin/empresa | NO | NO | SÍ delete + archivos | HIGH | P1 |
| acciones/actualizar_estado_factura.php | GET | sesión o token | empresa dueña / token=admin | NO | SÍ (sin TTL) | SÍ estado | HIGH | P1 emails — **no tocar aún** |
| php/restaurar_factura.php | POST | sesión | admin/empresa (UPDATE sin scope en WHERE) | ? | NO | SÍ estado | HIGH | P2 |
| acciones/editar_factura.php | GET+POST | sesión débil | incompleta | NO | NO | SÍ | HIGH | P2 |
| acciones/validar_proveedor.php | GET | token | token de alta | NO | NO | SÍ proveedores | HIGH | P2 (emails alta) |
| PanelAdmin/acciones/toggle_empresa.php | GET | admin | rol admin | NO | NO | SÍ activo | MEDIUM | P3 |
| PanelAdmin/acciones/toggle_usuario.php | GET | admin | rol admin | NO | NO | SÍ activo | MEDIUM | P3 |
| PanelAdmin/eliminar_{empresa,usuario,proveedor,admin}.php | GET | panel | variable | NO | NO | SÍ delete | HIGH | P3 |
| php/procesar_formulario.php | POST | sesión proveedor | id_proveedor sesión | SÍ | NO | SÍ alta | — | finfo hecho |
| php/registrar_abono.php | POST | sesión | CSRF sí; ownership pendiente | SÍ | NO | SÍ pago | MEDIUM | finfo siguiente |
| php/crear_factura_admin.php | POST | admin | rol admin | ? | NO | SÍ alta | MEDIUM | finfo siguiente |

Copia del parche: `patches/idor/acciones-factura.php`, `patches/idor/limpiar_facturas.php`.

No se parchearon los demás GET de escritura en esta subfase. No MIME/`finfo` todavía.

## Siguiente (sin parche masivo)

1. `historial.php` purge embebido (mismo DELETE de 15 días; corre al ver el listado)
2. `acciones/eliminar_factura.php`
3. `acciones/eliminar_factura_permanente.php`
4. `php/restaurar_factura.php`
5. `crear_factura_admin.php` + `registrar_abono.php` con `finfo`
6. Cookies HttpOnly/SameSite
7. Documentos privados (no bloquear `/archivos/` todavía)
8. CSRF en GET restantes de la UI
9. Preparar rotación de secretos HMAC — **no rotar todavía**

## Documentos

UI e emails siguen usando URL directa `archivos/...`. Controlador nuevo `php/descargar_documento.php` (sesión + ownership + realpath). **Acceso directo a /archivos/ no se bloqueó** (rompería mails y dropdown).

## Legacy / VCARD

- `/Legacy/` splash de otra app — KEEP/UNKNOWN, no tocado
- `/VCARD/` generador QR/vCard — KEEP, no es SystemSuplidor

## Secretos

No rotados. Ver informe de entrega. `configMail.php` tiene fallback si falta env (rotar y quitar fallback).
