# Login: reCAPTCHA no debe bloquear usuarios reales

## Causa

`procesar-login.php` exigía token + `siteverify` OK + score ≥ 0.5 + `action === login`.
Cualquier fallo (Google inalcanzable, secret inválido, score bajo, token vacío) mostraba:

«Verificación de seguridad fallida / protección contra bots»

## Cambio

- reCAPTCHA queda en modo advisory (solo log).
- Usuario/contraseña y rate limit (5 / 15 min) siguen.
- Si Google tarda o falla en el navegador, el formulario se envía igual (4 s).

## Vivo

- `PanelAdmin/procesar-login.php`
- `PanelAdmin/login.php`

## Backup

`*.bak-20261021-212215` (RETR del anterior). HTTP 403.
