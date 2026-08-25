<?php
session_start();
require_once "../Base_de_datos/config.php";

header('Content-Type: application/json');

$id_factura = $_GET['id'] ?? null;
if (!$id_factura) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de factura requerido']);
    exit;
}

// Validar que el usuario tiene sesión
$tipo_usuario = $_SESSION['tipo'] ?? null;
$nombre_usuario = $_SESSION['usuario'] ?? null;
$id_empresa_sesion = $_SESSION['id_empresa'] ?? null;
if (!$nombre_usuario && !$id_empresa_sesion && !$tipo_usuario) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
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

    if (!$factura) {
        http_response_code(404);
        echo json_encode(['error' => 'Factura no encontrada']);
        exit;
    }

    if ($tipo_usuario === 'proveedor') {
        $id_proveedor = $_SESSION['id_proveedor'] ?? null;
        if (!$id_proveedor || (string)$factura['id_proveedor'] !== (string)$id_proveedor) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
    } elseif ($tipo_usuario === 'empresa' || $tipo_usuario === 'usuario') {
        $id_empresa = $_SESSION['empresa_activa'] ?? ($_SESSION['id_empresa'] ?? null);
        if (!$id_empresa || (string)$factura['id_empresa'] !== (string)$id_empresa) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
    } elseif ($tipo_usuario !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    // Obtener abonos
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
