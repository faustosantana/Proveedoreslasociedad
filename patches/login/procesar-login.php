<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */

// Fuerza la salida y conexión a UTF-8
header('Content-Type: text/html; charset=UTF-8');

session_start();
require_once dirname(__DIR__) . '/Base_de_datos/config.php';
require_once dirname(__DIR__) . '/rutas_amigables.php';

// Fuerza la conexión PDO a utf8mb4
if (isset($pdo)) {
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
}

// -----------------------------------------------------------------------
// reCAPTCHA v3 (advisory)
// No bloquea el login: usuarios reales quedaban fuera por siteverify
// inalcanzable, secret inválido, score < 0.5 o action distinto de login.
// Siguen exigiendo usuario/contraseña y el rate limit de intentos.
// -----------------------------------------------------------------------
$es_local = env('APP_ENV', 'local') === 'local';

function ss_recaptcha_siteverify(?string $secret, string $token): ?object
{
    if ($secret === null || $secret === '' || $token === '') {
        return null;
    }
    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $token,
    ]);
    $raw = false;
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($raw === false && function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }
    if ($raw === false || $raw === null || $raw === '') {
        error_log('reCAPTCHA siteverify unavailable; login continues');
        return null;
    }
    $decoded = json_decode($raw);
    return is_object($decoded) ? $decoded : null;
}

if (!$es_local) {
    $recaptcha_response = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
    $recaptcha_secret = env('RECAPTCHA_SECRET_KEY');
    $captcha = ss_recaptcha_siteverify($recaptcha_secret, $recaptcha_response);
    if (is_object($captcha)) {
        $errors = isset($captcha->{'error-codes'}) ? (array) $captcha->{'error-codes'} : [];
        if (in_array('invalid-input-secret', $errors, true)) {
            error_log('reCAPTCHA invalid-input-secret; login continues');
        } elseif (empty($captcha->success)) {
            error_log('reCAPTCHA success=false; login continues');
        } elseif (($captcha->score ?? 1) < 0.3) {
            error_log('reCAPTCHA low score; login continues');
        }
    }
}
$usuario = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');

// Rate limiting: máximo 5 intentos fallidos en 15 minutos
if ($usuario && verificarRateLimit($pdo, $usuario)) {
    $loginUrl = getBaseUrl() . ($RUTA_LOGIN ?? '/PanelAdmin/login.php');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body style="background:#f4f6fa;"><script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: "warning",
                title: "Demasiados intentos",
                text: "Has excedido el límite de intentos. Espera 15 minutos e intenta de nuevo.",
                confirmButtonText: "Entendido",
                confirmButtonColor: "#d33",
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function() {
                window.location.href = "' . $loginUrl . '";
            });
        });
    </script></body></html>';
    exit;
}

if (!$usuario || !$contrasena) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body style="background:#f4f6fa;">
        <script>
            // Aseguramos que el DOM esté listo
            document.addEventListener("DOMContentLoaded", function() {
                try {
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: "Atención",
                            text: "Por favor completa todos los campos.",
                            icon: "warning",
                            confirmButtonColor: "#3085d6",
                            confirmButtonText: "Aceptar",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "login.php";
                            }
                        });
                    } else {
                        // Fallback si no carga SweetAlert
                        throw new Error("Swal not defined");
                    }
                } catch (e) {
                    alert("Por favor completa todos los campos.");
                    window.location.href = "login.php";
                }
            });
        </script>
    </body>
    </html>';
    exit;
}


