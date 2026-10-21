<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 *
 * GET LEGACY — AUTHENTICATED ONLY
 * UI actual usa acciones/eliminar_factura.php y php/restaurar_factura.php.
 * Correos vigentes usan acciones/actualizar_estado_factura.php (HMAC).
 */

session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

function ss_acciones_fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

$tipo = ss_require_tipo_sesion();
if (!$tipo) {
    ss_acciones_fail(401, 'No autorizado');
}

$id_factura = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$accion = isset($_GET['accion']) ? trim((string) $_GET['accion']) : '';

if ($id_factura === '' || $accion === '') {
    ss_acciones_fail(400, 'Parámetros insuficientes.');
}

$acciones_permitidas = [
    'aceptar' => 'aceptada',
    'eliminar' => 'eliminada',
    'restaurar' => 'pendiente',
];
if (!isset($acciones_permitidas[$accion])) {
    ss_acciones_fail(400, 'Acción no válida.');
}

if ($tipo === 'proveedor' || $tipo === 'usuario') {
    ss_acciones_fail(403, 'No autorizado');
}

if ($tipo !== 'admin' && $tipo !== 'empresa') {
    ss_acciones_fail(403, 'No autorizado');
}

$nuevo_estado = $acciones_permitidas[$accion];

$stmt = $pdo->prepare('SELECT id_factura, estado, id_empresa, id_proveedor FROM facturas WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura) {
    ss_acciones_fail(404, 'Factura no encontrada.');
}

if ($tipo === 'empresa') {
    if (!ss_puede_ver_factura($factura)) {
        ss_acciones_fail(404, 'Factura no encontrada.');
    }
    if (strtolower((string) $factura['estado']) === 'pagada') {
        ss_acciones_fail(403, 'No autorizado');
    }
}

try {
    if ($accion === 'eliminar') {
        if ($tipo === 'admin') {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NOW() WHERE id_factura = ?';
            $params = [$nuevo_estado, $id_factura];
        } else {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NOW() WHERE id_factura = ? AND id_empresa = ?';
            $params = [$nuevo_estado, $id_factura, ss_id_empresa_sesion()];
        }
    } else {
        if ($tipo === 'admin') {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NULL WHERE id_factura = ?';
            $params = [$nuevo_estado, $id_factura];
        } else {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NULL WHERE id_factura = ? AND id_empresa = ?';
            $params = [$nuevo_estado, $id_factura, ss_id_empresa_sesion()];
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() < 1) {
        ss_acciones_fail(404, 'Factura no encontrada.');
    }

    $mensajes = [
        'aceptar' => 'Factura aceptada correctamente.',
        'eliminar' => 'Factura marcada como eliminada. Se eliminará definitivamente en 15 días.',
        'restaurar' => 'Factura restaurada a pendiente.',
    ];
    $mensaje = $mensajes[$accion];
    registrarAuditoria($pdo, ucfirst($accion) . ' factura', 'Factura', $id_factura, $mensaje);
} catch (Exception $e) {
    ss_acciones_fail(500, 'No se pudo completar la acción.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Acción en factura</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            icon: 'success',
            title: <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>,
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = '../historial.php';
        });
    </script>
</body>
</html>
