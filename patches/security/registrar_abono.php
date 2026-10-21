<?php
session_start();

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */
require_once '../Base_de_datos/config.php';
require_once '../Base_de_datos/correos_config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

function ss_guardar_abono_finfo(string $campo, string $prefijo): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('Error al subir el archivo.');
    }
    if ($_FILES[$campo]['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        exit('El archivo es demasiado grande. Máx 5MB.');
    }
    $ext = strtolower((string) pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    $permitidas = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    if (!isset($permitidas[$ext])) {
        http_response_code(400);
        exit('Tipo de archivo no permitido.');
    }
    if (!function_exists('finfo_open')) {
        http_response_code(500);
        exit('No se pudo validar el archivo.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$campo]['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mime, $permitidas[$ext], true)) {
        http_response_code(400);
        exit('El contenido del archivo no es válido.');
    }
    $nombre = $prefijo . uniqid('', true) . '.' . $ext;
    $dir = '../archivos/comprobantes_de_pagos/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ruta = $dir . $nombre;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta)) {
        http_response_code(500);
        exit('Error al guardar el archivo.');
    }
    return 'archivos/comprobantes_de_pagos/' . $nombre;
}

$tipo = ss_require_tipo_sesion();
if (!$tipo) {
    http_response_code(401);
    exit('No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
    http_response_code(403);
    exit('Token de seguridad inválido.');
}

if ($tipo === 'proveedor') {
    http_response_code(403);
    exit('No autorizado');
}

$id_factura = $_POST['id_factura_abono'] ?? null;
$monto_abono = $_POST['monto_abono'] ?? null;
$comentario = trim($_POST['comentario_abono'] ?? '');

$link_comprobante = ss_guardar_abono_finfo('comprobante_abono', 'abono_');
if ($link_comprobante === null) {
    http_response_code(400);
    exit('Debe adjuntar un comprobante de pago.');
}
$link_documento_adicional = ss_guardar_abono_finfo('documento_adicional_abono', 'abono_doc2_');

if (!$id_factura || !is_numeric($monto_abono) || $monto_abono <= 0) {
    http_response_code(400);
    exit('Datos de abono inválidos.');
}
// Redondear a 2 decimales
$monto_abono = round(floatval($monto_abono), 2);

try {
    // Verificar que la factura exista y sea a crédito
    $stmt = $pdo->prepare('SELECT total, es_credito, estado, id_proveedor, id_empresa FROM facturas WHERE id_factura = ?');
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura || $factura['es_credito'] !== 'si') {
        http_response_code(400);
        exit('Factura no válida para abonos.');
    }
    if (!ss_puede_ver_factura($factura)) {
        http_response_code(404);
        exit('Factura no encontrada.');
    }
    if ($factura['estado'] === 'pagada') {
        http_response_code(400);
        exit('La factura ya está pagada.');
    }

// Calcular total abonado hasta ahora
    $stmt = $pdo->prepare('SELECT SUM(monto) as total_abonado FROM abonos WHERE id_factura = ?');
    $stmt->execute([$id_factura]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_abonado = floatval($row['total_abonado'] ?? 0);

    $nuevo_total = $total_abonado + $monto_abono;
    $total_factura = floatval($factura['total']);

    if ($monto_abono > ($total_factura - $total_abonado) + 0.01) {
        http_response_code(400);
        exit('El abono excede el saldo pendiente de la factura.');
    }

    // Registrar abono con comprobante y documento adicional
    $stmt = $pdo->prepare('INSERT INTO abonos (id_factura, monto, comentario, fecha_abono, comprobante, documento_adicional) VALUES (?, ?, ?, NOW(), ?, ?)');
    $stmt->execute([$id_factura, $monto_abono, $comentario, $link_comprobante, $link_documento_adicional]);
    registrarAuditoria($pdo, 'Registrar abono', 'Factura', $id_factura, "Abono registrado por $monto_abono");
    // Enviar correo de abono
    require_once '../correo/correo_abono.php';
    enviarCorreoAbono($id_factura, $monto_abono, $comentario, $link_comprobante);

    // Si se completó el pago, marcar factura como pagada
    $stmt = $pdo->prepare('SELECT SUM(monto) as total_abonado FROM abonos WHERE id_factura = ?');
    $stmt->execute([$id_factura]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_abonado_actual = floatval($row['total_abonado'] ?? 0);
    if (abs($total_abonado_actual - $total_factura) < 0.01) {
        $stmt = $pdo->prepare('UPDATE facturas SET estado = "pagada" WHERE id_factura = ?');
        $stmt->execute([$id_factura]);
    }

    echo 'Abono registrado correctamente.';
} catch (PDOException $e) {
    http_response_code(500);
    exit('Error al registrar abono: ' . $e->getMessage());
}
