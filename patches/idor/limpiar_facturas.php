<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 *
 * Mantenimiento: borra facturas ya marcadas eliminada hace ≥15 días.
 * Sin callers en UI/cron/email. Solo admin, POST + CSRF.
 * GET no es destructivo.
 */

session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

function ss_limpiar_fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    if ($code === 405) {
        header('Allow: POST');
    }
    echo $message;
    exit;
}

$tipo = ss_require_tipo_sesion();
if (!$tipo) {
    ss_limpiar_fail(401, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_limpiar_fail(405, 'Método no permitido');
}

if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
    ss_limpiar_fail(403, 'Token de seguridad inválido.');
}

if ($tipo !== 'admin') {
    ss_limpiar_fail(403, 'No autorizado');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "DELETE FROM facturas
         WHERE estado = 'eliminada'
           AND fecha_eliminacion <= (NOW() - INTERVAL 15 DAY)"
    );
    $stmt->execute();
    $eliminadas = $stmt->rowCount();
    $pdo->commit();

    if ($eliminadas > 0) {
        registrarAuditoria(
            $pdo,
            'Limpiar facturas antiguas',
            'Factura',
            0,
            "$eliminadas factura(s) eliminada(s) definitivamente por vencimiento de 15 días"
        );
    }

    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Proceso completado. ' . $eliminadas . ' facturas eliminadas definitivamente.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ss_limpiar_fail(500, 'No se pudo completar la limpieza.');
}
