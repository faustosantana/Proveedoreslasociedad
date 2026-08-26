<?php
session_start();
require_once __DIR__ . "/../Base_de_datos/config.php";
require_once __DIR__ . "/../php/autorizacion_facturas.php";

header('Content-Type: application/json; charset=UTF-8');

$tipo_usuario = ss_require_tipo_sesion();
if (!$tipo_usuario) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $campos = $_GET['campos'] ?? [];
    if (empty($campos) || !is_array($campos)) {
        throw new Exception('Seleccione al menos un campo.');
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
    $orden = $_GET['orden'] ?? 'f.id_factura DESC';

    $ordenes_validos = [
        'f.id_factura DESC',
        'f.id_factura ASC',
        'f.total DESC',
        'f.total ASC'
    ];
    if (!in_array($orden, $ordenes_validos, true)) {
        $orden = 'f.id_factura DESC';
    }

    // Filtros
    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
    $fecha_fin = $_GET['fecha_fin'] ?? null;
    $estado = $_GET['estado'] ?? null;
    $empresa = $_GET['empresa'] ?? null;
    $moneda = $_GET['moneda'] ?? null;

    // Mapear campos
    $mapa_campos = [
        'ncf' => 'f.ncf',
        'proveedor' => 'p.nombre_empresa AS proveedor',
        'empresa_emisora' => 'e.nombre AS empresa_emisora',
        'fecha_digital' => 'f.fecha_digital',
        'producto' => 'f.producto',
        'total' => 'f.total',
        'total_pagado' => '(SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura) AS total_pagado',
        'pendiente' => '(f.total - (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura)) AS pendiente',
        'estado' => 'f.estado',
        'moneda' => 'f.moneda',
        'es_credito' => 'f.es_credito'
    ];

    $select_parts = [];
    $headers_labels = [
        'ncf' => 'NCF',
        'proveedor' => 'Proveedor',
        'empresa_emisora' => 'Empresa',
        'fecha_digital' => 'Fecha',
        'producto' => 'Descripción',
        'total' => 'Total',
        'total_pagado' => 'Pagado',
        'pendiente' => 'Pendiente',
        'es_credito' => 'Condición',
        'estado' => 'Estado',
        'moneda' => 'Moneda'
    ];

    $headers = [];
    $field_keys = [];
    $alias_keys = [];

    foreach ($campos as $c) {
        if (!isset($mapa_campos[$c])) continue;
        $select_parts[] = $mapa_campos[$c];
        $headers[] = $headers_labels[$c] ?? $c;
        $field_keys[] = $c;

        // Obtener alias/key
        $expr = $mapa_campos[$c];
        if (stripos($expr, ' AS ') !== false) {
            $parts = preg_split('/\s+AS\s+/i', $expr);
            $alias_keys[$c] = trim($parts[1]);
        } else {
            if (strpos($expr, '.') !== false) {
                $alias_keys[$c] = trim(substr($expr, strpos($expr, '.') + 1));
            } else {
                $alias_keys[$c] = $c;
            }
        }
    }

    if (empty($select_parts)) {
        throw new Exception('Campos inválidos.');
    }

    $sql_base = "FROM facturas f
    LEFT JOIN proveedores p ON f.id_proveedor = p.id
    LEFT JOIN empresas e ON f.id_empresa = e.id_empresa";

    $condiciones = [];
    $parametros = [];

    if ($fecha_inicio) {
        $condiciones[] = "f.fecha_digital >= ?";
        $parametros[] = $fecha_inicio . " 00:00:00";
    }
    if ($fecha_fin) {
        $condiciones[] = "f.fecha_digital <= ?";
        $parametros[] = $fecha_fin . " 23:59:59";
    }
    if ($estado) {
        $condiciones[] = "f.estado = ?";
        $parametros[] = $estado;
    }
    if ($empresa) {
        $condiciones[] = "f.id_empresa = ?";
        $parametros[] = $empresa;
    }
    if ($moneda) {
        $condiciones[] = "TRIM(UPPER(f.moneda)) = ?";
        $parametros[] = strtoupper(trim($moneda));
    }

    ss_aplicar_scope_facturas($condiciones, $parametros);

    $where = !empty($condiciones) ? (" WHERE " . implode(" AND ", $condiciones)) : "";

    // Contar total
    $sql_count = "SELECT COUNT(*) " . $sql_base . $where;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($parametros);
    $total = $stmt_count->fetchColumn();

    // Obtener datos (máximo 10 para previsualización)
    $select_sql = implode(", ", $select_parts);
    $sql = "SELECT " . $select_sql . " " . $sql_base . $where . " ORDER BY " . $orden . " LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    // Detectar formato
    $hasPhpSpreadsheet = file_exists(__DIR__ . '/../vendor/autoload.php');
    if ($hasPhpSpreadsheet) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $hasPhpSpreadsheet = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    }
    $format = $hasPhpSpreadsheet ? 'xlsx' : 'csv';

    // Formatear filas: convertir a arreglo ordenado y mapear campos especiales
    $formatted_rows = [];
    foreach ($rows as $r) {
        $line = [];
        for ($i = 0; $i < count($field_keys); $i++) {
            $c = $field_keys[$i];
            $value = $r[$i] ?? '';
            // Forzar que siempre muestre 'Crédito'
            if ($c === 'es_credito') {
                $value = 'Crédito';
            }
            $line[] = $value;
        }
        $formatted_rows[] = $line;
    }

    echo json_encode([
        'headers' => $headers,
        'rows' => $formatted_rows,
        'total' => $total,
        'format' => $format,
        'preview_count' => count($formatted_rows)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
