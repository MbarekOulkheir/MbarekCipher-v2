<?php
require_once __DIR__ . '/Constants.php';

/**
 * XOR binaire de deux chaînes de même longueur
 */
function xorBinary(string $bits, string $key): string {
    $result = '';
    $keyBits = '';
    // Convertir la clé en bits
    for ($i=0; $i<strlen($key); $i++) {
        $keyBits .= str_pad(decbin(ord($key[$i])), 8, '0', STR_PAD_LEFT);
    }
    echo "len bits :".strlen($bits)."\n";
    $keyLen = strlen($keyBits);

    for ($i=0; $i<strlen($bits); $i++) {
        $bit = $bits[$i];
        $keyBit = $keyBits[$i % $keyLen];
        $result .= ($bit === $keyBit ? '0' : '1');
    }
    echo "len resultat xor :".strlen($bits)."\n";
    return $result;
}
/**
 * Calcul SHA-256 hash d'une chaîne
 */
function computeHash(string $data): string {
    return hash(MBK_HASH_ALGO, $data);
}
?>