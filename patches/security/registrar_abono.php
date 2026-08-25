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

$tipo_usuario = $_SESSION['tipo'] ?? null;
if (!$tipo_usuario) {
    http_response_code(401);
    exit('No autorizado');
}

if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
    http_response_code(403);
    exit('Token de seguridad inválido.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}


$id_factura = $_POST['id_factura_abono'] ?? null;
$monto_abono = $_POST['monto_abono'] ?? null;
$comentario = trim($_POST['comentario_abono'] ?? '');

// Validar archivo comprobante
$link_comprobante = null;
if (isset($_FILES['comprobante_abono']) && $_FILES['comprobante_abono']['error'] === UPLOAD_ERR_OK) {
    $permitidos = ['application/pdf', 'image/jpeg', 'image/png'];
    $archivo = $_FILES['comprobante_abono'];
    if (!in_array($archivo['type'], $permitidos)) {
        http_response_code(400);
        exit('Tipo de archivo no permitido.');
    }
    if ($archivo['size'] > 5 * 1024 * 1024) { // 5MB
        http_response_code(400);
        exit('El archivo es demasiado grande. Máx 5MB.');
    }
    $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = uniqid('abono_') . '_' . time() . '.' . $ext;
    $ruta_destino = '../archivos/comprobantes_de_pagos/' . $nombre_archivo;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        http_response_code(500);
        exit('Error al guardar el comprobante.');
    }
    $link_comprobante = 'archivos/comprobantes_de_pagos/' . $nombre_archivo;
} else {
    http_response_code(400);
    exit('Debe adjuntar un comprobante de pago.');
}

// Procesar documento adicional (opcional)
$link_documento_adicional = null;
if (isset($_FILES['documento_adicional_abono']) && $_FILES['documento_adicional_abono']['error'] === UPLOAD_ERR_OK) {
    $permitidos = ['application/pdf', 'image/jpeg', 'image/png'];
    $archivo2 = $_FILES['documento_adicional_abono'];
    if (!in_array($archivo2['type'], $permitidos)) {
        http_response_code(400);
        exit('Tipo de documento adicional no permitido.');
    }
    if ($archivo2['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        exit('El documento adicional es demasiado grande. Máx 5MB.');
    }
    $ext2 = pathinfo($archivo2['name'], PATHINFO_EXTENSION);
    $nombre_archivo2 = uniqid('abono_doc2_') . '_' . time() . '.' . $ext2;
    $ruta_destino2 = '../archivos/comprobantes_de_pagos/' . $nombre_archivo2;
    if (!move_uploaded_file($archivo2['tmp_name'], $ruta_destino2)) {
        http_response_code(500);
        exit('Error al guardar el documento adicional.');
    }
    $link_documento_adicional = 'archivos/comprobantes_de_pagos/' . $nombre_archivo2;
}

if (!$id_factura || !is_numeric($monto_abono) || $monto_abono <= 0) {
    http_response_code(400);
    exit('Datos de abono inválidos.');
}
// Redondear a 2 decimales
$monto_abono = round(floatval($monto_abono), 2);

try {
    // Verificar que la factura exista y sea a crédito
    $stmt = $pdo->prepare('SELECT total, es_credito, estado FROM facturas WHERE id_factura = ?');
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura || $factura['es_credito'] !== 'si') {
        http_response_code(400);
        exit('Factura no válida para abonos.');
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
