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
require_once "Base_de_datos/config.php";
require_once __DIR__ . "/php/autorizacion_facturas.php";

$id_factura = $_GET['id'] ?? null;
if (!$id_factura) {
    die("ID de factura no especificado.");
}

$tipo_usuario = ss_require_tipo_sesion();
if (!$tipo_usuario) {
    http_response_code(404);
    die("Factura no encontrada.");
}

// Obtener datos de la factura
$stmt = $pdo->prepare("SELECT f.*, p.nombre_empresa AS proveedor, e.nombre AS empresa_emisora FROM facturas f
    LEFT JOIN proveedores p ON f.id_proveedor = p.id
    LEFT JOIN empresas e ON f.id_empresa = e.id_empresa
    WHERE f.id_factura = ?");
$stmt->execute([$id_factura]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$factura || !ss_puede_ver_factura($factura)) {
    http_response_code(404);
    die("Factura no encontrada.");
}

// Si es crédito, mostrar abonos. Si es contado, solo mostrar pagada.
$abonos = [];
$total_abonado = 0;
$pendiente = 0;
if ($factura['es_credito'] === 'si') {
    $stmt = $pdo->prepare("SELECT * FROM abonos WHERE id_factura = ? ORDER BY fecha_abono ASC");
    $stmt->execute([$id_factura]);
    $abonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($abonos as $ab) {
        $total_abonado += floatval($ab['monto']);
    }
    $pendiente = floatval($factura['total']) - $total_abonado;
} else {
    $total_abonado = floatval($factura['total']);
    $pendiente = 0;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Factura</title>
    <link rel="stylesheet" href="css/historial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .detalle-box {
            background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #0001; padding: 2em; max-width: 700px; margin: 2em auto;
        }
        .detalle-box h2 { margin-top: 0; }
        .abonos-table { width: 100%; border-collapse: collapse; margin-top: 2em; font-size: 1em; }
        .abonos-table th, .abonos-table td { border: 1px solid #ddd; padding: 0.6em 1em; text-align: left; }
        .abonos-table th { background: #f0f4fa; }
        .abonos-table td a { color: #007bff; text-decoration: underline; }
        .resumen { margin-top: 2em; font-size: 1.1em; }
        @media (max-width: 600px) {
            .detalle-box {
                padding: 0.7em 0.2em; max-width: 99vw; font-size: 0.98em;
            }
            .detalle-box h2 { font-size: 1.1em; }
            .abonos-table, .abonos-table thead, .abonos-table tbody, .abonos-table th, .abonos-table td, .abonos-table tr {
                display: block;
            }
            .abonos-table thead { display: none; }
            .abonos-table tr { margin-bottom: 1.1em; border-bottom: 2px solid #eee; }
            .abonos-table td {
                border: none;
                position: relative;
                padding-left: 48%;
                min-height: 2.2em;
                font-size: 0.98em;
                box-sizing: border-box;
            }
            .abonos-table td:before {
                position: absolute;
                top: 0.6em; left: 0.7em;
                width: 45%;
                white-space: nowrap;
                font-weight: bold;
                color: #555;
                font-size: 0.97em;
                content: attr(data-label);
            }
        }
    </style>
</head>
<body>
<div class="detalle-box">
    <h2>Detalle de Factura #<?= htmlspecialchars($factura['id_factura']) ?></h2>
    <p><b>Proveedor:</b> <?= htmlspecialchars($factura['proveedor'] ?? 'No asignado') ?></p>
    <p><b>Empresa:</b> <?= htmlspecialchars($factura['empresa_emisora'] ?? 'No asignada') ?></p>
    <p><b>Producto:</b> <?= htmlspecialchars($factura['producto']) ?></p>
    <p><b>Fecha emisión:</b> <?= htmlspecialchars($factura['fecha_digital']) ?></p>
    <p><b>Monto total:</b> $<?= number_format($factura['total'], 2) ?></p>
    <p><b>Condición de pago:</b> <?= $factura['es_credito'] === 'si' ? 'Crédito' : 'Contado' ?></p>
    <p><b>Estado:</b> <?= ucfirst($factura['estado']) ?></p>
    <hr>
    <?php if ($factura['es_credito'] === 'si'): ?>
      <h3>Movimientos de pago (Abonos)</h3>
    <table class="abonos-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Monto</th>
                <th>Comentario</th>
                <th>Fecha</th>
                <th>Comprobante</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($abonos) === 0): ?>
            <tr><td colspan="5" style="text-align:center;">Sin abonos registrados</td></tr>
        <?php else: foreach ($abonos as $i => $ab): ?>
            <tr>
                <td data-label="#"><?= $i+1 ?></td>
                <td data-label="Monto">$<?= number_format($ab['monto'], 2) ?></td>
                <td data-label="Comentario"><?= htmlspecialchars($ab['comentario']) ?></td>
                <td data-label="Fecha"><?= htmlspecialchars($ab['fecha_abono']) ?></td>
                <td data-label="Comprobante">
                  <?php if (!empty($ab['comprobante'])): ?>
                    <a href="<?= htmlspecialchars($ab['comprobante']) ?>" target="_blank"><i class="fas fa-file-pdf"></i> Ver</a>
                  <?php else: ?>
                    <span style="color:#888;">No adjunto</span>
                  <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php else: ?>
      <h3>Factura al contado</h3>
      <div style="margin:1.5em 0; color:#2e7d32; font-weight:bold; font-size:1.1em;">
        Esta factura fue pagada al contado. No hay movimientos de abono.
      </div>
    <?php endif; ?>
    <div class="resumen">
        <b>Total abonado:</b> $<?= number_format($total_abonado, 2) ?><br>
        <b>Saldo pendiente:</b> $<?= number_format($pendiente, 2) ?>
    </div>
    <div style="margin-top:2em;">
        <a href="historial.php" style="color:#007bff;"><i class="fas fa-arrow-left"></i> Volver al historial</a>
    </div>
</div>
</body>
</html>
