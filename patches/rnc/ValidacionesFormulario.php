<?php

/**
 * -----------------------------------------------
 * Módulo: Validaciones de formularios
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-12-26
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */

require_once __DIR__ . '/DocumentoIdentidad.php';

class ValidacionesFormulario {
    
    /**
     * Valida los datos básicos del usuario
     * 
     * @param string $usuario Nombre de usuario
     * @param string $contrasena Contraseña
     * @return array Array con ['valido' => bool, 'error' => string]
     */
    public static function validarUsuario($usuario, $contrasena) {
        if (!$usuario || strlen($contrasena) < 8) {
            return ['valido' => false, 'error' => 'Usuario o contraseña inválidos.'];
        }
        return ['valido' => true, 'error' => ''];
    }

    /**
     * Valida los datos del proveedor
     * 
     * @param array $datos Array con los datos del proveedor
     * @return array Array con ['valido' => bool, 'error' => string]
     */
    public static function validarProveedor($datos) {
        $requeridos = ['tipo_proveedor_general', 'proveedor', 'telefono', 'correo', 'direccion_fiscal'];
        
        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                return ['valido' => false, 'error' => 'Faltan datos obligatorios del proveedor.'];
            }
        }

        $errDoc = DocumentoIdentidad::mensajeFormato(
            $datos['rnc'] ?? '',
            $datos['tipo_proveedor_general'] ?? ''
        );
        if ($errDoc !== null) {
            return ['valido' => false, 'error' => $errDoc];
        }

        return ['valido' => true, 'error' => ''];
    }

    /**
     * Valida que se hayan seleccionado empresas
     * 
     * @param array $empresas Array de IDs de empresas
     * @return array Array con ['valido' => bool, 'error' => string]
     */
    public static function validarEmpresas($empresas) {
        if (empty($empresas)) {
            return ['valido' => false, 'error' => 'Debe seleccionar al menos una empresa.'];
        }

        // Validar que sean enteros positivos
        foreach ($empresas as $id_empresa) {
            if (!filter_var($id_empresa, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
                return ['valido' => false, 'error' => 'ID de empresa inválido.'];
            }
        }

        return ['valido' => true, 'error' => ''];
    }

    /**
     * Valida los archivos obligatorios
     * 
     * @param array $files Array $_FILES
     * @param string $tipo_bancario Tipo bancario ('local' o 'extranjero')
     * @param string $tipo_proveedor_general Tipo de proveedor ('persona_individual', 'local', 'internacional')
     * @return array Array con ['valido' => bool, 'error' => string]
     */
    public static function validarArchivos($files, $tipo_bancario, $tipo_proveedor_general = 'local') {
        if ($tipo_proveedor_general === 'persona_individual') {
            // Validar solo RNC/Cédula y certificación bancaria para persona individual
            if (!isset($files['rnc_cedula']) || $files['rnc_cedula']['error'] !== UPLOAD_ERR_OK) {
                return ['valido' => false, 'error' => 'Copia de Cédula (ambos lados) es obligatoria.'];
            }
            if (!isset($files['cert_bancaria']) || $files['cert_bancaria']['error'] !== UPLOAD_ERR_OK) {
                return ['valido' => false, 'error' => 'Certificación bancaria es obligatoria.'];
            }
        } else {
            // Validar archivos para empresas (local o internacional)
            
            // Validar certificación bancaria local solo para empresas locales
            if ($tipo_bancario === 'local') {
                if (!isset($files['cert_bancaria_local']) || $files['cert_bancaria_local']['error'] !== UPLOAD_ERR_OK) {
                    return ['valido' => false, 'error' => 'Certificación bancaria es obligatoria para cuentas locales.'];
                }
            }

            // Validar registro mercantil
            if (!isset($files['registro_mercantil']) || $files['registro_mercantil']['error'] !== UPLOAD_ERR_OK) {
                return ['valido' => false, 'error' => 'Registro mercantil obligatorio: Debes adjuntar el registro mercantil.'];
            }

            // Validar RNC/Cédula
            if (!isset($files['rnc_cedula']) || $files['rnc_cedula']['error'] !== UPLOAD_ERR_OK) {
                return ['valido' => false, 'error' => 'Copia de RNC o Cédula es obligatoria.'];
            }

            // Validar certificación ITBIS
            if (!isset($files['cert_itbis']) || $files['cert_itbis']['error'] !== UPLOAD_ERR_OK) {
                return ['valido' => false, 'error' => 'Certificación de impuestos al día es obligatoria.'];
            }
        }

        return ['valido' => true, 'error' => ''];
    }

    /**
     * Valida los datos del representante
     * 
     * @param array $datos Array con los datos del representante
     * @return array Array con ['valido' => bool, 'error' => string, 'campos_vacios' => array]
     */
    public static function validarRepresentante($datos) {
        $camposVacios = [];
        
        if (trim($datos['nombre'] ?? '') === '') $camposVacios[] = 'nombre';
        if (trim($datos['telefono'] ?? '') === '') $camposVacios[] = 'telefono';

        if (!empty($camposVacios)) {
            return [
                'valido' => false, 
                'error' => 'Todos los campos del representante son obligatorios.',
                'campos_vacios' => $camposVacios
            ];
        }

        return ['valido' => true, 'error' => '', 'campos_vacios' => []];
    }

    /**
     * Valida que el usuario sea único en la base de datos
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param string $usuario Nombre de usuario
     * @param string $correo Email
     * @param string $rnc RNC/Cédula
     * @param string $tipo Tipo de proveedor (persona_individual|local|internacional)
     * @return array Array con ['valido' => bool, 'error' => string]
     */
    public static function validarUnicos($pdo, $usuario, $correo, $rnc, $tipo = 'persona_individual') {
        $errDoc = DocumentoIdentidad::mensajeFormato($rnc, $tipo);
        if ($errDoc !== null) {
            return ['valido' => false, 'error' => $errDoc];
        }

        // Validar usuario único
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM proveedores WHERE usuario = ?");
        $stmtCheck->execute([$usuario]);
        if ($stmtCheck->fetchColumn() > 0) {
            return ['valido' => false, 'error' => 'El nombre de usuario ya está en uso.'];
        }

        // Validar correo único
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM proveedores WHERE correo = ?");
        $stmtCheck->execute([$correo]);
        if ($stmtCheck->fetchColumn() > 0) {
            return ['valido' => false, 'error' => 'El correo ya está en uso.'];
        }

        // Duplicado sobre dígitos normalizados (guiones/espacios/puntos equivalentes)
        if (DocumentoIdentidad::soloDigitos($rnc) !== '') {
            if (DocumentoIdentidad::existeDuplicado($pdo, $rnc)) {
                return ['valido' => false, 'error' => 'El RNC/Cédula ya está en uso.'];
            }
        }

        return ['valido' => true, 'error' => ''];
    }
}
