<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once "../../Base_de_datos/config.php";
session_start();
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}
$usuarios = $pdo->query("SELECT u.id, u.usuario, u.nombre, u.correo, u.activo, GROUP_CONCAT(e.nombre SEPARATOR ', ') AS empresas
FROM usuarios u
LEFT JOIN usuarios_empresas ue ON u.id = ue.id_usuario
LEFT JOIN empresas e ON ue.id_empresa = e.id_empresa
GROUP BY u.id
ORDER BY u.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>  
    <meta charset="UTF-8">
    <title>Usuarios del sistema</title>
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
            margin: 0 0 20px 0;
            font-size: 24px;
            color: #1e293b;
            font-weight: 600;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
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
        }

        .search-box {
            position: relative;
            max-width: 300px;
            flex: 1;
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

        .table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 300px);
            min-height: 400px;
            position: relative;
            border-radius: 0 0 12px 12px;
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

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-activo {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactivo {
            background: #fee2e2;
            color: #991b1b;
        }

        .empresa-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin: 2px 4px 2px 0;
        }

        .btn-eliminar-empresa {
            padding: 0 3px;
            border: none;
            background: none;
            vertical-align: middle;
            line-height: 1;
            cursor: pointer;
        }

        .btn-eliminar-empresa i {
            font-size: 0.85em;
            color: #b00;
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

        #modal-asignar-empresas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        #modal-asignar-empresas > form {
            background: white;
            padding: 2em;
            border-radius: 10px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body style="background:transparent;">
    <div class="container">
        <div class="header-section">
            <h2>Usuarios Registrados</h2>
            
            <?php
            if (isset($_SESSION['mensaje'])) {
                echo '<div class="alert success">' . htmlspecialchars($_SESSION['mensaje']) . '</div>';
                unset($_SESSION['mensaje']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="alert error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>
        </div>

        <div class="table-section">
            <div class="table-header">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorUsuarios" placeholder="Buscar en la tabla...">
                </div>
                <button onclick="window.parent.cargarEnIframe('/SystemSuplidor/PanelAdmin/modal/modalNuevoUsuario.php')" style="background:#5b8db8;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;transition:all 0.2s;">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>

            <div class="table-wrapper">
                <table id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th style="width: 100px;">Estado</th>
                            <th>Empresas asignadas</th>
                            <th style="width: 80px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php
                                $activo = isset($u['activo']) ? (int)$u['activo'] : 1;
                                $badgeEstado = $activo ? 'badge-activo' : 'badge-inactivo';
                                $estadoTexto = $activo ? 'Activo' : 'Inactivo';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['id']) ?></strong></td>
                                <td><?= htmlspecialchars($u['usuario']) ?></td>
                                <td><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['correo']) ?></td>
                                <td style="text-align: center;"><span class="badge <?= $badgeEstado ?>"><?= $estadoTexto ?></span></td>
                                <td>
                                    <?php
                                    if (!empty($u['empresas'])) {
                                        $empresasArr = explode(',', $u['empresas']);
                                        foreach ($empresasArr as $nombreEmpresa) {
                                            $nombreEmpresaOriginal = trim($nombreEmpresa); 
                                            if ($nombreEmpresaOriginal !== '') {
                                                $nombreEmpresaSafe = str_replace(["\n", "\r"], " ", $nombreEmpresaOriginal);
                                                $onclick = 'eliminarEmpresaUsuario(' . (int)$u['id'] . ', \'' . addslashes($nombreEmpresaOriginal) . '\')';
                                                echo '<span class="empresa-tag">' . htmlspecialchars($nombreEmpresaSafe) .
                                                    ' <button class="btn-eliminar-empresa" title="Quitar empresa" onclick="' . $onclick . '">
                                                        <i class="fa fa-times"></i>
                                                    </button></span> ';
                                            }
                                        }
                                    } else {
                                        echo '<span style="color:#888">Sin empresas</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="dropdown-actions">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="../editar_usuario.php?id=<?= $u['id'] ?>" class="dropdown-item">
                                                <i class="fas fa-pen"></i> Editar
                                            </a>
                                            <button onclick="asignarEmpresas(<?= $u['id'] ?>)" class="dropdown-item">
                                                <i class="fas fa-building"></i> Asignar Empresas
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Filas espaciadoras invisibles para dropdown -->
                        <tr class="spacer-row"><td colspan="7">&nbsp;</td></tr>
                        <tr class="spacer-row"><td colspan="7">&nbsp;</td></tr>
                        <tr class="spacer-row"><td colspan="7">&nbsp;</td></tr>
                        <tr class="spacer-row"><td colspan="7">&nbsp;</td></tr>
                        <tr class="spacer-row"><td colspan="7">&nbsp;</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modal-asignar-empresas" style="display:none;"></div>

    <script>
        var SS_CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
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

        // Buscador
        const buscador = document.getElementById('buscadorUsuarios');
        buscador?.addEventListener('input', (e) => {
            const filtro = e.target.value.toLowerCase();
            document.querySelectorAll('#tablaUsuarios tbody tr:not(.spacer-row)').forEach(fila => {
                fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
            });
        });

        // Alerta lateral
        function mostrarAlertaLateralJS(mensaje, tipo = 'info', opciones = null) {
            var color = {
                info: '#3498db',
                success: '#27ae60',
                error: '#e74c3c',
                warning: '#f39c12'
            }[tipo] || '#3498db';
            var id = 'alerta-lateral-' + Math.random().toString(36).substr(2, 9);
            var div = document.createElement('div');
            div.id = id;
            div.style = "position:fixed;top:30px;right:30px;z-index:99999;min-width:260px;max-width:400px;padding:18px 24px;background:" + color + ";color:#fff;font-size:1.1em;border-radius:8px;box-shadow:0 2px 12px #0002;opacity:0;transition:opacity 0.4s,transform 0.4s;transform:translateY(-20px);";
            div.innerHTML = mensaje;
            if (opciones && Array.isArray(opciones)) {
                var btns = document.createElement('div');
                btns.style = 'margin-top:16px;text-align:right;';
                opciones.forEach(function(op){
                    var b = document.createElement('button');
                    b.textContent = op.text;
                    b.style = 'margin-left:8px;padding:6px 18px;border:none;border-radius:5px;background:#fff;color:' + color + ';font-weight:bold;cursor:pointer;font-size:1em;';
                    b.onclick = function(){
                        if (op.onClick) op.onClick();
                        if (div.parentNode) div.parentNode.removeChild(div);
                    };
                    btns.appendChild(b);
                });
                div.appendChild(btns);
            }
            document.body.appendChild(div);
            setTimeout(function() {
                div.style.opacity = '1';
                div.style.transform = 'translateY(0)';
            }, 100);
            if (!opciones) {
                setTimeout(function() {
                    div.style.opacity = '0';
                    div.style.transform = 'translateY(-20px)';
                }, 3500);
                setTimeout(function() {
                    if (div.parentNode) div.parentNode.removeChild(div);
                }, 4000);
            }
        }

        function eliminarEmpresaUsuario(idUsuario, nombreEmpresa) {
            mostrarAlertaLateralJS(
                '¿Seguro que deseas quitar la empresa "' + nombreEmpresa + '" de este usuario?',
                'warning',
                [
                    {
                        text: 'Sí',
                        onClick: function() {
                            fetch('../eliminar_empresa_usuario.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id_usuario=' + encodeURIComponent(idUsuario) + '&nombre_empresa=' + encodeURIComponent(nombreEmpresa) + '&csrf_token=' + encodeURIComponent(SS_CSRF)
                            })
                            .then(r => r.json())
                            .then(result => {
                                    mostrarAlertaLateralJS(result.msg, result.ok ? 'success' : 'error');
                                    if(result.ok) setTimeout(function(){ location.reload(); }, 1200);
                            })
                            .catch(() => mostrarAlertaLateralJS('Error de conexión.', 'error'));
                        }
                    },
                    {
                        text: 'No',
                        onClick: function(){}
                    }
                ]
            );
        }

        function asignarEmpresas(idUsuario) {
            fetch('modalAsignarEmpresas.php?id_usuario=' + idUsuario)
                .then(r => r.text())
                .then(html => {
                    const modalDiv = document.getElementById('modal-asignar-empresas');
                    modalDiv.innerHTML = html;
                    modalDiv.style.display = 'flex';

                    modalDiv.onclick = function(e) {
                        if (e.target === modalDiv) {
                            modalDiv.style.display = 'none';
                        }
                    };

                    // Adjuntar handler al formulario dentro del modal (con pequeño retardo para asegurar DOM)
                    setTimeout(() => {
                        const form = modalDiv.querySelector('#form-asignar-empresas');
                        if (form) {
                            form.onsubmit = async function(e) {
                                e.preventDefault();
                                const data = new FormData(form);
                                let result = { ok: false, msg: 'Error inesperado.' };
                                try {
                                    const res = await fetch('procesarAsignarEmpresas.php', { method:'POST', body: data });
                                    let text = await res.text();
                                    try {
                                        result = JSON.parse(text);
                                    } catch (jsonErr) {
                                        result = { ok: false, msg: 'Respuesta inválida del servidor.' };
                                    }
                                } catch (err) {
                                    result = { ok: false, msg: 'Error de conexión: ' + err };
                                }
                                alert(result.msg);
                                if(result.ok) {
                                    modalDiv.style.display = 'none';
                                    location.reload();
                                }
                            };
                        }
                    }, 300);
                })
                .catch(() => mostrarAlertaLateralJS('Error al cargar el modal.', 'error'));
        }
    </script>
</body>
</html>
