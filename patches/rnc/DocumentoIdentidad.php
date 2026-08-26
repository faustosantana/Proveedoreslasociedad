<?php
/**
 * Normalización y reglas de formato de RNC/Cédula.
 *
 * No hay checksum/Luhn ni consulta DGII en este proyecto.
 * "disponible" significa: formato aceptable y no duplicado en proveedores.
 * No significa que el documento esté verificado ante el Estado.
 */

class DocumentoIdentidad
{
    public static function soloDigitos($valor)
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    public static function esDummyCeros($digitos)
    {
        return $digitos !== '' && preg_match('/^0+$/', $digitos);
    }

    /**
     * @return string|null mensaje de error, o null si el formato es aceptable
     */
    public static function mensajeFormato($rncRaw, $tipo)
    {
        $tipo = strtolower(trim((string) $tipo));
        $digitos = self::soloDigitos($rncRaw);

        if ($tipo === 'internacional') {
            if ($digitos === '') {
                return null;
            }
            if (self::esDummyCeros($digitos)) {
                return 'El RNC/Cédula no es un documento válido.';
            }
            return null;
        }

        if ($tipo === 'local') {
            if ($digitos === '') {
                return 'El RNC es obligatorio para proveedores locales.';
            }
            if (self::esDummyCeros($digitos)) {
                return 'El RNC no es un documento válido.';
            }
            $len = strlen($digitos);
            // RNC dominicano: 9 dígitos. Se acepta 11 por registros históricos con cédula.
            if ($len !== 9 && $len !== 11) {
                return 'El RNC debe tener 9 dígitos.';
            }
            return null;
        }

        // persona_individual (cédula)
        if ($digitos === '') {
            return 'La cédula es obligatoria.';
        }
        if (strlen($digitos) !== 11) {
            return 'La cédula debe tener 11 dígitos.';
        }
        if (self::esDummyCeros($digitos)) {
            return 'La cédula no es un documento válido.';
        }
        return null;
    }

    public static function existeDuplicado(PDO $pdo, $rncRaw)
    {
        $norm = self::soloDigitos($rncRaw);
        if ($norm === '') {
            return false;
        }
        $sql = "SELECT 1 FROM proveedores
                WHERE REPLACE(REPLACE(REPLACE(TRIM(rnc_cedula), '-', ''), ' ', ''), '.', '') = ?
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([$norm]);
        return (bool) $st->fetchColumn();
    }
}
