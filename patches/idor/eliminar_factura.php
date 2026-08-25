<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 *
 * Soft delete: estado=eliminada. Solo admin/empresa dueña. POST+CSRF.
 */

session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/../php/autorizacion_facturas.php';

function ss_elim_fail(int $code, string $message): void
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
    ss_elim_fail(401, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_elim_fail(405, 'Método no permitido');
}

if (!ss_csrf_post_ok()) {
    ss_elim_fail(403, 'Token de seguridad inválido.');
}

if ($tipo === 'proveedor' || $tipo === 'usuario') {
    ss_elim_fail(403, 'No autorizado');
}

if ($tipo !== 'admin' && $tipo !== 'empresa') {
    ss_elim_fail(403, 'No autorizado');
}

$id_factura = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
if ($id_factura === '') {
    ss_elim_fail(400, 'Parámetros insuficientes.');
}

$stmt = $pdo->prepare('SELECT id_factura, estado, id_empresa FROM facturas WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura) {
    ss_elim_fail(404, 'Factura no encontrada.');
}

if ($tipo === 'empresa') {
    if (!ss_puede_ver_factura($factura)) {
        ss_elim_fail(404, 'Factura no encontrada.');
    }
    if (strtolower((string) $factura['estado']) === 'pagada') {
        ss_elim_fail(403, 'No autorizado');
    }
}

try {
    if ($tipo === 'admin') {
        $sql = "UPDATE facturas SET estado = 'eliminada', fecha_eliminacion = NOW() WHERE id_factura = ?";
        $params = [$id_factura];
    } else {
        $sql = "UPDATE facturas SET estado = 'eliminada', fecha_eliminacion = NOW() WHERE id_factura = ? AND id_empresa = ?";
        $params = [$id_factura, ss_id_empresa_sesion()];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() < 1) {
        ss_elim_fail(404, 'Factura no encontrada.');
    }
    registrarAuditoria($pdo, 'Eliminar factura', 'Factura', $id_factura, 'Factura marcada como eliminada (soft delete)');
} catch (PDOException $e) {
    ss_elim_fail(500, 'No se pudo completar la acción.');
}

header('Location: ../historial.php?msg=' . rawurlencode('Factura eliminada temporalmente. Se eliminará definitivamente en 15 días.'));
exit;
