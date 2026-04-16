<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cipher.php';

use MbarekCipher\Cipher;

try {
    $cipher = new Cipher("ma_cle_secrete");

    // Fichier d'entrée et fichier chiffré de sortie
    $inputFile  = __DIR__ . '/../tests/test_4MB.bin';   // à créer si nécessaire
    $outputFile = __DIR__ . '/../tests/encrypted.mbk';

    $cipher->encryptFile($inputFile, $outputFile);

    echo "Fichier chiffré généré : $outputFile\n";

    // Optionnel : afficher hash du fichier chiffré
    if (file_exists($outputFile)) {
        $hash = hash_file('sha256', $outputFile);
        echo "SHA256 du fichier chiffré : $hash\n";
    }

} catch (Exception $e) {
    echo "Erreur lors du chiffrement : " . $e->getMessage() . "\n";
}