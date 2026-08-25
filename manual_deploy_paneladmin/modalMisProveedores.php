<?php
require_once "../../Base_de_datos/config.php";
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../../index.php');
    exit;
}

$tipo_usuario = $_SESSION['tipo'] ?? null;
$id_empresa = $_SESSION['id_empresa'] ?? null;
$esEmpresa = ($tipo_usuario === 'empresa') || ($tipo_usuario === 'usuario' && !empty($id_empresa));
$esSoloEmpresa = ($tipo_usuario === 'empresa');
$puedeGestionarProveedores = ($tipo_usuario === 'empresa') || ($tipo_usuario === 'usuario' && !empty($id_empresa));

$misProveedores = [];
if ($esEmpresa && !empty($id_empresa)) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.nombre_empresa, p.correo, p.telefono, p.activo, p.rnc_cedula
            FROM proveedores p
            INNER JOIN empresa_proveedor ep ON p.id = ep.id_proveedor
            WHERE ep.id_empresa = ?
            ORDER BY p.nombre_empresa ASC
        ");
        $stmt->execute([$id_empresa]);
        $misProveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Proveedores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #333; }

        .page-wrap { padding: 24px 28px; }

        /* Header */
        .header-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 20px 24px;
            margin-bottom: 16px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .header-top h2 {
            font-size: 22px;
            color: #1e293b;
            font-weight: 700;
        }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action {
            background: #5b8db8;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-action:hover { background: #4a7a9e; transform: translateY(-1px); }

        /* Filtros */
        .btn-toggle-filters {
            display: flex; align-items: center; gap: 8px;
            background: #f1f5f9; color: #475569;
            padding: 10px 16px; border: none; border-radius: 8px;
            font-weight: 500; font-size: 14px; cursor: pointer; transition: all 0.2s;
        }
        .btn-toggle-filters:hover { background: #e2e8f0; }
        .filters-container { max-height: 0; overflow: hidden; transition: max-height 0.3s ease, opacity 0.3s ease; opacity: 0; }
        .filters-container.show { max-height: 200px; opacity: 1; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; padding-top: 16px; }
        .filter-item { display: flex; flex-direction: column; gap: 5px; }
        .filter-item label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
        .filter-item input, .filter-item select {
            padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; background: #fff; transition: all 0.2s;
        }
        .filter-item input:focus, .filter-item select:focus {
            outline: none; border-color: #5b8db8; box-shadow: 0 0 0 3px rgba(91,141,184,0.12);
        }

        /* Tabla */
        .table-section {
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden;
        }
        .table-header {
            padding: 14px 20px; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .search-box { flex: 1; max-width: 300px; position: relative; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input {
            width: 100%; padding: 9px 12px 9px 38px;
            border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.2s;
        }
        .search-box input:focus { outline: none; border-color: #5b8db8; box-shadow: 0 0 0 3px rgba(91,141,184,0.1); }
        .table-info { font-size: 13px; color: #64748b; }

        .table-wrapper { overflow-x: auto; max-height: calc(100vh - 330px); min-height: 300px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
        thead { position: sticky; top: 0; z-index: 5; background: #f8fafc; }
        th {
            padding: 12px 16px; text-align: left; font-weight: 600;
            color: #475569; font-size: 12px; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }

        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-activo { background: #d1fae5; color: #065f46; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }

        /* Acciones */
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 6px;
            color: #64748b; transition: all 0.2s;
            background: transparent; border: none; cursor: pointer; font-size: 14px; text-decoration: none;
        }
        .action-btn:hover { background: #f1f5f9; color: #3b82f6; }
        .action-btn.danger:hover { background: #fee2e2; color: #dc2626; }

        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
        .empty-state p { font-size: 15px; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="header-section">
        <div class="header-top">
            <h2><i class="fas fa-users" style="color:#5b8db8;margin-right:10px;"></i>Mis Proveedores</h2>
            <div class="header-actions">
                <button class="btn-toggle-filters" id="btnFiltros">
                    <i class="fas fa-filter"></i> Filtros <i class="fas fa-chevron-down"></i>
                </button>
                <?php if ($puedeGestionarProveedores): ?>
                <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelDeUsuario/tipo_registro_proveedor.php')">
                    <i class="fas fa-user-plus"></i> Nuevo Proveedor
                </button>
                <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/cargar_proveedores_excel.php')">
                    <i class="fas fa-file-excel"></i> Carga Masiva
                </button>
                <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/modal/modalInvitarProveedor.php')">
                    <i class="fas fa-paper-plane"></i> Invitar Proveedor
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="filters-container" id="filtrosContainer">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Buscar por nombre</label>
                    <input type="text" id="filtroNombre" placeholder="Nombre de empresa...">
                </div>
                <div class="filter-item">
                    <label>Estado</label>
                    <select id="filtroEstado">
                        <option value="">Todos</option>
                        <option value="1">Activo</option>
                        <option value="0">Pendiente</option>
                        <option value="-1">Rechazado</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>RNC / Cédula</label>
                    <input type="text" id="filtroRnc" placeholder="RNC o cédula...">
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="table-section">
        <div class="table-header">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscador" placeholder="Buscar en tabla...">
            </div>
            <span class="table-info" id="countInfo"><?= count($misProveedores) ?> proveedor(es)</span>
        </div>
        <div class="table-wrapper">
            <table id="tablaProveedores">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre Empresa</th>
                        <th>RNC / Cédula</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyProveedores">
                <?php if (empty($misProveedores)): ?>
                    <tr><td colspan="7" style="padding:0;">
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>No tienes proveedores registrados aún.</p>
                        </div>
                    </td></tr>
                <?php else: foreach ($misProveedores as $i => $prov):
                    $estadoVal = (int)$prov['activo'];
                    $badgeClass = match($estadoVal) { 1 => 'badge-activo', -1 => 'badge-inactivo', default => 'badge-pendiente' };
                    $badgeText  = match($estadoVal) { 1 => 'Activo', -1 => 'Rechazado', default => 'Pendiente' };
                ?>
                    <tr data-filtrable="1" data-nombre="<?= htmlspecialchars(strtolower($prov['nombre_empresa'])) ?>" data-rnc="<?= htmlspecialchars(strtolower($prov['rnc_cedula'] ?? '')) ?>" data-estado="<?= $estadoVal ?>">
                        <td style="color:#94a3b8;font-size:12px;"><?= $i + 1 ?></td>
                        <td style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($prov['nombre_empresa']) ?></td>
                        <td style="font-family:monospace;"><?= htmlspecialchars($prov['rnc_cedula'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($prov['correo']) ?></td>
                        <td><?= htmlspecialchars($prov['telefono']) ?></td>
                        <td style="text-align:center;"><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="../perfil_proveedor.php?id=<?= $prov['id'] ?>" class="action-btn" title="Ver perfil" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/perfil_proveedor.php?id=<?= $prov['id'] ?>');return false;"><i class="fas fa-address-card"></i></a>
                            <?php if ($puedeGestionarProveedores): ?>
                            <a href="../editar_proveedor.php?id=<?= $prov['id'] ?>" class="action-btn" title="Editar" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/editar_proveedor.php?id=<?= $prov['id'] ?>');return false;"><i class="fas fa-edit"></i></a>
                            <a href="../cargar_facturas_excel.php?id_proveedor=<?= $prov['id'] ?>&nombre=<?= urlencode($prov['nombre_empresa']) ?>" class="action-btn" title="Cargar Facturas" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/cargar_facturas_excel.php?id_proveedor=<?= $prov['id'] ?>&nombre=<?= urlencode($prov['nombre_empresa']) ?>');return false;"><i class="fas fa-file-invoice"></i></a>
                            <?php endif; ?>
                            <?php if ($tipo_usuario !== 'usuario' && $puedeGestionarProveedores): ?>
                            <form method="post" action="../eliminar_proveedor.php" style="display:inline;" onsubmit="return confirm('¿Eliminar este proveedor?');"><input type="hidden" name="id" value="<?= (int) $prov['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" class="action-btn danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    // Toggle filtros
    const btnFiltros = document.getElementById('btnFiltros');
    const filtrosContainer = document.getElementById('filtrosContainer');
    btnFiltros.addEventListener('click', () => {
        filtrosContainer.classList.toggle('show');
        btnFiltros.querySelector('.fa-chevron-down').style.transform =
            filtrosContainer.classList.contains('show') ? 'rotate(180deg)' : '';
    });

    // Filtrado combinado
    const filas = () => document.querySelectorAll('#tbodyProveedores tr[data-filtrable]');

    function aplicarFiltros() {
        const busq   = document.getElementById('buscador').value.toLowerCase();
        const nombre = document.getElementById('filtroNombre').value.toLowerCase();
        const estado = document.getElementById('filtroEstado').value;
        const rnc    = document.getElementById('filtroRnc').value.toLowerCase();
        let visible  = 0;

        document.querySelectorAll('#tbodyProveedores tr').forEach(tr => {
            const texto = tr.innerText.toLowerCase();
            const estadoTr = tr.dataset.estado ?? '';
            const rncTr    = (tr.dataset.rnc ?? '').toLowerCase();
            const nombreTr = (tr.dataset.nombre ?? '').toLowerCase();

            const ok = texto.includes(busq)
                && nombreTr.includes(nombre)
                && rncTr.includes(rnc)
                && (estado === '' || estadoTr === estado);

            tr.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        document.getElementById('countInfo').textContent = visible + ' proveedor(es)';
    }

    ['buscador','filtroNombre','filtroEstado','filtroRnc'].forEach(id => {
        document.getElementById(id).addEventListener('input', aplicarFiltros);
    });
</script>
</body>
</html>
