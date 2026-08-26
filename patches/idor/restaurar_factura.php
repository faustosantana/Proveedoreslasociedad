<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 *
 * Restaurar / re-marcar eliminada. POST+CSRF. GET = 405.
 */

session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

function ss_rest_fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    if ($code === 405) {
        header('Allow: POST');
    }
    echo $message;
    exit;
}

function ss_csrf_post_ok(): bool
{
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '') {
        return false;
    }
    return validarTokenCSRFDeterministico($token) || validarTokenCSRF($token);
}

$tipo = ss_require_tipo_sesion();
if (!$tipo) {
    ss_rest_fail(401, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_rest_fail(405, 'Método no permitido');
}

if (!ss_csrf_post_ok()) {
    ss_rest_fail(403, 'Token de seguridad inválido.');
}

if ($tipo === 'proveedor' || $tipo === 'usuario') {
    ss_rest_fail(403, 'No autorizado');
}

if ($tipo !== 'admin' && $tipo !== 'empresa') {
    ss_rest_fail(403, 'No autorizado');
}

$id_factura = isset($_POST['id_factura']) ? trim((string) $_POST['id_factura']) : '';
$nuevo_estado = isset($_POST['nuevo_estado']) ? trim((string) $_POST['nuevo_estado']) : '';

$estados_ok = ['pendiente' => true, 'eliminada' => true];
if ($id_factura === '' || $nuevo_estado === '') {
    ss_rest_fail(400, 'Parámetros insuficientes.');
}
if (!isset($estados_ok[$nuevo_estado])) {
    ss_rest_fail(400, 'Acción no válida.');
}

$stmt = $pdo->prepare('SELECT id_factura, estado, id_empresa FROM facturas WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura) {
    ss_rest_fail(404, 'Factura no encontrada.');
}

$estado_actual = strtolower((string) $factura['estado']);

if ($tipo === 'empresa') {
    if (!ss_puede_ver_factura($factura)) {
        ss_rest_fail(404, 'Factura no encontrada.');
    }
    if ($estado_actual === 'pagada') {
        ss_rest_fail(403, 'No autorizado');
    }
}

if ($estado_actual === 'rechazada' && $nuevo_estado !== 'rechazada') {
    $stmt_ncf = $pdo->prepare(
        'SELECT id_factura FROM facturas WHERE ncf = (SELECT ncf FROM facturas WHERE id_factura = ?) AND id_factura != ? AND estado != \'rechazada\' LIMIT 1'
    );
    $stmt_ncf->execute([$id_factura, $id_factura]);
    if ($stmt_ncf->fetch()) {
        http_response_code(409);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => 'No se puede restablecer esta factura porque ya existe otra factura activa con el mismo NCF.']);
        exit;
    }
}

try {
    if ($nuevo_estado === 'eliminada') {
        if ($tipo === 'admin') {
            $sql = "UPDATE facturas SET estado = 'eliminada', fecha_eliminacion = NOW() WHERE id_factura = ?";
            $params = [$id_factura];
        } else {
            $sql = "UPDATE facturas SET estado = 'eliminada', fecha_eliminacion = NOW() WHERE id_factura = ? AND id_empresa = ?";
            $params = [$id_factura, ss_id_empresa_sesion()];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        registrarAuditoria($pdo, 'Eliminar factura', 'Factura', $id_factura, 'Factura marcada como eliminada desde restauración');
    } else {
        if ($tipo === 'admin') {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NULL WHERE id_factura = ?';
            $params = [$nuevo_estado, $id_factura];
        } else {
            $sql = 'UPDATE facturas SET estado = ?, fecha_eliminacion = NULL WHERE id_factura = ? AND id_empresa = ?';
            $params = [$nuevo_estado, $id_factura, ss_id_empresa_sesion()];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        registrarAuditoria($pdo, 'Restaurar factura', 'Factura', $id_factura, "Factura restaurada a estado: $nuevo_estado");
    }
    if ($stmt->rowCount() < 1) {
        ss_rest_fail(404, 'Factura no encontrada.');
    }
} catch (Exception $e) {
    ss_rest_fail(500, 'No se pudo completar la acción.');
}

header('Location: ../historial.php');
exit;
