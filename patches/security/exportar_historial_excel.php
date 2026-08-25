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
require_once __DIR__ . '/../Base_de_datos/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo_usuario = $_SESSION['tipo'] ?? null;
if (!$tipo_usuario) {
    http_response_code(401);
    include __DIR__ . '/../plantillas/no_autorizado.php';
    exit;
}

if ($tipo_usuario === 'proveedor') {
    $id_scope = $_SESSION['id_proveedor'] ?? null;
    if (!$id_scope) {
        http_response_code(401);
        include __DIR__ . '/../plantillas/no_autorizado.php';
        exit;
    }
} elseif ($tipo_usuario === 'empresa') {
    $id_scope = $_SESSION['id_empresa'] ?? null;
    if (!$id_scope) {
        http_response_code(401);
        include __DIR__ . '/../plantillas/no_autorizado.php';
        exit;
    }
} elseif ($tipo_usuario === 'usuario') {
    $id_scope = $_SESSION['empresa_activa'] ?? ($_SESSION['id_empresa'] ?? null);
    if (!$id_scope) {
        http_response_code(401);
        include __DIR__ . '/../plantillas/no_autorizado.php';
        exit;
    }
} elseif ($tipo_usuario !== 'admin') {
    http_response_code(401);
    include __DIR__ . '/../plantillas/no_autorizado.php';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$estado = $_GET['estado'] ?? null;
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

$ids = isset($_POST['ids_factura']) ? $_POST['ids_factura'] : '';
$ids_array = array_filter(explode(',', $ids));

if (!empty($ids_array)) {
    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
    $sql = "SELECT f.id_factura, p.nombre_empresa AS proveedor, e.nombre AS empresa_emisora, f.fecha_digital, f.producto, f.total, f.estado, f.es_credito, f.fecha_vencimiento, (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura) AS total_pagado, (f.total - (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura)) AS pendiente FROM facturas f LEFT JOIN proveedores p ON f.id_proveedor = p.id LEFT JOIN empresas e ON f.id_empresa = e.id_empresa WHERE f.id_factura IN ($placeholders)";
    $params = array_values($ids_array);
} else {
    $sql = "SELECT f.id_factura, p.nombre_empresa AS proveedor, e.nombre AS empresa_emisora, f.fecha_digital, f.producto, f.total, f.estado, f.es_credito, f.fecha_vencimiento, (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura) AS total_pagado, (f.total - (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura)) AS pendiente FROM facturas f LEFT JOIN proveedores p ON f.id_proveedor = p.id LEFT JOIN empresas e ON f.id_empresa = e.id_empresa WHERE f.estado != 'eliminada'";
    $params = [];
    if ($estado) {
        $sql .= " AND f.estado = ?";
        $params[] = $estado;
    }
    if ($fecha_inicio) {
        $sql .= " AND f.fecha_digital >= ?";
        $params[] = $fecha_inicio;
    }
    if ($fecha_fin) {
        $sql .= " AND f.fecha_digital <= ?";
        $params[] = $fecha_fin;
    }
}

if ($tipo_usuario === 'proveedor') {
    $sql .= " AND f.id_proveedor = ?";
    $params[] = $id_scope;
} elseif ($tipo_usuario === 'empresa' || $tipo_usuario === 'usuario') {
    $sql .= " AND f.id_empresa = ?";
    $params[] = $id_scope;
}

if (empty($ids_array)) {
    $sql .= " ORDER BY f.fecha_digital DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="historial_facturas.xlsx"');
header('Cache-Control: max-age=0');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if (!empty($facturas)) {
    $facturaKeys = array_keys($facturas[0]);
    $headers = array_filter($facturaKeys, function($h) { return $h !== 'factura_fisica'; });
    $sheet->fromArray($headers, null, 'A1');
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '217346']
        ],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ];
    $lastCol = chr(65 + count($headers) - 1);
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(24);

    $row = 2;
    foreach ($facturas as $factura) {
        $col = 0;
        foreach ($headers as $key) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $factura[$key]);
            $col++;
        }
        $row++;
    }

    for ($i = 0; $i < count($headers); $i++) {
        $col = chr(65 + $i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
