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

// Refuerza la sesión: si el usuario está logueado

if (!isset($_SESSION['tipo'])) {
  if (isset($_SESSION['id_admin'])) {
    $_SESSION['tipo'] = 'admin';
  } elseif (isset($_SESSION['id_proveedor'])) {
    $_SESSION['tipo'] = 'proveedor';
  } elseif (isset($_SESSION['id_empresa'])) {
    $_SESSION['tipo'] = 'empresa';
  } elseif (isset($_SESSION['id_usuario'])) {
    $_SESSION['tipo'] = 'usuario';
  }
}

require_once "Base_de_datos/config.php";


$tipo_usuario = $_SESSION['tipo'] ?? null;
$id_usuario = null;

// Validar sesión activa y permisos
if (!$tipo_usuario) {
  include 'plantillas/no_autorizado.php';
  exit;
}

if ($tipo_usuario === 'proveedor') {
  $id_usuario = $_SESSION['id_proveedor'] ?? null;
  if (!$id_usuario) {
    include 'plantillas/no_autorizado.php';
    exit;
  }
} elseif ($tipo_usuario === 'empresa') {
  $id_usuario = $_SESSION['id_empresa'] ?? null;
  if (!$id_usuario) {
    include 'plantillas/no_autorizado.php';
    exit;
  }
} elseif ($tipo_usuario === 'usuario') {
  $empresa_activa = $_SESSION['empresa_activa'] ?? ($_SESSION['id_empresa'] ?? null);
  if (!$empresa_activa) {
    echo "Debe seleccionar una empresa activa para continuar.";
    exit;
  }
} elseif ($tipo_usuario === 'admin') {
  // Admin sin restricción
} else {
  include 'plantillas/no_autorizado.php';
  exit;
}

try {
  $condiciones = [];
  $parametros = [];

  $fechaInicio = $_GET['fecha_inicio'] ?? null;
  $fechaFin = $_GET['fecha_fin'] ?? null;

  if ($fechaInicio && $fechaFin) {
    $condiciones[] = "f.fecha_digital BETWEEN ? AND ?";
    $parametros[] = $fechaInicio;
    $parametros[] = $fechaFin . " 23:59:59";
  }

  $estado = $_GET['estado'] ?? null;
  if ($estado) {
    $condiciones[] = "f.estado = ?";
    $parametros[] = $estado;
  } else {
    // Por defecto, ocultar eliminadas
    $condiciones[] = "f.estado != 'eliminada'";
  }

  // Filtro por empresa emisora
  $empresa_filtro = $_GET['empresa'] ?? '';
  if ($empresa_filtro !== '') {
    $condiciones[] = "f.id_empresa = ?";
    $parametros[] = $empresa_filtro;
  }

  // Filtro por moneda
  $moneda_filtro = $_GET['moneda'] ?? '';
  if ($moneda_filtro !== '') {
    $condiciones[] = "f.moneda = ?";
    $parametros[] = $moneda_filtro;
  }

  // Purge de 15 días: solo php/limpiar_facturas.php (admin POST+CSRF). No DELETE al cargar GET.

  // --- Paginación ---
  $registros_por_pagina = 15;
  $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
  $offset = ($pagina - 1) * $registros_por_pagina;

  // Construir SQL base
  $sql_base = "
        FROM facturas f
        LEFT JOIN proveedores p ON f.id_proveedor = p.id
        LEFT JOIN empresas e ON f.id_empresa = e.id_empresa
    ";

  if ($tipo_usuario === 'proveedor') {
    $condiciones[] = "f.id_proveedor = ?";
    $parametros[] = $id_usuario;
  } elseif ($tipo_usuario === 'empresa') {
    $condiciones[] = "f.id_empresa = ?";
    $parametros[] = $id_usuario;
  } elseif ($tipo_usuario === 'usuario') {
    $condiciones[] = "f.id_empresa = ?";
    $parametros[] = $empresa_activa;
  }

  $where = !empty($condiciones) ? (" WHERE " . implode(" AND ", $condiciones)) : "";

  // 1. Obtener total de registros para paginación
  $sql_total = "SELECT COUNT(*) " . $sql_base . $where;
  $stmt_total = $pdo->prepare($sql_total);
  $stmt_total->execute($parametros);
  $total_registros = $stmt_total->fetchColumn();
  $total_paginas = ceil($total_registros / $registros_por_pagina);

  // 2. Obtener registros de la página actual
  $sql = "SELECT 
            f.id_factura,
            f.ncf,
            p.nombre_empresa AS proveedor,
            e.nombre AS empresa_emisora,
            f.fecha_digital,
            f.producto,
            f.total,
            f.estado,
            f.factura_fisica,
            f.soportes_adicionales,
            f.es_credito,
            f.fecha_vencimiento,
            f.moneda,
            (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura) AS total_pagado,
            (f.total - (SELECT COALESCE(SUM(a.monto),0) FROM abonos a WHERE a.id_factura = f.id_factura)) AS pendiente
        " . $sql_base . $where . " ORDER BY f.id_factura DESC LIMIT $registros_por_pagina OFFSET $offset";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($parametros);
  $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Obtener monedas disponibles dinámicamente
  $sql_monedas = "SELECT DISTINCT TRIM(UPPER(moneda)) AS moneda FROM facturas WHERE moneda IS NOT NULL AND moneda != '' ORDER BY moneda";
  $stmt_monedas = $pdo->prepare($sql_monedas);
  $stmt_monedas->execute();
  $monedas_disponibles = $stmt_monedas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("Error al obtener facturas: " . $e->getMessage());
}

