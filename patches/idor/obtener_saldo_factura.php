<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */

session_start();
require_once '../Base_de_datos/config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

header('Content-Type: application/json');

if (!ss_require_tipo_sesion()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_factura = $_GET['id_factura'] ?? null;
if (!$id_factura) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de factura requerido']);
    exit;
}

$stmt = $pdo->prepare('SELECT total, id_proveedor, id_empresa FROM facturas WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura || !ss_puede_ver_factura($factura)) {
    http_response_code(404);
    echo json_encode(['error' => 'Factura no encontrada']);
    exit;
}

$stmt = $pdo->prepare('SELECT SUM(monto) as total_abonado FROM abonos WHERE id_factura = ?');
$stmt->execute([$id_factura]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_abonado = floatval($row['total_abonado'] ?? 0);
$saldo_pendiente = max(0, floatval($factura['total']) - $total_abonado);
echo json_encode(['saldo_pendiente' => round($saldo_pendiente, 2)]);
