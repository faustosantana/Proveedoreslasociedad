<?php

/**
 * Development-environment smoke test.
 *
 * Exercises the self-contained business logic shipped in this repository
 * (RNC/Cedula validation) to prove the PHP toolchain and the optional
 * MariaDB service are wired up correctly.
 *
 * Pure-format checks always run. The database-backed uniqueness checks run
 * only when a MySQL/MariaDB connection is available.
 *
 * Usage: php .cursor/verify-env.php
 * Exit code 0 = all checks passed, 1 = a check failed.
 */

require_once __DIR__ . '/../patches/rnc/DocumentoIdentidad.php';
require_once __DIR__ . '/../patches/rnc/ValidacionesFormulario.php';

$passed = 0;
$failed = 0;

function check(string $label, $expected, $actual): void
{
    global $passed, $failed;
    $ok = $expected === $actual;
    if ($ok) {
        $passed++;
        printf("  PASS  %s\n", $label);
    } else {
        $failed++;
        printf("  FAIL  %s\n        expected: %s\n        actual:   %s\n",
            $label, var_export($expected, true), var_export($actual, true));
    }
}

echo "PHP: " . PHP_VERSION . "\n";
echo "Loaded extensions of interest: ";
$want = ['pdo_mysql', 'mbstring', 'gd', 'zip', 'curl', 'intl', 'bcmath', 'fileinfo'];
echo implode(', ', array_values(array_filter($want, 'extension_loaded'))) . "\n\n";

echo "== DocumentoIdentidad::soloDigitos (normalization) ==\n";
check("044-1234567-8 -> 04412345678", '04412345678', DocumentoIdentidad::soloDigitos('044-1234567-8'));
check("spaces/dots stripped", '04412345678', DocumentoIdentidad::soloDigitos('044 123.4567 8'));

echo "\n== DocumentoIdentidad::mensajeFormato ==\n";
check("local RNC 9 digits ok", null, DocumentoIdentidad::mensajeFormato('130123456', 'local'));
check("local RNC wrong length", 'El RNC debe tener 9 dígitos.', DocumentoIdentidad::mensajeFormato('12345', 'local'));
check("local RNC empty", 'El RNC es obligatorio para proveedores locales.', DocumentoIdentidad::mensajeFormato('', 'local'));
check("cedula 11 digits ok", null, DocumentoIdentidad::mensajeFormato('040-1234567-8', 'persona_individual'));
check("cedula dummy zeros rejected", 'La cédula no es un documento válido.', DocumentoIdentidad::mensajeFormato('000-0000000-0', 'persona_individual'));
check("internacional empty ok", null, DocumentoIdentidad::mensajeFormato('', 'internacional'));

echo "\n== ValidacionesFormulario::validarProveedor ==\n";
$okProveedor = ValidacionesFormulario::validarProveedor([
    'tipo_proveedor_general' => 'local',
    'proveedor' => 'ACME SRL',
    'telefono' => '8095551234',
    'correo' => 'nuevo@example.com',
    'direccion_fiscal' => 'Av. Siempre Viva 123',
    'rnc' => '130123456',
]);
check("valid local provider", true, $okProveedor['valido']);

$badProveedor = ValidacionesFormulario::validarProveedor([
    'tipo_proveedor_general' => 'local',
    'proveedor' => 'ACME SRL',
    'telefono' => '8095551234',
    'correo' => 'nuevo@example.com',
    'direccion_fiscal' => 'Av. Siempre Viva 123',
    'rnc' => '12',
]);
check("invalid RNC rejected", false, $badProveedor['valido']);

echo "\n== Database-backed checks (PDO) ==\n";
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'suplidores_dev';
$user = getenv('DB_USER') ?: 'suplidor';
$pass = getenv('DB_PASS') ?: 'suplidor';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "  Connected to {$user}@{$host}/{$name}\n";

    check("existeDuplicado finds seeded 044-1234567-8",
        true, DocumentoIdentidad::existeDuplicado($pdo, '044-1234567-8'));
    check("existeDuplicado normalizes 04412345678",
        true, DocumentoIdentidad::existeDuplicado($pdo, '04412345678'));
    check("existeDuplicado unknown RNC absent",
        false, DocumentoIdentidad::existeDuplicado($pdo, '999888777'));

    $dupUser = ValidacionesFormulario::validarUnicos($pdo, 'existente', 'x@example.com', '130555111', 'local');
    check("validarUnicos rejects taken username", false, $dupUser['valido']);

    $fresh = ValidacionesFormulario::validarUnicos($pdo, 'brand_new_user', 'brand_new@example.com', '130555111', 'local');
    check("validarUnicos accepts fresh data", true, $fresh['valido']);
} catch (PDOException $e) {
    echo "  SKIP  database not reachable: " . $e->getMessage() . "\n";
    echo "        (pure-format checks above still validate the PHP toolchain)\n";
}

echo "\n-------------------------------------------\n";
printf("RESULT: %d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
