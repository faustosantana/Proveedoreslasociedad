<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */

// Aumentar límites de PHP para archivos grandes
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '22M');
@ini_set('memory_limit', '64M');
@ini_set('max_execution_time', '120');

require_once '../Base_de_datos/config.php';
session_start();

if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
    http_response_code(403);
    die('Token de seguridad inválido.');
}

function guardarArchivo($campo)
{
    $dir = '../archivos/';
    $limiteMB = 5; // Límite en megabytes
    $limiteBytes = $limiteMB * 1024 * 1024;

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    // Si no se envió archivo o no se seleccionó ninguno, retornar null
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Si hay un error diferente a "no file", lanzar excepción
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_TMP_DIR => 'No se encuentra el directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
        ];
        $mensaje = $errores[$_FILES[$campo]['error']] ?? 'Error desconocido al subir el archivo';
        throw new Exception("Error en '{$campo}': {$mensaje}");
    }

    if ($_FILES[$campo]['size'] > $limiteBytes) {
        throw new Exception("El archivo '{$campo}' excede el tamaño máximo permitido de {$limiteMB}MB.");
    }

    $extension = strtolower((string) pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    $permitidas = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];
    if (!isset($permitidas[$extension])) {
        throw new Exception("Tipo de archivo no permitido.");
    }
    if (!function_exists('finfo_open')) {
        throw new Exception("No se pudo validar el archivo.");
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$campo]['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mime, $permitidas[$extension], true)) {
        throw new Exception("El contenido del archivo no es válido.");
    }

    $nombre = uniqid('', true) . '.' . $extension;
    $ruta = $dir . $nombre;

    if (move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta)) {
        return 'archivos/' . $nombre;
    } else {
        throw new Exception("Error al guardar el archivo '{$campo}' en el servidor.");
    }
}

