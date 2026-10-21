    <?php

    /**
     * -----------------------------------------------
     * Proyecto: Plataforma de Suplidores - PilarDevs
     * Autor: Chadwin Pilar
     * Fecha: 2025-09-18
     * Refactorizado: 2025-12-26
     * Uso interno – No distribuir sin autorización
     * -----------------------------------------------
     */

    require_once "../Base_de_datos/config.php";
    require_once __DIR__ . '/modulos/GestionArchivos.php';
    require_once __DIR__ . '/modulos/DocumentoIdentidad.php';
    require_once __DIR__ . '/modulos/ValidacionesFormulario.php';
    require_once __DIR__ . '/modulos/ProveedorDB.php';
    require_once __DIR__ . '/../correo/correo_validacion_proveedor.php';
    require_once __DIR__ . '/../correo/correo_nuevo_proveedor.php';

    // Inicializar gestor de archivos
    $gestionArchivos = new GestionArchivos('../uploads/legal_docs/');

    // Función para enviar un error como alerta SweetAlert y detener el script limpiamente
    function mostrarError($mensaje)
    {
        require_once __DIR__ . '/../plantillas/template/alert.php';
        mostrarAlertaLateral('Error: ' . $mensaje, 'error');
        echo "<script>setTimeout(function(){ window.history.back(); }, 2000);</script>";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // DEBUG: Mostrar el contenido de $_POST para verificar datos enviados
        file_put_contents(__DIR__ . '/debug_post.log', print_r($_POST, true));
        $tipo_bancario = $_POST['tipo_bancario'] ?? 'local';

        // Recibir datos del formulario
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        $tipo_proveedor_general = $_POST['tipo_proveedor_general'] ?? '';
        $proveedor = trim($_POST['proveedor'] ?? '');
        $rnc = trim($_POST['rnc'] ?? '');
        $tipo_proveedor = $_POST['tipo_proveedor'] ?? '';
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = filter_var(trim($_POST['correo'] ?? ''), FILTER_VALIDATE_EMAIL);
        $direccion_fiscal = trim($_POST['direccion_fiscal'] ?? '');
        $empresasSeleccionadas = $_POST['empresas'] ?? [];

        // Datos bancarios locales
        $nombre_banco = trim($_POST['nombre_banco'] ?? '');
        $tipo_cuenta = trim($_POST['tipo_cuenta'] ?? '');
        $nombre_titular = trim($_POST['nombre_titular'] ?? '');
        $rnc_titular = trim($_POST['rnc_titular'] ?? '');
        $numero_cuenta = trim($_POST['numero_cuenta'] ?? '');

        // Datos bancarios extranjeros
        $foreign_name = trim($_POST['foreign_name'] ?? '');
        $foreign_tax_id = trim($_POST['foreign_tax_id'] ?? '');
        $foreign_swift_code = trim($_POST['foreign_swift_code'] ?? '');
        $foreign_aba_no = trim($_POST['foreign_aba_no'] ?? '');
        $foreign_bank_address = trim($_POST['foreign_bank_address'] ?? '');
        $foreign_bank_name = trim($_POST['foreign_bank_name'] ?? '');
        $foreign_intermediary_bank = trim($_POST['foreign_intermediary_bank'] ?? '');
        $foreign_account_numbers = trim($_POST['foreign_account_numbers'] ?? '');

        // Datos del representante
        $representante_nombre = trim($_POST['representante_nombre'] ?? '');
        $representante_telefono = trim($_POST['representante_telefono'] ?? '');

    // Incluir plantilla de alerta lateral solo si no se ha mostrado antes
    if (!defined('ALERTA_LATERAL_MOSTRADA')) {
        require_once __DIR__ . '/../plantillas/template/alert.php';
        define('ALERTA_LATERAL_MOSTRADA', true);
        echo "<div id='alerta-lateral-fijo' style='position:fixed; top:30px; right:30px; z-index:99999; min-width:260px; max-width:400px; padding:18px 24px; background:#3498db; color:#fff; font-size:1.1em; border-radius:8px; box-shadow:0 2px 12px #0002; opacity:1; transition:opacity 0.4s, transform 0.4s; transform:translateY(0);'>Formulario cargando...</div>";
    }

        // ===== VALIDACIONES =====
        
        // Validar usuario y contraseña
        $validacionUsuario = ValidacionesFormulario::validarUsuario($usuario, $contrasena);
        if (!$validacionUsuario['valido']) {
            mostrarError($validacionUsuario['error']);
        }

        // Validar datos del proveedor
        $validacionProveedor = ValidacionesFormulario::validarProveedor([
            'tipo_proveedor_general' => $tipo_proveedor_general,
            'proveedor' => $proveedor,
            'telefono' => $telefono,
            'correo' => $correo,
            'direccion_fiscal' => $direccion_fiscal,
            'rnc' => $rnc
        ]);
        if (!$validacionProveedor['valido']) {
            mostrarError($validacionProveedor['error']);
        }

        // Validar empresas seleccionadas
        $validacionEmpresas = ValidacionesFormulario::validarEmpresas($empresasSeleccionadas);
        if (!$validacionEmpresas['valido']) {
            mostrarError($validacionEmpresas['error']);
        }

        // Validar archivos
        $validacionArchivos = ValidacionesFormulario::validarArchivos($_FILES, $tipo_bancario, $tipo_proveedor_general);
        if (!$validacionArchivos['valido']) {
            mostrarError($validacionArchivos['error']);
        }

        // Validar representante (solo para empresas, no para persona individual)
        if ($tipo_proveedor_general !== 'persona_individual') {
            $validacionRepresentante = ValidacionesFormulario::validarRepresentante([
                'nombre' => $representante_nombre,
                'telefono' => $representante_telefono
            ]);
            if (!$validacionRepresentante['valido']) {
                file_put_contents(__DIR__ . '/debug_representante.log', 
                    date('Y-m-d H:i:s') . "\nCampos vacíos representante: " . 
                    implode(', ', $validacionRepresentante['campos_vacios']) . "\n", FILE_APPEND);
                mostrarError($validacionRepresentante['error']);
            }
        }

        // Doble control backend: formato + normalización (no confiar solo en JS)
        $errDocumento = DocumentoIdentidad::mensajeFormato($rnc, $tipo_proveedor_general);
        if ($errDocumento !== null) {
            mostrarError($errDocumento);
        }

        // Validar datos únicos (usuario, correo, RNC/Cédula normalizado)
        $validacionUnicos = ValidacionesFormulario::validarUnicos($pdo, $usuario, $correo, $rnc, $tipo_proveedor_general);
        if (!$validacionUnicos['valido']) {
            mostrarError($validacionUnicos['error']);
        }

        // ===== SUBIR ARCHIVOS =====
        $archivosSubidos = $gestionArchivos->procesarArchivosRegistro($_FILES, $tipo_bancario);
        
        // Validar que los archivos obligatorios se hayan subido correctamente
        if ($tipo_proveedor_general === 'persona_individual') {
            // Persona Individual: Solo requiere rnc_cedula y cert_bancaria del Panel 3
            $archivosObligatorios = [
                $archivosSubidos['rnc_cedula'],
                $archivosSubidos['cert_bancaria']
            ];
        } else {
            // Empresa: Requiere registro_mercantil, rnc_cedula, cert_itbis
            $archivosObligatorios = [
                $archivosSubidos['registro_mercantil'],
                $archivosSubidos['rnc_cedula'],
                $archivosSubidos['cert_itbis']
            ];
            // Agregar cert_bancaria_local si es cuenta local
            if ($tipo_proveedor_general === 'local' && isset($archivosSubidos['cert_bancaria_local'])) {
                $archivosObligatorios[] = $archivosSubidos['cert_bancaria_local'];
            }
        }
        
        if (!$gestionArchivos->validarArchivosObligatorios($archivosObligatorios)) {
            mostrarError("No se pudieron subir los archivos obligatorios.");
        }

        // ===== OPERACIONES DE BASE DE DATOS =====
        
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            // Inicializar clase de operaciones de base de datos
            $proveedorDB = new ProveedorDB($pdo);

            // 1. Insertar proveedor
            $resultadoProveedor = $proveedorDB->insertarProveedor([
                'proveedor' => $proveedor,
                'rnc' => $rnc,
                'telefono' => $telefono,
                'correo' => $correo,
                'direccion_fiscal' => $direccion_fiscal,
                'usuario' => $usuario,
                'contrasena' => $contrasena
            ]);
            
            $id_proveedor = $resultadoProveedor['id'];
            $validacion_token = $resultadoProveedor['token'];

            // Log de depuración
            file_put_contents(__DIR__ . '/debug_registro.log',
                date('Y-m-d H:i:s') . "\n" .
                "Nuevo proveedor registrado: id_proveedor=" . var_export($id_proveedor, true) . "\n" .
                "Insert info legal: [" . var_export([$id_proveedor, $archivosSubidos['registro_mercantil'], 
                $archivosSubidos['rnc_cedula'], $archivosSubidos['cert_itbis'] ?: null], true) . "]\n",
                FILE_APPEND
            );

            // 2. Insertar representante
            $proveedorDB->insertarRepresentante($id_proveedor, [
                'nombre' => $representante_nombre,
                'telefono' => $representante_telefono
            ]);

            // 3. Insertar relación empresas - proveedor
            $proveedorDB->insertarRelacionEmpresas($id_proveedor, $empresasSeleccionadas);

            // 4. Insertar información bancaria
            if ($tipo_bancario === 'local') {
                $datosBancarios = [
                    'nombre_banco' => $nombre_banco,
                    'tipo_cuenta' => $tipo_cuenta,
                    'nombre_titular' => $nombre_titular,
                    'rnc_titular' => $rnc_titular,
                    'numero_cuenta' => $numero_cuenta,
                    'cert_bancaria' => $archivosSubidos['cert_bancaria']
                ];
            } else {
                $datosBancarios = [
                    'foreign_name' => $foreign_name,
                    'foreign_tax_id' => $foreign_tax_id,
                    'foreign_swift_code' => $foreign_swift_code,
                    'foreign_aba_no' => $foreign_aba_no,
                    'foreign_bank_address' => $foreign_bank_address,
                    'foreign_bank_name' => $foreign_bank_name,
                    'foreign_intermediary_bank' => $foreign_intermediary_bank,
                    'foreign_account_numbers' => $foreign_account_numbers
                ];
            }
            $proveedorDB->insertarInformacionBancaria($id_proveedor, $datosBancarios, $tipo_bancario);

            // 5. Insertar información legal
            $proveedorDB->insertarInformacionLegal($id_proveedor, $archivosSubidos);

            // Commit de la transacción
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            registrarAuditoria($pdo, 'Registrar proveedor', 'Proveedor', $id_proveedor, "Proveedor $proveedor (RNC: $rnc) registrado desde el formulario público");

            // Auto-aceptar documentos legales obligatorios
            $docsObligatorios = $pdo->query("
                SELECT v.id FROM versiones_documentos v
                JOIN documentos_legales d ON d.id = v.id_documento
                WHERE v.obligatorio = 1
                AND v.id = (SELECT id FROM versiones_documentos WHERE id_documento = v.id_documento ORDER BY fecha_publicacion DESC LIMIT 1)
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($docsObligatorios as $dv) {
                $pdo->prepare("INSERT IGNORE INTO aceptacion_terminos (tipo_usuario, usuario_id, id_version, aceptado, ip) VALUES ('proveedor', ?, ?, 1, ?)")
                    ->execute([$id_proveedor, $dv['id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
            }

            // ── Marcar token de invitación como usado ────────────────────
            $inv_token_usado = trim($_POST['inv_token'] ?? '');
            if (!empty($inv_token_usado)) {
                $jsonFileReg = __DIR__ . '/../data/invitaciones_proveedores.json';
                $invitacionesReg = json_decode(file_get_contents($jsonFileReg), true) ?? [];
                foreach ($invitacionesReg as &$invReg) {
                    if (isset($invReg['token']) && hash_equals($invReg['token'], $inv_token_usado)) {
                        $invReg['usado'] = true;
                        $invReg['usado_en'] = date('Y-m-d H:i:s');
                        $invReg['registrado_proveedor'] = $proveedor ?? '';
                        $invReg['auditoria'][] = [
                            'fecha'      => date('Y-m-d H:i:s'),
                            'ip'         => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'desconocida',
                            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 250),
                            'accion'     => 'registro_completado'
                        ];
                        break;
                    }
                }
                unset($invReg);
                file_put_contents($jsonFileReg, json_encode($invitacionesReg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            // ────────────────────────────────────────────────────────────

            // ===== ENVÍO DE CORREOS EN SEGUNDO PLANO =====
            
            // Preparar parámetros para el script de correos
            $empresasString = implode(',', $empresasSeleccionadas);
            $correoUrl = '/SystemSuplidor/php/correo_registro_background.php?' . http_build_query([
                'id_proveedor' => $id_proveedor,
                'token' => $validacion_token,
                'proveedor' => $proveedor,
                'rnc' => $rnc,
                'correo' => $correo,
                'empresas' => $empresasString
            ]);

            // Llamar al script en segundo plano (no bloqueante)
            $host = $_SERVER['HTTP_HOST'];
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fullUrl = $protocol . '://' . $host . $correoUrl;
            
            // Ejecutar en segundo plano sin esperar respuesta
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $command = 'start /B php ' . escapeshellarg(__DIR__ . '/../php/correo_registro_background.php') . 
                        ' "id_proveedor=' . $id_proveedor . 
                        '&token=' . urlencode($validacion_token) .
                        '&proveedor=' . urlencode($proveedor) .
                        '&rnc=' . urlencode($rnc) .
                        '&correo=' . urlencode($correo) .
                        '&empresas=' . urlencode($empresasString) . '" > NUL 2>&1';
                pclose(popen($command, 'r'));
            } else {
                // Linux/Unix
                $command = 'php ' . escapeshellarg(__DIR__ . '/../php/correo_registro_background.php') . 
                        ' "id_proveedor=' . $id_proveedor . 
                        '&token=' . urlencode($validacion_token) .
                        '&proveedor=' . urlencode($proveedor) .
                        '&rnc=' . urlencode($rnc) .
                        '&correo=' . urlencode($correo) .
                        '&empresas=' . urlencode($empresasString) . '" > /dev/null 2>&1 &';
                exec($command);
            }

            file_put_contents(__DIR__ . '/debug_correos.log', 
                date('Y-m-d H:i:s') . " - Proceso de correos iniciado en segundo plano para proveedor: $proveedor\n", FILE_APPEND);

        // Mostrar modal de éxito con SweetAlert
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
            </body>
            </html>';
            
            echo "<script>
                // Eliminar alerta de cargando
                var alerta = document.getElementById('alerta-lateral-fijo');
                if(alerta && alerta.parentNode) alerta.parentNode.removeChild(alerta);
                
                // Mostrar modal inmediatamente
                Swal.fire({
                    icon: 'success',
                    title: '¡Registro exitoso!',
                    html: 'Tu solicitud ha sido enviada correctamente.<br>Se están enviando los correos de notificación.',
                    confirmButtonText: 'Continuar',
                    confirmButtonColor: '#27ae60',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        try {
                            if (window.top !== window.self && typeof window.top.cargarEnIframe === 'function') {
                                window.top.cargarEnIframe('../PanelAdmin/modal/modalVerProveedores.php');
                            } else {
                                window.location.href='../PanelAdmin/login.php';
                            }
                        } catch(e) {
                            window.location.href='../PanelAdmin/login.php';
                        }
                    }
                });
            </script>";
            
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log del error completo
            file_put_contents(__DIR__ . '/debug_error.log', 
                date('Y-m-d H:i:s') . "\nError: " . $e->getMessage() . "\n" . 
                "Trace: " . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            
            // Mensaje amigable según el tipo de error
            $mensaje_error = $e->getMessage();
            if (strpos($mensaje_error, 'Duplicate entry') !== false) {
                if (strpos($mensaje_error, 'usuario') !== false) {
                    mostrarError('El nombre de usuario ya está en uso.');
                } elseif (strpos($mensaje_error, 'correo') !== false) {
                    mostrarError('El correo electrónico ya está registrado.');
                } elseif (strpos($mensaje_error, 'rnc') !== false) {
                    mostrarError('El RNC/Cédula ya está registrado.');
                } else {
                    mostrarError('Ya existe un registro con estos datos.');
                }
            } else {
                mostrarError('Error al procesar el registro: ' . $mensaje_error);
            }
        }
    }