function verificarUsuario($pdo, $tabla, $usuario, $contrasena, $userCol, $passCol, $nombreCol, $idCol)
{
    // Si es proveedores, validar que esté activo
    try {
        if ($tabla === 'proveedores') {
            // Buscar por usuario O correo
            $sql = "SELECT $idCol AS id, $nombreCol AS nombre, $passCol AS password, activo FROM $tabla WHERE $userCol = ? OR correo = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario, $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($contrasena, $user['password'])) {
                if ($user['activo'] == -1) {
                    // Buscar comentario de rechazo
                    $comentario = '';
                    $logFile = dirname(__DIR__) . '/debug_comentarios_rechazo.log';
                    if (file_exists($logFile)) {
                        $lines = file($logFile);
                        foreach (array_reverse($lines) as $line) {
                            if (strpos($line, 'Proveedor ID: ' . $user['id'] . ' |') !== false) {
                                $comentario = trim(explode('|', $line, 2)[1]);
                                break;
                            }
                        }
                    }
                    require_once dirname(__DIR__) . '/plantillas/template/alert.php';
                    $msg = 'Tu cuenta de proveedor ha sido RECHAZADA.';
                    if ($comentario) {
                        $msg .= '<br><b>Motivo:</b> ' . htmlspecialchars($comentario);
                    }
                    // Modal centrado fijo con el mensaje de rechazo
                    echo '<div style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;">';
                    echo '<div style="background:#fff;padding:2em 2.5em;border-radius:10px;max-width:400px;width:100%;box-shadow:0 2px 16px #0002;text-align:center;">';
                    echo '<h2 style="color:#c0392b;margin-bottom:1em;">Acceso denegado</h2>';
                    echo '<div style="background:#f8d7da;color:#721c24;padding:1em;border-radius:8px;margin-bottom:1.5em;font-size:1.1em;">' . $msg . '</div>';
                    echo '</div></div>';
                    echo '<script>document.body.style.overflow = "hidden";</script>';
                    exit;
                } elseif ($user['activo'] == -3) {
                    // Proveedor requiere cambio de contraseña obligatorio
                    // Permitir login - el switch case posterior manejará la redirección
                    return $user;
                } elseif ($user['activo'] == 0) {
                    // Cuenta en proceso de activación - Mostrar modal con SweetAlert
                    $loginUrl = getBaseUrl() . '/PanelAdmin/login.php';
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    </head>
                    <body>
                        <script>
                            Swal.fire({
                                icon: "info",
                                title: "Cuenta en proceso de activación",
                                html: "Tu cuenta de proveedor está siendo revisada por un administrador.<br><br>Recibirás una notificación por correo cuando sea aprobada.",
                                confirmButtonText: "Entendido",
                                confirmButtonColor: "#3085d6",
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "' . $loginUrl . '";
                                }
                            });
                        </script>
                    </body>
                    </html>';
                    exit;
                } elseif ($user['activo'] != 1) {
                    // Cualquier otro estado no reconocido - bloquear acceso
                    $loginUrl = getBaseUrl() . '/PanelAdmin/login.php';
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    </head>
                    <body>
                        <script>
                            Swal.fire({
                                icon: "warning",
                                title: "Estado de cuenta no válido",
                                text: "Tu cuenta tiene un estado no reconocido. Contacta al administrador.",
                                confirmButtonText: "Entendido",
                                confirmButtonColor: "#3085d6",
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "' . $loginUrl . '";
                                }
                            });
                        </script>
                    </body>
                    </html>';
                    exit;
                }
                return $user;
            }
            return false;
        } else {
            if ($tabla === 'empresas') {
                // Buscar por usuario O correo
                $sql = "SELECT $idCol AS id, $nombreCol AS nombre, $passCol AS password, activo FROM $tabla WHERE $userCol = ? OR correo = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$usuario, $usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($contrasena, $user['password'])) {
                    if (isset($user['activo']) && $user['activo'] == 0) {
                        $loginUrl = getBaseUrl() . '/PanelAdmin/login.php';
                        echo '<!DOCTYPE html>
                        <html lang="es">
                        <head>
                            <meta charset="UTF-8">
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        </head>
                        <body style="background:#f4f6fa;">
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    try {
                                        if (typeof Swal !== "undefined") {
                                            Swal.fire({
                                                icon: "error",
                                                title: "Cuenta Desactivada",
                                                text: "Su cuenta de empresa ha sido desactivada. Contacte al administrador.",
                                                confirmButtonText: "Entendido",
                                                confirmButtonColor: "#d33",
                                                allowOutsideClick: false,
                                                allowEscapeKey: false
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    window.location.href = "' . $loginUrl . '";
                                                }
                                            });
                                        } else {
                                            throw new Error("Swal not defined");
                                        }
                                    } catch(e) {
                                        alert("Su cuenta de empresa ha sido desactivada. Contacte al administrador.");
                                        window.location.href = "' . $loginUrl . '";
                                    }
                                });
                            </script>
                        </body>
                        </html>';
                        exit;
                    }
                    return $user;
                }
                return false;
            }

            if ($tabla === 'usuarios') {
                // Buscar por usuario O correo (si existe la columna correo)
                $sql = "SELECT $idCol AS id, $nombreCol AS nombre, $passCol AS password, activo FROM $tabla WHERE $userCol = ? OR correo = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$usuario, $usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($contrasena, $user['password'])) {
                    if (isset($user['activo']) && $user['activo'] == 0) {
                        $loginUrl = getBaseUrl() . '/PanelAdmin/login.php';
                        echo '<!DOCTYPE html>
                        <html lang="es">
                        <head>
                            <meta charset="UTF-8">
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        </head>
                        <body style="background:#f4f6fa;">
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    try {
                                        if (typeof Swal !== "undefined") {
                                            Swal.fire({
                                                icon: "error",
                                                title: "Cuenta Desactivada",
                                                text: "Su usuario ha sido desactivado. Contacte al administrador.",
                                                confirmButtonText: "Entendido",
                                                confirmButtonColor: "#d33",
                                                allowOutsideClick: false,
                                                allowEscapeKey: false
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    window.location.href = "' . $loginUrl . '";
                                                }
                                            });
                                        } else {
                                            throw new Error("Swal not defined");
                                        }
                                    } catch(e) {
                                        alert("Su usuario ha sido desactivado. Contacte al administrador.");
                                        window.location.href = "' . $loginUrl . '";
                                    }
                                });
                            </script>
                        </body>
                        </html>';
                        exit;
                    }
                    return $user;
                }
                return false;
            }

            // Para administradores también buscar por correo
            $sql = "SELECT $idCol AS id, $nombreCol AS nombre, $passCol AS password FROM $tabla WHERE $userCol = ? OR correo = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario, $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($contrasena, $user['password'])) {
                return $user;
            }
            return false;
        }
    } catch (PDOException $e) {
        file_put_contents(dirname(__DIR__) . '/PanelAdmin/login_error.log', date('Y-m-d H:i:s') . " SQL error in $tabla: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    } catch (Exception $e) {
        file_put_contents(dirname(__DIR__) . '/PanelAdmin/login_error.log', date('Y-m-d H:i:s') . " General error in $tabla: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// Verificar super_administradores primero (super admin oculto)
try {
    $stmt = $pdo->prepare("SELECT id, nombre, contrasena, ultimo_acceso FROM super_administradores WHERE correo = ?");
    $stmt->execute([$usuario]);
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($superadmin && password_verify($contrasena, $superadmin['contrasena'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = $superadmin['nombre'];
        $_SESSION['tipo'] = 'superadmin';
        $_SESSION['id_superadmin'] = $superadmin['id'];

        // Registrar en auditoría
        registrarAuditoria($pdo, 'Inicio de sesión', 'Sesión', $superadmin['id'], "Inicio de sesión exitoso desde " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));

        // Actualizar ultimo_acceso
        $pdo->prepare("UPDATE super_administradores SET ultimo_acceso = NOW() WHERE id = ?")->execute([$superadmin['id']]);

        $pendientes = documentosPendientesAceptacion($pdo, 'superadmin', $superadmin['id']);
        if (!empty($pendientes)) {
            header("Location: /SystemSuplidor/app/legal/aceptar_terminos.php?redirect=" . urlencode("/SystemSuplidor" . $RUTA_SUPERADMIN));
            exit;
        }

        header("Location: /SystemSuplidor" . $RUTA_SUPERADMIN);
        exit;
    }
} catch (PDOException $e) {
    // Si la tabla no existe, continuar con el login normal
}

// Mapeo para cada tipo de usuario
$tablas = [
    ['tabla' => 'administradores', 'usuario' => 'nombre', 'pass' => 'contrasena', 'nombre' => 'nombre', 'id' => 'id', 'tipo' => 'admin', 'redirect' => $RUTA_INDEX],
    ['tabla' => 'empresas',       'usuario' => 'usuario', 'pass' => 'contraseña', 'nombre' => 'nombre', 'id' => 'id_empresa', 'tipo' => 'empresa', 'redirect' => $RUTA_INDEX],
    ['tabla' => 'proveedores',    'usuario' => 'usuario', 'pass' => 'password', 'nombre' => 'nombre_empresa', 'id' => 'id', 'tipo' => 'proveedor', 'redirect' => $RUTA_INDEX],
    ['tabla' => 'usuarios',       'usuario' => 'usuario', 'pass' => 'contrasena', 'nombre' => 'nombre', 'id' => 'id', 'tipo' => 'usuario', 'redirect' => $RUTA_SELECCION], 
];

$RUTA_INDEX = '/x1y2z3aBcDeFgHiJkLmNoPqRsTuVwXyZ123456';
$logueado = false;
foreach ($tablas as $t) {
    $user = verificarUsuario($pdo, $t['tabla'], $usuario, $contrasena, $t['usuario'], $t['pass'], $t['nombre'], $t['id']);
    // Log intento de login
    file_put_contents(dirname(__DIR__) . '/PanelAdmin/login_error.log', date('Y-m-d H:i:s') . " Intento login tabla={$t['tabla']} usuario={$usuario} resultado=" . ($user ? 'OK' : 'FAIL') . "\n", FILE_APPEND);
    if ($user) {
        $_SESSION['usuario'] = $user['nombre'];
        $_SESSION['tipo'] = $t['tipo'];
        switch ($t['tipo']) {
            case 'proveedor':
                $_SESSION['id_proveedor'] = $user['id'];
                // Verificar si requiere cambio de contraseña (activo = -3)
                if (isset($user['activo']) && $user['activo'] == -3) {
                    $_SESSION['requiere_cambio_password'] = 1;
                    header("Location: /SystemSuplidor/PanelDeUsuario/cambio_password_obligatorio.php");
                    exit;
                }
                break;
            case 'empresa':
                $_SESSION['id_empresa'] = $user['id'];
                break;
            case 'admin':
                $_SESSION['id'] = $user['id'];
                break;
            case 'usuario': 
                $_SESSION['id_usuario'] = $user['id'];
                break;
        }
        $logueado = true;
        session_regenerate_id(true);
        registrarIntentoLogin($pdo, $usuario, true);

        // Registrar inicio de sesión en auditoría
        try {
            registrarAuditoria($pdo, 'Inicio de sesión', 'Sesión', $user['id'], "Inicio de sesión exitoso desde " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        } catch (Exception $e) {}

        // Verificar documentos legales pendientes de aceptación
        $pendientes = documentosPendientesAceptacion($pdo, $t['tipo'], $user['id']);
        if (!empty($pendientes)) {
            $redirectEncoded = urlencode("/SystemSuplidor" . $t['redirect']);
            header("Location: /SystemSuplidor/app/legal/aceptar_terminos.php?redirect=" . $redirectEncoded);
            exit;
        }

        // Redirección amigable para cada tipo de usuario
        header("Location: /SystemSuplidor" . $t['redirect']);
        exit;
    }
}

if (!$logueado) {
    registrarIntentoLogin($pdo, $usuario, false);

    // Definimos la URL de login para el redireccionamiento
    $loginUrl = getBaseUrl() . ($RUTA_LOGIN ?? '/PanelAdmin/login.php');
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body style="background:#f4f6fa;">
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                try {
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "error",
                            title: "Error de acceso",
                            text: "Usuario o contraseña incorrectos",
                            confirmButtonText: "Intentar de nuevo",
                            confirmButtonColor: "#d33",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "' . $loginUrl . '";
                            }
                        });
                    } else {
                        throw new Error("Swal not defined");
                    }
                } catch(e) {
                    alert("Usuario o contraseña incorrectos");
                    window.location.href = "' . $loginUrl . '";
                }
            });
        </script>
    </body>
    </html>';
}