try {
    // Verificar si PHP truncó los datos por exceder post_max_size
    $content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $post_max_size = ini_get('post_max_size');
    $post_max_bytes = (int)$post_max_size * 1024 * 1024;
    
    if ($content_length > $post_max_bytes) {
        throw new Exception("Los archivos enviados exceden el tamaño máximo permitido. Por favor, envíe archivos más pequeños (máximo 10MB por archivo).");
    }
    
    if (empty($_POST)) {
        throw new Exception("No se recibieron datos del formulario.");
    }

    if (!isset($_SESSION['id_proveedor'])) {
        throw new Exception("Sesión inválida. Por favor inicie sesión como proveedor.");
    }

    // === DATOS DEL FORMULARIO ===
    $id_factura = uniqid('fact_');
    $id_proveedor = $_SESSION['id_proveedor'];
    $id_empresa = $_POST['id_empresa'] ?? null;
    $producto = $_POST['producto'] ?? null;
    $moneda = $_POST['moneda'] ?? null;
    
    // NCF opcional
    $aplica_ncf = isset($_POST['aplica_ncf']);
    $ncf = $aplica_ncf ? ($_POST['ncf'] ?? null) : null;

    $fecha_digital = $_POST['fecha_emision_digital'] ?? null;
    $fecha_fisica = $_POST['fecha_emision_fisica'] ?? null;
    $subtotal = !empty($_POST['subtotal']) ? $_POST['subtotal'] : null;
    $impuestos = !empty($_POST['impuestos']) ? $_POST['impuestos'] : null;
    $total = $_POST['total'] ?? null;
    $correo_factura = $_POST['correo'] ?? '';

    // Nuevos campos agregados
    $es_credito = $_POST['es_credito'] ?? 'si'; // Forzado a crédito por defecto
    $fecha_vencimiento = null; // Eliminada la obligatoriedad

    // Validar fecha de emisión física no mayor a la actual
    if (!empty($fecha_fisica)) {
        $fecha_fisica_ts = strtotime($fecha_fisica);
        $hoy_ts = strtotime(date('Y-m-d'));
        if ($fecha_fisica_ts > $hoy_ts) {
            throw new Exception("La fecha de emisión no puede ser mayor a la fecha actual.");
        }
    }
    
    // Validar NCF solo si aplica
    if ($aplica_ncf) {
        if (empty($ncf)) {
            throw new Exception("Debe ingresar el número de comprobante (NCF).");
        }
    } else {
        $ncf = null; // Guardar como null
    }

    // Bloqueo del 27 al 31 de cada mes
    $diaActual = (int)date('d');
    if ($diaActual >= 27 || $diaActual == 31) {
        throw new Exception("El envío de facturas está bloqueado del día 27 al 31 de cada mes.");
    }
    
    if (!$id_empresa || !$producto) {
        throw new Exception("Faltan datos obligatorios en la factura.");
    }


    $pdo->beginTransaction();

    // Verificar NCF duplicado para el mismo proveedor
    if ($aplica_ncf && !empty($ncf)) {
        $stmtNcf = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE ncf = ? AND id_proveedor = ? AND estado != 'rechazada'");
        $stmtNcf->execute([$ncf, $id_proveedor]);
        if ($stmtNcf->fetchColumn() > 0) {
            throw new Exception("El número de comprobante (NCF) ya fue registrado por este proveedor.");
        }
    }

    // === ARCHIVOS ===
    $factura_fisica = guardarArchivo('factura_fisica');
    $soportes_adicionales = guardarArchivo('soportes_adicionales');
    
    // Validar que la factura física obligatoria esté presente
    if (empty($factura_fisica)) {
        throw new Exception("El archivo de factura física es obligatorio.");
    }

    // === INSERTAR FACTURA ===
    $stmt = $pdo->prepare("INSERT INTO facturas (
        id_factura, id_proveedor, id_empresa, producto, moneda, ncf,
        fecha_digital, fecha_fisica, subtotal, impuestos, total,
        factura_fisica, soportes_adicionales,
        es_credito, fecha_vencimiento, estado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')");

    $stmt->execute([
        $id_factura,
        $id_proveedor,
        $id_empresa,
        $producto,
        $moneda,
        $ncf,
        $fecha_digital,
        $fecha_fisica,
        $subtotal,
        $impuestos,
        $total,
        $factura_fisica,
        $soportes_adicionales,
        $es_credito,
        $fecha_vencimiento
    ]);

    $pdo->commit();
    registrarAuditoria($pdo, 'Crear factura', 'Factura', $id_factura, "Factura creada por proveedor ID: $id_proveedor, empresa ID: $id_empresa");

    // === ENVIAR CORREOS DE NUEVA FACTURA DIRECTAMENTE ===
    require_once '../correo/correo_nueva_factura.php';
    // Enviar al proveedor
    enviarCorreoNuevaFactura($id_empresa, $id_factura);
    // Enviar a la empresa
    enviarCorreoNuevaFacturaEmpresa($id_empresa, $id_factura);

    // === ÉXITO - REDIRIGIR A HISTORIAL DE FACTURAS ===
    header("Location: ../historial.php?exito=1");
    exit;
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Mostrar el error pero permitir seguir editando el formulario sin limpiar los datos
    echo "<script>\n";
    echo "if (typeof mostrarAlertaLateral === 'function') { mostrarAlertaLateral('" . addslashes($e->getMessage()) . "', 'error'); } else {\n";
    echo "  window.onload = function() {\n";
    echo "    var alerta = document.createElement('div');\n";
    echo "    alerta.style.position = 'fixed';\n";
    echo "    alerta.style.top = '30px';\n";
    echo "    alerta.style.right = '30px';\n";
    echo "    alerta.style.zIndex = '99999';\n";
    echo "    alerta.style.minWidth = '280px';\n";
    echo "    alerta.style.maxWidth = '350px';\n";
    echo "    alerta.style.padding = '18px 24px';\n";
    echo "    alerta.style.borderRadius = '8px';\n";
    echo "    alerta.style.fontSize = '16px';\n";
    echo "    alerta.style.fontWeight = 'bold';\n";
    echo "    alerta.style.boxShadow = '0 2px 12px rgba(0,0,0,0.15)';\n";
    echo "    alerta.style.background = '#c0392b';\n";
    echo "    alerta.style.color = '#fff';\n";
    echo "    alerta.textContent = '" . addslashes($e->getMessage()) . "';\n";
    echo "    alerta.style.opacity = '1';\n";
    echo "    document.body.appendChild(alerta);\n";
    echo "    setTimeout(function(){ alerta.style.opacity = '0'; }, 3000);\n";
    echo "  }\n";
    echo "}\n";
    echo "</script>\n";
    // Incluir el formulario nuevamente (sin redirigir)
    include '../formulario_suplidor.php';
    exit;
}
