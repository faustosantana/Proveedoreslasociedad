<?php
session_start();
require_once "../Base_de_datos/config.php";
require_once __DIR__ . "/autorizacion_facturas.php";

header('Content-Type: application/json');

if (!ss_require_tipo_sesion()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_factura = $_GET['id'] ?? null;
if (!$id_factura) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de factura requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT f.id_factura, f.ncf, f.producto, f.total, f.estado, f.es_credito,
            f.fecha_digital, f.fecha_vencimiento, f.factura_fisica, f.moneda, f.id_empresa, f.id_proveedor,
            p.nombre_empresa AS proveedor,
            e.nombre AS empresa_emisora
        FROM facturas f
        LEFT JOIN proveedores p ON f.id_proveedor = p.id
        LEFT JOIN empresas e ON f.id_empresa = e.id_empresa
        WHERE f.id_factura = ?
    ");
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura || !ss_puede_ver_factura($factura)) {
        http_response_code(404);
        echo json_encode(['error' => 'Factura no encontrada']);
        exit;
    }

    $abonos = [];
    $total_abonado = 0;
    if ($factura['es_credito'] === 'si') {
        $stmtAb = $pdo->prepare("SELECT * FROM abonos WHERE id_factura = ? ORDER BY fecha_abono ASC");
        $stmtAb->execute([$id_factura]);
        $abonos = $stmtAb->fetchAll(PDO::FETCH_ASSOC);
        foreach ($abonos as $ab) {
            $total_abonado += floatval($ab['monto']);
        }
    } else {
        $total_abonado = floatval($factura['total']);
    }

    $pendiente = floatval($factura['total']) - $total_abonado;

    echo json_encode([
        'factura'       => $factura,
        'abonos'        => $abonos,
        'total_abonado' => $total_abonado,
        'pendiente'     => $pendiente,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos']);
}
