<?php
require_once "../../Base_de_datos/config.php";
session_start();
// Solo permitir acceso a administradores
$tipo_usuario = $_SESSION['tipo'] ?? null;
if ($tipo_usuario !== 'admin') {
    header('Location: ../../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Proveedores Registrados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        *::-webkit-scrollbar {
            display: none;
        }

        body {
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: #fff;
            padding: 24px 32px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .header-section h2 {
            margin: 0;
            font-size: 24px;
            color: #1e293b;
            font-weight: 600;
        }

        .table-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            overflow: visible;
        }

        .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            min-width: 250px;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 12px 10px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input:focus {
            outline: none;
            border-color: #5b8db8;
            box-shadow: 0 0 0 3px rgba(91, 141, 184, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            background: #5b8db8;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(91, 141, 184, 0.2);
        }

        .btn-action:hover {
            background: #4a7a9e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(91, 141, 184, 0.3);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action i {
            font-size: 15px;
        }

        @media (max-width: 968px) {
            .table-header {
                padding: 12px 16px;
            }

            .search-box {
                min-width: 100%;
                max-width: 100%;
                order: -1;
            }

            .action-buttons {
                width: 100%;
                justify-content: flex-start;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
            }
        }

        .table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 400px);
            min-height: 400px;
            position: relative;
            border-radius: 0 0 12px 12px;
        }

        .pagination-container {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-pagination {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            min-width: 38px;
        }

        .btn-pagination:hover:not(:disabled) {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-pagination:disabled {
            cursor: not-allowed;
            background: #5b8db8;
            color: white;
            border-color: #5b8db8;
        }

        .pagination-info {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            min-width: 150px;
            text-align: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid #f1f5f9;
            border-top-color: #5b8db8;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8fafc;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr.dropdown-open {
            z-index: 1000;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tr.spacer-row {
            visibility: hidden;
            pointer-events: none;
        }

        tr.spacer-row td {
            padding: 14px 16px;
            height: 50px;
        }

        /* Dropdown Menu */
        .dropdown-actions {
            position: relative;
            display: inline-block;
        }

        td:last-child {
            position: relative;
            overflow: visible;
        }

        .dropdown-toggle {
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 18px;
        }

        .dropdown-toggle:hover {
            background: #f1f5f9;
            color: #3b82f6;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 10000;
            padding: 8px 0;
            margin-top: 4px;
        }

        .dropdown-menu.show {
            display: block;
            animation: dropdownFadeIn 0.2s;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: block;
            padding: 10px 16px;
            color: #334155;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            font-size: 14px;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #3b82f6;
        }

        .dropdown-item.danger:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        .dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 4px 0;
        }
    </style>
</head>

<body style="background:transparent;">
    <div class="container">
        <div class="header-section">
            <h2>Proveedores Registrados</h2>
        </div>

        <div class="table-section">
            <div class="table-header">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorProveedores" placeholder="Buscar proveedores...">
                </div>
                <div class="action-buttons">
                    <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelDeUsuario/tipo_registro_proveedor.php')">
                        <i class="fas fa-plus"></i>
                        <span>Nuevo</span>
                    </button>
                    <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/cargar_proveedores_excel.php')">
                        <i class="fas fa-file-excel"></i>
                        <span>Cargar Excel</span>
                    </button>
                    <button class="btn-action" onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/modal/modalInvitarProveedor.php')">
                        <i class="fas fa-envelope"></i>
                        <span>Invitar</span>
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table id="tablaProveedores">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Empresa</th>
                            <th>RNC</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Usuario</th>
                            <th style="width: 80px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaProveedoresBody">
                        <?php
                        try {
                            // Paginación: 25 registros por página
                            $registros_por_pagina = 25;
                            $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
                            $offset = ($pagina - 1) * $registros_por_pagina;
                            
                            // Contar total de registros
                            $stmt_count = $pdo->query("SELECT COUNT(*) FROM proveedores");
                            $total_registros = $stmt_count->fetchColumn();
                            $total_paginas = ceil($total_registros / $registros_por_pagina);
                            
                            // Obtener registros de la página actual
                            $stmt = $pdo->query("SELECT * FROM proveedores ORDER BY id DESC LIMIT $registros_por_pagina OFFSET $offset");
                            $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($proveedores as $prov) {
                                $id = htmlspecialchars($prov['id'] ?? '');
                                $nombre_enc = urlencode($prov['nombre_empresa'] ?? '');
                                $nombre = ($prov['nombre_empresa'] ?? '') !== '' ? htmlspecialchars($prov['nombre_empresa']) : '<span style="color:#b0b0b0;">No asignado</span>';
                                $rnc = ($prov['rnc_cedula'] ?? '') !== '' ? htmlspecialchars($prov['rnc_cedula']) : '<span style="color:#b0b0b0;">No asignado</span>';
                                $correo = ($prov['correo'] ?? '') !== '' ? htmlspecialchars($prov['correo']) : '<span style="color:#b0b0b0;">No asignado</span>';
                                $telefono = ($prov['telefono'] ?? '') !== '' ? htmlspecialchars($prov['telefono']) : '<span style="color:#b0b0b0;">No asignado</span>';
                                $usuario = ($prov['usuario'] ?? '') !== '' ? htmlspecialchars($prov['usuario']) : '<span style="color:#b0b0b0;">No asignado</span>';

                                echo "<tr>";
                                echo "<td><strong>{$id}</strong></td>";
                                echo "<td>{$nombre}</td>";
                                echo "<td>{$rnc}</td>";
                                echo "<td>{$correo}</td>";
                                echo "<td>{$telefono}</td>";
                                echo "<td>{$usuario}</td>";
                                echo "<td style='text-align: center;'>
                                        <div class='dropdown-actions'>
                                            <button class='dropdown-toggle' onclick='toggleDropdown(this)'>
                                                <i class='fas fa-ellipsis-v'></i>
                                            </button>
                                            <div class='dropdown-menu'>
                                                <a href='../perfil_proveedor.php?id={$prov['id']}' class='dropdown-item'>
                                                    <i class='fas fa-address-card'></i> Ver perfil
                                                </a>
                                                <a href='../ver_archivos_proveedor.php?id={$prov['id']}' class='dropdown-item'>
                                                    <i class='fas fa-folder-open'></i> Ver archivos
                                                </a>
                                                <div class='dropdown-divider'></div>
                                                <a href='../editar_proveedor.php?id={$prov['id']}' class='dropdown-item'>
                                                    <i class='fas fa-edit'></i> Editar
                                                </a>
                                                <form method='post' action='../eliminar_proveedor.php' onsubmit='return confirm(\"¿Eliminar este proveedor?\");'><input type='hidden' name='id' value='{$prov['id']}'><input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'><button type='submit' class='dropdown-item danger'><i class='fas fa-trash'></i> Eliminar</button></form>
                                                <div class='dropdown-divider'></div>
                                                <button class='dropdown-item' onclick=\"window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/cargar_facturas_excel.php?id_proveedor={$id}&nombre={$nombre_enc}')\">
                                                    <i class='fas fa-file-excel'></i> Importar Facturas
                                                </button>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="pagination-container" id="paginacionContainer">
                <?php if (isset($total_paginas) && $total_paginas > 1): ?>
                    <div class="pagination-info">
                        Página <?= $pagina ?> de <?= $total_paginas ?> (<?= $total_registros ?> registros)
                    </div>
                    <div>
                        <?php if ($pagina > 1): ?>
                            <button onclick="buscarProveedores(1)" class="btn-pagination" title="Primera página">«</button>
                            <button onclick="buscarProveedores(<?= $pagina - 1 ?>)" class="btn-pagination">&laquo; Anterior</button>
                        <?php endif; ?>
                        
                        <?php 
                        $inicio = max(1, $pagina - 2);
                        $fin = min($total_paginas, $pagina + 2);
                        
                        if ($inicio > 1) {
                            echo '<button onclick="buscarProveedores(1)" class="btn-pagination">1</button>';
                            if ($inicio > 2) {
                                echo '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                            }
                        }
                        
                        for ($i = $inicio; $i <= $fin; $i++) {
                            if ($i == $pagina) {
                                echo '<button class="btn-pagination" disabled style="background:#5b8db8;color:white;">' . $i . '</button>';
                            } else {
                                echo '<button onclick="buscarProveedores(' . $i . ')" class="btn-pagination">' . $i . '</button>';
                            }
                        }
                        
                        if ($fin < $total_paginas) {
                            if ($fin < $total_paginas - 1) {
                                echo '<span style="color:#94a3b8;padding:0 4px;">...</span>';
                            }
                            echo '<button onclick="buscarProveedores(' . $total_paginas . ')" class="btn-pagination">' . $total_paginas . '</button>';
                        }
                        
                        if ($pagina < $total_paginas): ?>
                            <button onclick="buscarProveedores(<?= $pagina + 1 ?>)" class="btn-pagination">Próxima &raquo;</button>
                            <button onclick="buscarProveedores(<?= $total_paginas ?>)" class="btn-pagination" title="Última página">»</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Dropdown Menu
        function toggleDropdown(btn) {
            const dropdown = btn.nextElementSibling;
            const wasOpen = dropdown.classList.contains('show');
            const currentRow = btn.closest('tr');

            document.querySelectorAll('.dropdown-menu.show').forEach(d => {
                d.classList.remove('show');
                const row = d.closest('tr');
                if (row) row.classList.remove('dropdown-open');
            });

            if (!wasOpen) {
                dropdown.classList.add('show');
                if (currentRow) currentRow.classList.add('dropdown-open');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-actions')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(d => {
                    d.classList.remove('show');
                    const row = d.closest('tr');
                    if (row) row.classList.remove('dropdown-open');
                });
            }
        });

        const tableWrapper = document.querySelector('.table-wrapper');
        if (tableWrapper) {
            tableWrapper.addEventListener('scroll', function() {
                document.querySelectorAll('.dropdown-menu.show').forEach(d => {
                    d.classList.remove('show');
                    const row = d.closest('tr');
                    if (row) row.classList.remove('dropdown-open');
                });
            });
        }

        // Buscador con búsqueda en tiempo real
        const buscador = document.getElementById('buscadorProveedores');
        let timerBusqueda;
        
        buscador.addEventListener('input', (e) => {
            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(() => {
                buscarProveedores(1);
            }, 300);
        });
        
        async function buscarProveedores(pagina = 1) {
            const termino = buscador.value.trim();
            const paginacionContainer = document.getElementById('paginacionContainer');
            
            try {
                const response = await fetch(`/SystemSuplidor/PanelAdmin/ajax/buscar_proveedores.php?q=${encodeURIComponent(termino)}&pagina=${pagina}`);
                const data = await response.json();
                
                if (data.success) {
                    // Actualizar tabla
                    const tbody = document.getElementById('tablaProveedoresBody');
                    tbody.innerHTML = data.html;
                    
                    // Agregar filas espaciadoras
                    for (let i = 0; i < 5; i++) {
                        const tr = document.createElement('tr');
                        tr.className = 'spacer-row';
                        tr.innerHTML = '<td colspan="7">&nbsp;</td>';
                        tbody.appendChild(tr);
                    }
                    
                    // Actualizar paginación
                    paginacionContainer.innerHTML = data.paginacion;
                } else {
                    console.error('Error:', data.error);
                }
            } catch (error) {
                console.error('Error en la búsqueda:', error);
            }
        }
    </script>
</body>

</html>