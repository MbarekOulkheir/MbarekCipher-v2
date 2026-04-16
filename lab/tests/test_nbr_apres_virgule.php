<?php
declare(strict_types=1);

// Configuration BCMath : nombre de chiffres maximum à manipuler
bcscale(100); // marge interne

// Table des nombres remarquables
$numbers = [
    'pi'    => '3.14159265358979323846264338327950288419716939937510',
    'e'     => '2.71828182845904523536028747135266249775724709369995',
    'sqrt2' => '1.41421356237309504880168872420969807856967187537694'
];

/**
 * Retourne la partie décimale d'un nombre BCMath sous forme d'entier.
 *
 * @param string $numStr Le nombre sous forme de chaîne
 * @param int    $digits Nombre de chiffres de la partie décimale à extraire
 * @return string Partie décimale sous forme entière
 */
function decimalPartBC(string $numStr, int $digits): string {
    $integerPart = bcdiv($numStr, '1', 0);           // partie entière
    $frac = bcsub($numStr, $integerPart, $digits + 5); // partie décimale avec marge
    $scaled = bcmul($frac, bcpow('10', (string)$digits)); // multiplier pour obtenir n chiffres
    return bcadd($scaled, '0', 0);                  // convertir en entier sans virgule
}

// Exemple d'utilisation
$digits = 20;

// Nombres remarquables
foreach ($numbers as $name => $val) {
    $decPart = decimalPartBC($val, $digits);
    echo "Decimal part of $name with $digits digits: $decPart\n";
}

// Fraction simple
$frac = bcdiv('1', '7', $digits + 5); // 1/7 avec marge
echo "Decimal part of 1/7 with $digits digits: " . decimalPartBC($frac, $digits) . "\n";

// Nombre flottant classique
$float = '3.14159';
echo "Decimal part of $float with $digits digits: " . decimalPartBC($float, $digits) . "\n";

?>