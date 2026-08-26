<?php
/**
 * Endpoint para validar RNC/Cédula en tiempo real.
 * Respuestas: VALID | DUPLICATE | INVALID | ERROR
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
require_once '../../Base_de_datos/config.php';

function ss_rnc_json(array $payload): void
{
    if (ob_get_length() !== false) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ss_documento_digits(string $documento): string
{
    return preg_replace('/\D+/', '', $documento) ?? '';
}

if (!isset($_POST['rnc']) || trim((string) $_POST['rnc']) === '') {
    ss_rnc_json([
        'status' => 'INVALID',
        'exists' => false,
        'valid' => false,
        'valido' => false,
        'mensaje' => 'RNC vacío',
    ]);
}

$rnc = trim((string) $_POST['rnc']);
$tipo = trim((string) ($_POST['tipo'] ?? ''));
$digits = ss_documento_digits($rnc);

if ($digits === '') {
    ss_rnc_json([
        'status' => 'INVALID',
        'exists' => false,
        'valid' => false,
        'valido' => false,
        'mensaje' => 'No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.',
    ]);
}

if ($tipo === 'persona_individual' && strlen($digits) !== 11) {
    ss_rnc_json([
        'status' => 'INVALID',
        'exists' => false,
        'valid' => false,
        'valido' => false,
        'mensaje' => 'No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.',
    ]);
}

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM proveedores
         WHERE rnc_cedula = ?
            OR REPLACE(REPLACE(REPLACE(TRIM(rnc_cedula), '-', ''), ' ', ''), '.', '') = ?"
    );
    $stmt->execute([$rnc, $digits]);
    $existe = (int) $stmt->fetchColumn() > 0;

    if ($existe) {
        ss_rnc_json([
            'status' => 'DUPLICATE',
            'exists' => true,
            'valid' => false,
            'valido' => false,
            'mensaje' => 'Esta cédula/RNC ya se encuentra registrada.',
        ]);
    }

    ss_rnc_json([
        'status' => 'VALID',
        'exists' => false,
        'valid' => true,
        'valido' => true,
        'mensaje' => 'Cédula/RNC validado',
    ]);
} catch (PDOException $e) {
    error_log('Error al validar RNC: ' . $e->getMessage());
    ss_rnc_json([
        'status' => 'ERROR',
        'exists' => false,
        'valid' => false,
        'valido' => false,
        'mensaje' => 'No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.',
    ]);
}
