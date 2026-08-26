<?php
/**
 * Descarga autenticada de documentos ligados a factura/abono.
 * No acepta rutas del navegador. No bloquear /archivos/ todavía.
 */
session_start();
require_once __DIR__ . '/../Base_de_datos/config.php';
require_once __DIR__ . '/autorizacion_facturas.php';

if (!ss_require_tipo_sesion()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No autorizado';
    exit;
}

$tipo = $_GET['tipo'] ?? 'factura';
$base = realpath(__DIR__ . '/../archivos');
if ($base === false) {
    http_response_code(404);
    exit('No encontrado');
}

$rel = null;

if ($tipo === 'factura') {
    $id_factura = $_GET['id_factura'] ?? null;
    if (!$id_factura) {
        http_response_code(400);
        exit('Solicitud inválida');
    }
    $stmt = $pdo->prepare('SELECT id_factura, id_proveedor, id_empresa, factura_fisica FROM facturas WHERE id_factura = ?');
    $stmt->execute([$id_factura]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !ss_puede_ver_factura($row) || empty($row['factura_fisica'])) {
        http_response_code(404);
        exit('No encontrado');
    }
    $rel = $row['factura_fisica'];
} elseif ($tipo === 'comprobante') {
    $id_abono = $_GET['id_abono'] ?? null;
    if (!$id_abono || !ctype_digit((string) $id_abono)) {
        http_response_code(400);
        exit('Solicitud inválida');
    }
    $stmt = $pdo->prepare(
        'SELECT a.id_abono, a.comprobante, f.id_factura, f.id_proveedor, f.id_empresa
         FROM abonos a
         INNER JOIN facturas f ON f.id_factura = a.id_factura
         WHERE a.id_abono = ?'
    );
    $stmt->execute([$id_abono]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !ss_puede_ver_factura($row)) {
        http_response_code(404);
        exit('No encontrado');
    }
    $rel = $row['comprobante'] ?? '';
    if ($rel === '') {
        http_response_code(404);
        exit('No encontrado');
    }
} else {
    http_response_code(400);
    exit('Solicitud inválida');
}

$rel = str_replace('\\', '/', (string) $rel);
$rel = ltrim($rel, '/');
if (strpos($rel, '..') !== false) {
    http_response_code(404);
    exit('No encontrado');
}

$full = realpath(__DIR__ . '/../' . $rel);
if ($full === false || strpos($full, $base) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit('No encontrado');
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
        $detected = finfo_file($f, $full);
        finfo_close($f);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($full) . '"');
header('Cache-Control: private, no-store');
readfile($full);
exit;
