# Entrega manual — PanelAdmin modal UI (POST + CSRF)

No usar FTP. No hay merge. Subir **solo** estos 3 archivos, uno a uno, desde cPanel File Manager.

Carpeta local: `manual_deploy_paneladmin/`

Ruta remota:

`public_html/SystemSuplidor/PanelAdmin/modal/`

Los PHP de delete/toggle **ya están en producción** (POST + CSRF). Estos 3 archivos solo cambian la UI para que deje de usar GET.

SHA del vivo = último RETR verificado (2026-08-25). No se volvió a descargar.

---

## ARCHIVO: modalMisProveedores.php

SHA DEL VIVO ACTUAL: `63101c9f7045edd2b103797fed3df5fb231c29f6f80023be09d183caee14d895` (15034 bytes)

SHA DEL ARCHIVO LOCAL PREPARADO: `e6133086d5c8afb312ec4d754d373ccf950b4468f2d94b4fd0e70111e9f46e25` (15190 bytes)

CAMBIOS EXACTOS: el enlace GET `eliminar_proveedor.php?id=…&csrf_token=…` se sustituye por un `<form method="post">` al mismo script, con `id` y `csrf_token` hidden. Mismo `confirm('¿Eliminar este proveedor?')`, misma clase `action-btn danger`, mismo icono. `style="display:inline"` solo para no romper la fila de botones.

BACKUP REQUERIDO ANTES DE SUBIR: copiar el vivo a `modalMisProveedores.php.bak-YYYYMMDD-HHMMSS` (UTC).

RUTA EXACTA EN CPANEL: `public_html/SystemSuplidor/PanelAdmin/modal/modalMisProveedores.php`

---

## ARCHIVO: modalVerProveedores.php

SHA DEL VIVO ACTUAL: `d24d061f61b9c137d5bb7a181db495c7aaf861bf157159840309a8c24a1db777` (22371 bytes)

SHA DEL ARCHIVO LOCAL PREPARADO: `83378bfa6247df7c218db56a4827cd9fbc021c7dd174cff4c4e20d85e1db6b18` (22395 bytes)

CAMBIOS EXACTOS: en el menú desplegable, el `<a href='../eliminar_proveedor.php?id=…&csrf_token=…'>` se sustituye por un `<form method='post' action='../eliminar_proveedor.php'>` con hidden `id` y `csrf_token`. Texto del botón sigue siendo «Eliminar». Misma clase `dropdown-item danger`. El `csrf_token` sigue saliendo de `$_SESSION['csrf_token']` (concatenación PHP igual que el GET actual).

BACKUP REQUERIDO ANTES DE SUBIR: copiar el vivo a `modalVerProveedores.php.bak-YYYYMMDD-HHMMSS` (UTC).

RUTA EXACTA EN CPANEL: `public_html/SystemSuplidor/PanelAdmin/modal/modalVerProveedores.php`

---

## ARCHIVO: modalVerUsuarios.php

SHA DEL VIVO ACTUAL: `94e9e00de08764bff70c55a52b4a30ce54c526778b58d99ecab21ff2c9e6b956` (22343 bytes)

SHA DEL ARCHIVO LOCAL PREPARADO: `039804111385c9008752ace15bb24f5c324ecf238f21b168380d8e5bc51bda32` (22463 bytes)

CAMBIOS EXACTOS: el `fetch` a `eliminar_empresa_usuario.php` **ya era POST**. Se añade `var SS_CSRF` con `$_SESSION['csrf_token']` y se concatena `&csrf_token=` en el body. No hay enlace GET de delete en este archivo. No se tocan toggles (no hay en este modal).

BACKUP REQUERIDO ANTES DE SUBIR: copiar el vivo a `modalVerUsuarios.php.bak-YYYYMMDD-HHMMSS` (UTC).

RUTA EXACTA EN CPANEL: `public_html/SystemSuplidor/PanelAdmin/modal/modalVerUsuarios.php`

---

## Pasos en cPanel File Manager

1. Entrar a cPanel → **File Manager**.
2. Ir a `public_html/SystemSuplidor/PanelAdmin/modal/`.
3. **Un archivo a la vez**, en este orden:
   1. `modalMisProveedores.php`
   2. `modalVerProveedores.php`
   3. `modalVerUsuarios.php`
4. Para cada uno:
   - Seleccionar el archivo vivo → **Copy** o **Rename** / duplicar.
   - El backup debe llamarse exactamente:  
     `NOMBRE.php.bak-YYYYMMDD-HHMMSS`  
     Ejemplo: `modalMisProveedores.php.bak-20260825-223000` (use hora UTC).
   - Confirmar que el `.bak-…` existe en la misma carpeta **antes** de subir el nuevo.
   - Subir el archivo nuevo desde `manual_deploy_paneladmin/` **con el mismo nombre** (`modalMisProveedores.php`, etc.), reemplazando el vivo.
   - No borrar el `.bak-…`.
5. Tras cada subida, abrir en el navegador:  
   `https://proveedoreslasociedad.com/SystemSuplidor/`  
   Debe cargar (login / splash). Si no, restaurar el `.bak-…` sobre el vivo (Rename/Copy del bak al nombre original).
6. Comprobar que la UI ya no usa GET para eliminar:
   - En «Mis proveedores» / «Proveedores»: clic derecho en Eliminar → no debe ser un enlace `eliminar_proveedor.php?id=`.
   - Debe ser un botón dentro de un formulario POST a `eliminar_proveedor.php`.
   - En «Usuarios»: quitar empresa del usuario sigue siendo `fetch` POST; ahora el body incluye `csrf_token`.
7. **No pulse Eliminar de verdad.** No confirme el `confirm()`. No quite empresas de un usuario. Los endpoints POST sí borrarían datos.

## Qué no hacer

- No usar FTP.
- No subir otros PHP.
- No mezclar estos archivos con `patches/idor/ui/` si el SHA no coincide con esta carpeta.
- No ejecutar deletes, restores, toggles, abonos ni crear factura.

## Rollback

En File Manager, copiar `ARCHIVO.php.bak-YYYYMMDD-HHMMSS` sobre `ARCHIVO.php`.