// Determinar permisos de eliminación una vez, antes del bucle
$puedeEliminarGeneral = false;
if ($tipo_usuario === 'admin') {
  $puedeEliminarGeneral = true;
} elseif ($tipo_usuario === 'empresa' && isset($_SESSION['nombre_empresa'])) {
  // Para empresa, el permiso se evalúa por factura, pero podemos preparar la variable.
  // La comprobación final se hará en el bucle si es necesario.
  $puedeEliminarGeneral = true; // Habilitado para chequear por factura
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <title>Historial de Facturas</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="css/historial.css" />
  <link rel="stylesheet" href="historial/css/estilos.css" />
  <link rel="stylesheet" href="historial/css/modales.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="js/historial.js" defer></script>
  <script src="historial/js/facturas.js" defer></script>
</head>

<body>
  <div class="container" id="main-content">
    <div class="header-section">
      <div class="header-top">
        <h2 style="margin: 0;">Historial de Facturas</h2>
        <button type="button" class="btn-toggle-filters" id="btn-toggle-filters">
          <i class="fas fa-filter"></i>
          <span>Filtros</span>
          <i class="fas fa-chevron-down"></i>
        </button>
      </div>

      <form method="GET">
        <div class="filters-container show" id="filters-container">
          <div class="filter-grid">
            <div class="filter-item">
              <label>Fecha Inicio</label>
              <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($_GET['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="filter-item">
              <label>Fecha Fin</label>
              <input type="date" name="fecha_fin" value="<?= htmlspecialchars($_GET['fecha_fin'] ?? '') ?>">
            </div>
            <div class="filter-item">
              <label>Estado</label>
              <select name="estado">
                <option value="">Todos</option>
                <option value="pendiente" <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="aceptada" <?= ($_GET['estado'] ?? '') === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                <option value="pagada" <?= ($_GET['estado'] ?? '') === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                <option value="rechazada" <?= ($_GET['estado'] ?? '') === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Empresa</label>
              <select name="empresa">
                <option value="">Todas</option>
                <?php
                $empresasFiltro = $pdo->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($empresasFiltro as $emp): ?>
                  <option value="<?= htmlspecialchars($emp['id_empresa']) ?>" <?= (($_GET['empresa'] ?? '') == $emp['id_empresa']) ? 'selected' : '' ?>><?= htmlspecialchars($emp['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!empty($monedas_disponibles)): ?>
            <div class="filter-item">
              <label>Moneda</label>
              <select name="moneda">
                <option value="">Todas</option>
                <?php foreach ($monedas_disponibles as $moneda_option): 
                  $moneda_label = ($moneda_option['moneda'] === 'DOP') ? 'RD$ - DOP' : $moneda_option['moneda'];
                ?>
                  <option value="<?= htmlspecialchars($moneda_option['moneda']) ?>" <?= (($_GET['moneda'] ?? '') === $moneda_option['moneda']) ? 'selected' : '' ?>><?= htmlspecialchars($moneda_label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <div class="filter-actions">
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
            <?php if (!empty($_GET['fecha_inicio']) || !empty($_GET['fecha_fin']) || !empty($_GET['estado']) || !empty($_GET['empresa']) || !empty($_GET['moneda'])): ?>
              <a href="historial.php" class="btn-link">Limpiar filtros</a>
            <?php endif; ?>
            <a href="historial.php?estado=eliminada" class="btn-link">Ver eliminadas</a>
            <button type="button" id="btn-ver-rechazadas" class="btn-link" style="background:none;border:none;cursor:pointer;padding:0;">Ver rechazadas</button>
          </div>
        </div>
      </form>
    </div>

    <div class="table-section">
      <?php
        $queryReporte = $_GET;
        unset($queryReporte['pagina']);
        $reporteUrl = 'historial/reporte_config.php' . (!empty($queryReporte) ? ('?' . http_build_query($queryReporte)) : '');
      ?>
      <div class="table-header">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="buscadorFacturas" placeholder="Buscar en la tabla...">
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
          <?php if (isset($_GET['estado']) && $_GET['estado'] === 'eliminada' && ($tipo_usuario === 'admin' || $tipo_usuario === 'empresa')): ?>
          <button id="btn-eliminar-seleccionadas" class="btn-danger" style="display:none;">
            <i class="fas fa-trash-alt"></i>
            <span>Eliminar seleccionadas</span>
          </button>
          <?php endif; ?>
          <?php if ($tipo_usuario === 'admin' || $tipo_usuario === 'empresa' || ($tipo_usuario === 'usuario' && !empty($id_empresa))): ?>
          <button onclick="abrirModalCrearFactura()" class="btn-action">
            <i class="fas fa-plus"></i>
            <span>Crear Factura</span>
          </button>
          <?php endif; ?>
          <?php if ($tipo_usuario === 'proveedor'): ?>
          <button onclick="window.location.href='formulario_suplidor.php'" style="background:#5b8db8;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;transition:all 0.2s;">
            <i class="fas fa-plus"></i> Nueva Factura
          </button>
          <?php endif; ?>
          <form id="form-exportar-excel" action="export/exportar_historial_excel.php" method="post" style="margin:0;">
            <input type="hidden" name="ids_factura" id="ids_factura_export" value="">
            <button id="btn-exportar-excel" type="submit" class="btn-primary" style="display:none;">
              <i class="fas fa-file-excel"></i> Exportar a Excel
            </button>
          </form>
          <button onclick="window.location.href='<?= htmlspecialchars($reporteUrl) ?>'" class="btn-action">
            <i class="fas fa-file-export"></i>
            <span>Reporte de facturas</span>
          </button>
        </div>
      </div>

      <div class="table-wrapper">
        <table id="tablaFacturas">
          <thead>
            <tr>
              <?php if (isset($_GET['estado']) && $_GET['estado'] === 'eliminada' && ($tipo_usuario === 'admin' || $tipo_usuario === 'empresa')): ?>
              <th style="width: 40px; text-align: center;"><input type="checkbox" id="check-todos"></th>
              <?php endif; ?>
              <th style="width: 120px;">NCF</th>
              <th>Proveedor</th>
              <th>Empresa</th>
              <th style="width: 130px;">Fecha</th>
              <th>Descripción</th>
              <th style="width: 110px; text-align: right;">Total</th>
              <th style="width: 110px; text-align: right;">Pagado</th>
              <th style="width: 110px; text-align: right;">Pendiente</th>
              <th style="width: 100px;">Condición</th>
              <th style="width: 100px; text-align: center;">Estado</th>
              <th style="width: 80px; text-align: center;">Acciones</th>
            </tr>
          </thead>
          <tbody id="tablaFacturasBody">
            <?php
            $mostrarRechazadas = isset($_GET['ver_rechazadas']) && $_GET['ver_rechazadas'] == '1';
            foreach ($facturas as $factura):
              $estado = strtolower($factura['estado']);
              if ($mostrarRechazadas) {
                if ($estado !== 'rechazada') {
                  continue;
                }
              } else {
                if ($estado === 'rechazada') {
                  continue;
                }
              }

              $badgeEstado = match ($estado) {
                'aceptada' => 'badge-aceptada',
                'pagada'   => 'badge-pagada',
                'eliminada' => 'badge-eliminada',
                'rechazada' => 'badge-rechazada',
                default    => 'badge-pendiente',
              };
              $esPagada = ($estado === 'pagada');

              // Fecha con formato
              $fecha = !empty($factura['fecha_digital']) ? date('d/m/Y H:i', strtotime($factura['fecha_digital'])) : 'Sin fecha';
              $monto_total = number_format($factura['total'], 2);
              $total_pagado = number_format($factura['total_pagado'], 2);
              $pendiente = number_format($factura['pendiente'], 2);
              $moneda_raw = strtoupper(trim($factura['moneda'] ?? 'DOP'));
              $moneda_label = ($moneda_raw === 'DOP') ? 'RD$' : $moneda_raw . ' ';

              // Estado de pago con vencimiento
              $estadoPago = '';
              if ($factura['es_credito'] === 'si') {
                $fechaVenc = !empty($factura['fecha_vencimiento']) ? strtotime($factura['fecha_vencimiento']) : null;
                if ($fechaVenc && $fechaVenc < time()) {
                  $estadoPago = 'Crédito - vencido';
                } else {
                  $estadoPago = 'Crédito';
                }
              } else {
                $estadoPago = 'Contado';
              }
              // Permisos: solo admin o empresa dueña pueden eliminar/restaurar
              $puedeEliminar = false;
              if ($puedeEliminarGeneral) {
                if ($tipo_usuario === 'admin') {
                  $puedeEliminar = true;
                } elseif ($tipo_usuario === 'empresa' && isset($factura['id_empresa']) && $factura['id_empresa'] == $id_usuario) {
                  $puedeEliminar = true;
                }
              }
            ?>
              <tr>
                <?php if (isset($_GET['estado']) && $_GET['estado'] === 'eliminada' && ($tipo_usuario === 'admin' || $tipo_usuario === 'empresa')): ?>
                <td style="text-align: center;">
                  <?php 
                  // Debug: verificar que id_factura existe
                  if (empty($factura['id_factura'])) {
                    error_log("WARNING: Factura sin ID en historial.php");
                  }
                  ?>
                  <input type="checkbox" 
                         class="check-factura" 
                         data-id="<?= htmlspecialchars($factura['id_factura']) ?>"
                         data-debug-id="<?= $factura['id_factura'] ?>"
                         title="ID: <?= $factura['id_factura'] ?>">
                </td>
                <?php endif; ?>
                <td>
                  <button class="ncf-link" onclick="abrirDetalleFactura('<?= htmlspecialchars($factura['id_factura']) ?>')" title="Ver detalle">
                    <?= htmlspecialchars($factura['ncf'] ?? 'N/A') ?>
                  </button>
                </td>
                <td><?= htmlspecialchars($factura['proveedor'] ?? 'No asignado') ?></td>
                <td><?= htmlspecialchars($factura['empresa_emisora'] ?? 'No asignada') ?></td>
                <td><?= !empty($factura['fecha_digital']) ? date('d/m/Y', strtotime($factura['fecha_digital'])) : 'Sin fecha' ?></td>
                <td><?= htmlspecialchars(strlen($factura['producto']) > 50 ? substr($factura['producto'], 0, 47) . '...' : $factura['producto']) ?></td>
                <td style="text-align: right; font-weight: 500;"><?= $moneda_label ?><?= $monto_total ?></td>
                <td style="text-align: right; color:#059669; font-weight: 500;"><?= $moneda_label ?><?= $total_pagado ?></td>
                <td style="text-align: right; color:#dc2626; font-weight: 500;"><?= $moneda_label ?><?= $pendiente ?></td>
                <td><span style="font-size: 12px; color: #64748b;"><?= $estadoPago ?></span></td>
                <td style="text-align: center;"><span class="badge <?= $badgeEstado ?>"><?= ucfirst($estado) ?></span></td>
                <td style="text-align: center;">
                  <div class="dropdown-actions">
                    <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                      <?php if ($estado !== 'rechazada'): ?>
                        <a href="detalle_factura.php?id=<?= urlencode($factura['id_factura']) ?>" class="dropdown-item">
                          <i class="fas fa-file-invoice"></i> Ver detalles
                        </a>
                      <?php endif; ?>

                      <?php if (!empty($factura['factura_fisica'])): ?>
                        <?php
                        $url = htmlspecialchars($factura['factura_fisica']);
                        $es_pdf = (strtolower(pathinfo($url, PATHINFO_EXTENSION)) === 'pdf');
                        ?>
                        <a href="<?= $url ?>" target="_blank" class="dropdown-item">
                          <i class="fas fa-<?= $es_pdf ? 'file-pdf' : 'print' ?>"></i> Ver factura
                        </a>
                      <?php endif; ?>

                      <?php if (!empty($factura['soportes_adicionales'])): ?>
                        <?php
                        $url_soporte = htmlspecialchars($factura['soportes_adicionales']);
                        $es_pdf_soporte = (strtolower(pathinfo($url_soporte, PATHINFO_EXTENSION)) === 'pdf');
                        ?>
                        <a href="<?= $url_soporte ?>" target="_blank" class="dropdown-item">
                          <i class="fas fa-<?= $es_pdf_soporte ? 'file-pdf' : 'paperclip' ?>"></i> Ver soportes
                        </a>
                      <?php endif; ?>

                      <?php if (($tipo_usuario === 'admin' || !$esPagada) && $puedeEliminar): ?>
                        <div class="dropdown-divider"></div>
                        <a href="acciones/editar_factura.php?id=<?= urlencode($factura['id_factura']) ?>" class="dropdown-item">
                          <i class="fas fa-pen"></i> Editar
                        </a>
                      <?php endif; ?>

                      <?php if ($factura['es_credito'] === 'si' && $estado === 'aceptada' && $tipo_usuario !== 'proveedor'): ?>
                        <button class="dropdown-item abonar-btn"
                          data-id-factura="<?= htmlspecialchars($factura['id_factura']) ?>"
                          data-total="<?= htmlspecialchars($factura['total']) ?>">
                          <i class="fas fa-coins"></i> Abonar
                        </button>
                      <?php endif; ?>

                      <?php if ($estado === 'eliminada' && ($tipo_usuario === 'admin' || !$esPagada) && $puedeEliminar): ?>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" onclick="restaurarFactura('<?= addslashes($factura['id_factura']) ?>','<?= htmlspecialchars(tokenCSRF()) ?>')">
                          <i class="fas fa-undo"></i> Restaurar
                        </button>
                        <button class="dropdown-item danger" onclick="eliminarPermanente('<?= addslashes($factura['id_factura']) ?>','<?= htmlspecialchars(tokenCSRF()) ?>')">
                          <i class="fas fa-trash-alt"></i> Eliminar definitivamente
                        </button>
                      <?php elseif (($tipo_usuario === 'admin' || !$esPagada) && $puedeEliminar): ?>
                        <button class="dropdown-item danger" onclick="confirmarEliminar('<?= addslashes($factura['id_factura']) ?>','<?= htmlspecialchars(tokenCSRF()) ?>')">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <!-- Filas espaciadoras invisibles para dropdown -->
            <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
            <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
            <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
            <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
            <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Paginación -->
    <div id="paginacionContainer" style="margin: 20px 0; text-align: center; background: #fff; padding: 16px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
      <?php
      // Mantener filtros en la paginación
      $queryString = $_GET;
      unset($queryString['pagina']);
      $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');
      $qs = http_build_query($queryString);
      $url = $baseUrl . ($qs ? ("?" . $qs . "&") : "?");
      ?>
      <?php if ($pagina > 1): ?>
        <a href="<?= $url ?>pagina=<?= $pagina - 1 ?>" style="margin-right: 15px;">&laquo; Anterior</a>
      <?php endif; ?>
      Página <?= $pagina ?> de <?= $total_paginas ?>
      <?php if ($pagina < $total_paginas): ?>
        <a href="<?= $url ?>pagina=<?= $pagina + 1 ?>" style="margin-left: 15px;">Próxima &raquo;</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal Detalle Factura -->
  <?php include 'historial/modal/detalle_factura.php'; ?>

  <!-- Modal Abono -->
  <?php include 'historial/modal/abono.php'; ?>

  <!-- Modal Crear Factura (Solo Admin) -->
  <?php include 'historial/modal/crear_factura.php'; ?>

</body>

</html>