<?php
/**
 * Endpoint para verificar RNC/Cédula en tiempo real.
 * Códigos: FORMATO_VALIDO (interno) | DOCUMENTO_DISPONIBLE | DUPLICADO | INVALIDO | ERROR
 * Aliases JS: DUPLICATE (duplicado). No usar VALID como "identidad verificada".
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
require_once '../../Base_de_datos/config.php';
require_once __DIR__ . '/../modulos/DocumentoIdentidad.php';

function ss_rnc_json(array $payload): void
{
    if (ob_get_length() !== false) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ss_rnc_respuesta(string $codigo, string $mensaje, array $extra = []): void
{
    $statusJs = $codigo;
    if ($codigo === 'DUPLICADO') {
        $statusJs = 'DUPLICATE';
    }
    $disponible = $codigo === 'DOCUMENTO_DISPONIBLE';
    $formatoOk = in_array($codigo, ['FORMATO_VALIDO', 'DOCUMENTO_DISPONIBLE', 'DUPLICADO'], true);
    ss_rnc_json(array_merge([
        'status' => $statusJs,
        'codigo' => $codigo,
        'formato_valido' => $formatoOk,
        'documento_disponible' => $disponible,
        'exists' => $codigo === 'DUPLICADO',
        'valid' => false,
        'valido' => false,
        'mensaje' => $mensaje,
    ], $extra));
}

$rnc = trim((string) ($_POST['rnc'] ?? ''));
$tipo = trim((string) ($_POST['tipo'] ?? ''));

$errFormato = DocumentoIdentidad::mensajeFormato($rnc, $tipo);
if ($errFormato !== null) {
    ss_rnc_respuesta('INVALIDO', $errFormato);
}

try {
    if (DocumentoIdentidad::existeDuplicado($pdo, $rnc)) {
        ss_rnc_respuesta('DUPLICADO', 'Esta cédula/RNC ya se encuentra registrada.');
    }

    $digitos = DocumentoIdentidad::soloDigitos($rnc);
    $esCedula = ($tipo === 'persona_individual') || strlen($digitos) === 11;
    ss_rnc_respuesta(
        'DOCUMENTO_DISPONIBLE',
        $esCedula
            ? 'Cédula disponible (no registrada).'
            : 'RNC disponible (no registrado).'
    );
} catch (PDOException $e) {
    error_log('Error al verificar RNC: ' . $e->getMessage());
    ss_rnc_respuesta('ERROR', 'No pudimos verificar la cédula/RNC. Intenta nuevamente.');
}
