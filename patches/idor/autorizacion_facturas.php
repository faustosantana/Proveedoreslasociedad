<?php
/**
 * Autorización de facturas.
 * Misma regla que historial.php: el scope sale de la sesión, nunca del request.
 */

function ss_tipo_sesion(): ?string
{
    return $_SESSION['tipo'] ?? null;
}

function ss_id_proveedor_sesion()
{
    return $_SESSION['id_proveedor'] ?? null;
}

function ss_id_empresa_sesion()
{
    $tipo = ss_tipo_sesion();
    if ($tipo === 'empresa') {
        return $_SESSION['id_empresa'] ?? null;
    }
    if ($tipo === 'usuario') {
        return $_SESSION['empresa_activa'] ?? ($_SESSION['id_empresa'] ?? null);
    }
    return null;
}

/**
 * Añade AND f.id_proveedor / f.id_empresa según el rol.
 * admin: sin predicado extra (reporte global intacto).
 */
function ss_aplicar_scope_facturas(array &$condiciones, array &$parametros): void
{
    $tipo = ss_tipo_sesion();
    if ($tipo === 'proveedor') {
        $id = ss_id_proveedor_sesion();
        if (!$id) {
            $condiciones[] = '1 = 0';
            return;
        }
        $condiciones[] = 'f.id_proveedor = ?';
        $parametros[] = $id;
        return;
    }
    if ($tipo === 'empresa' || $tipo === 'usuario') {
        $id = ss_id_empresa_sesion();
        if (!$id) {
            $condiciones[] = '1 = 0';
            return;
        }
        $condiciones[] = 'f.id_empresa = ?';
        $parametros[] = $id;
    }
}

function ss_puede_ver_factura(array $factura): bool
{
    $tipo = ss_tipo_sesion();
    if ($tipo === 'admin') {
        return true;
    }
    if ($tipo === 'proveedor') {
        $id = ss_id_proveedor_sesion();
        return $id && isset($factura['id_proveedor']) && (string) $factura['id_proveedor'] === (string) $id;
    }
    if ($tipo === 'empresa' || $tipo === 'usuario') {
        $id = ss_id_empresa_sesion();
        return $id && isset($factura['id_empresa']) && (string) $factura['id_empresa'] === (string) $id;
    }
    return false;
}

function ss_require_tipo_sesion(): ?string
{
    $tipo = ss_tipo_sesion();
    if (!$tipo) {
        return null;
    }
    if ($tipo === 'proveedor' && !ss_id_proveedor_sesion()) {
        return null;
    }
    if ($tipo === 'empresa' && !ss_id_empresa_sesion()) {
        return null;
    }
    if ($tipo === 'usuario' && !ss_id_empresa_sesion()) {
        return null;
    }
    if (!in_array($tipo, ['admin', 'proveedor', 'empresa', 'usuario'], true)) {
        return null;
    }
    return $tipo;
}
