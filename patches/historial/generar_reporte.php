<?php
session_start();
require_once __DIR__ . "/../Base_de_datos/config.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo_usuario = $_SESSION['tipo'] ?? null;
if (!$tipo_usuario) {
    include __DIR__ . "/../plantillas/no_autorizado.php";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reporte_config.php');
    exit;
}

function wizard_report_fail(string $publicMessage, int $code = 400): void {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Report-File: 0');
        header('Cache-Control: no-store');
    }
    echo $publicMessage;
    exit;
}

$campos = $_POST['campos'] ?? [];
if (empty($campos) || !is_array($campos)) {
    wizard_report_fail('Seleccione al menos un campo.');
}

$incluir_encabezados = isset($_POST['incluir_encabezados']);
$allowed_limits = [0, 25, 50, 100];
$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 25;
if (!in_array($limit, $allowed_limits, true)) {
    $limit = 25;
}
$orden = $_POST['orden'] ?? 'f.id_factura DESC';

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
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$fecha_fin = $_POST['fecha_fin'] ?? null;
$estado = $_POST['estado'] ?? null;
$empresa = $_POST['empresa'] ?? null;
$moneda = $_POST['moneda'] ?? null;

// Mapear campos seleccionables a expresiones SQL
$mapa_campos = [
        'ncf' => 'f.ncf AS ncf',
        'proveedor' => 'p.nombre_empresa AS proveedor',
        'empresa_emisora' => 'e.nombre AS empresa_emisora',
        'fecha_digital' => 'f.fecha_digital AS fecha_digital',
        'producto' => 'f.producto AS producto',
        'total' => 'f.total AS total',
        'total_pagado' => '(SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura) AS total_pagado',
        'pendiente' => '(f.total - (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura)) AS pendiente',
        'estado' => 'f.estado AS estado',
        'moneda' => 'f.moneda AS moneda',
        'es_credito' => 'f.es_credito AS es_credito'
    ];

    $select_parts = [];
foreach ($campos as $c) {
    if (isset($mapa_campos[$c])) $select_parts[] = $mapa_campos[$c];
}

if (empty($select_parts)) wizard_report_fail('Campos inválidos.');

// Determinar aliases/result keys por campo seleccionado
$alias_keys = [];
foreach ($campos as $c) {
    if (!isset($mapa_campos[$c])) continue;
    $expr = $mapa_campos[$c];
    if (stripos($expr, ' AS ') !== false) {
        $parts = preg_split('/\s+AS\s+/i', $expr);
        $alias_keys[$c] = trim($parts[1]);
    } else {
        // si es f.campo, tomar la parte después del punto
        if (strpos($expr, '.') !== false) {
            $alias_keys[$c] = trim(substr($expr, strpos($expr, '.') + 1));
        } else {
            $alias_keys[$c] = $c;
        }
    }
}

$sql_base = "FROM facturas f\nLEFT JOIN proveedores p ON f.id_proveedor = p.id\nLEFT JOIN empresas e ON f.id_empresa = e.id_empresa";

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

$where = !empty($condiciones) ? (" WHERE " . implode(" AND ", $condiciones)) : "";

// Construir SQL
$select_sql = implode(", ", $select_parts);
$sql = "SELECT " . $select_sql . " " . $sql_base . $where . " ORDER BY " . $orden;
if ($limit > 0) {
    $sql .= " LIMIT " . $limit;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    wizard_report_fail('No se pudo generar el reporte. Intenta nuevamente.', 500);
}

// Intentar usar PhpSpreadsheet si está presente
$useXlsx = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        $useXlsx = true;
    }
}

$filename_base = 'reporte_facturas_' . date('Ymd_His');

if ($useXlsx) {
    // Generar XLSX
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $col = 1;
    if ($incluir_encabezados) {
        foreach ($campos as $c) {
            // map better header
            $headers_map = [
                'ncf' => 'NCF', 'proveedor' => 'Proveedor', 'empresa_emisora' => 'Empresa',
                'fecha_digital' => 'Fecha', 'producto' => 'Descripción', 'total' => 'Total', 'total_pagado' => 'Pagado',
                'pendiente' => 'Pendiente', 'estado' => 'Estado', 'moneda' => 'Moneda', 'es_credito' => 'Condición'
            ];
            $cellCoord = Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->setCellValue($cellCoord, $headers_map[$c] ?? $c);
            $col++;
        }
    }

    $rowIndex = $incluir_encabezados ? 2 : 1;
    foreach ($rows as $r) {
        $col = 1;
        foreach ($campos as $c) {
            $key = $alias_keys[$c] ?? $c;
            $cellCoord = Coordinate::stringFromColumnIndex($col) . $rowIndex;
            $value = $r[$key] ?? '';
            // Forzar que siempre muestre 'Crédito'
            if ($c === 'es_credito') {
                $value = 'Crédito';
            }
            $sheet->setCellValue($cellCoord, $value);
            $col++;
        }
        $rowIndex++;
    }

    // Enviar archivo
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename_base.'.xlsx"');
    header('X-Report-File: 1');
    header('Cache-Control: no-store');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    // Fallback CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename_base.'.csv"');
    header('X-Report-File: 1');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    if ($incluir_encabezados) {
        $headers = [];
        $map_headers = [
            'ncf' => 'NCF', 'proveedor' => 'Proveedor', 'empresa_emisora' => 'Empresa',
            'fecha_digital' => 'Fecha', 'producto' => 'Descripción', 'total' => 'Total', 'total_pagado' => 'Pagado',
            'pendiente' => 'Pendiente', 'estado' => 'Estado', 'moneda' => 'Moneda', 'es_credito' => 'Condición'
        ];
        foreach ($campos as $c) $headers[] = $map_headers[$c] ?? $c;
        fputcsv($out, $headers);
    }
    foreach ($rows as $r) {
        $line = [];
        foreach ($campos as $c) {
            $key = $alias_keys[$c] ?? $c;
            $value = $r[$key] ?? '';
            // Forzar que siempre muestre 'Crédito'
            if ($c === 'es_credito') {
                $value = 'Crédito';
            }
            $line[] = $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

?>