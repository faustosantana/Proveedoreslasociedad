<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 *
 * Hard delete. CRITICAL. Solo admin/empresa dueña sobre facturas ya eliminadas.
 * POST+CSRF. GET = 405. No ampliar alcance.
 */

session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/../php/autorizacion_facturas.php';

function ss_perm_fail(int $code, string $message): void
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
    ss_perm_fail(401, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_perm_fail(405, 'Método no permitido');
}

if (!ss_csrf_post_ok()) {
    ss_perm_fail(403, 'Token de seguridad inválido.');
}

if ($tipo === 'proveedor' || $tipo === 'usuario') {
    ss_perm_fail(403, 'No autorizado');
}

if ($tipo !== 'admin' && $tipo !== 'empresa') {
    ss_perm_fail(403, 'No autorizado');
}

$id_factura = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
if ($id_factura === '') {
    ss_perm_fail(400, 'Parámetros insuficientes.');
}

$stmt = $pdo->prepare('SELECT id_factura, estado, id_empresa, factura_fisica FROM facturas WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura) {
    ss_perm_fail(404, 'Factura no encontrada.');
}

if (strtolower((string) $factura['estado']) !== 'eliminada') {
    ss_perm_fail(403, 'No autorizado');
}

if ($tipo === 'empresa' && !ss_puede_ver_factura($factura)) {
    ss_perm_fail(404, 'Factura no encontrada.');
}

$archivosRoot = realpath(__DIR__ . '/../archivos');

function ss_unlink_bajo_archivos(?string $rel, $root): void
{
    if ($rel === '' || $root === false) {
        return;
    }
    $candidate = realpath(__DIR__ . '/../' . ltrim(str_replace('\\', '/', $rel), '/'));
    if ($candidate === false || !is_file($candidate)) {
        return;
    }
    if (strpos($candidate, $root) !== 0) {
        return;
    }
    @unlink($candidate);
}

try {
    $pdo->beginTransaction();

    ss_unlink_bajo_archivos($factura['factura_fisica'] ?? '', $archivosRoot);

    $stmtAbonos = $pdo->prepare('SELECT comprobante, documento_adicional FROM abonos WHERE id_factura = ?');
    $stmtAbonos->execute([$id_factura]);
    foreach ($stmtAbonos->fetchAll(PDO::FETCH_ASSOC) as $abono) {
        ss_unlink_bajo_archivos($abono['comprobante'] ?? '', $archivosRoot);
        ss_unlink_bajo_archivos($abono['documento_adicional'] ?? '', $archivosRoot);
    }

    $stmtDelAbonos = $pdo->prepare('DELETE FROM abonos WHERE id_factura = ?');
    $stmtDelAbonos->execute([$id_factura]);

    try {
        $stmtDelComentarios = $pdo->prepare('DELETE FROM comentarios_rechazo WHERE id_factura = ?');
        $stmtDelComentarios->execute([$id_factura]);
    } catch (PDOException $e) {
        // tabla opcional
    }

    if ($tipo === 'admin') {
        $stmtDelFactura = $pdo->prepare("DELETE FROM facturas WHERE id_factura = ? AND estado = 'eliminada'");
        $stmtDelFactura->execute([$id_factura]);
    } else {
        $stmtDelFactura = $pdo->prepare("DELETE FROM facturas WHERE id_factura = ? AND estado = 'eliminada' AND id_empresa = ?");
        $stmtDelFactura->execute([$id_factura, ss_id_empresa_sesion()]);
    }
    if ($stmtDelFactura->rowCount() < 1) {
        $pdo->rollBack();
        ss_perm_fail(404, 'Factura no encontrada.');
    }

    $pdo->commit();
    registrarAuditoria($pdo, 'Eliminar factura permanentemente', 'Factura', $id_factura, 'Factura eliminada permanentemente de la base de datos');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ss_perm_fail(500, 'No se pudo completar la acción.');
}

header('Location: ../historial.php?msg=' . rawurlencode('Factura eliminada permanentemente de la base de datos.'));
exit;
