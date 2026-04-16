<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cipher.php';

use MbarekCipher\Cipher;

try {
    $cipher = new Cipher("ma_cle_secrete");

    $inputFile  = __DIR__ . '/../tests/encrypted.mbk';
    $outputFile = __DIR__ . '/../tests/decrypted_output.bin';

    $cipher->decryptFile($inputFile, $outputFile);

    echo "Fichier déchiffré généré : $outputFile\n";

    // Vérification hash
    if (file_exists($outputFile)) {
        $hash = hash_file('sha256', $outputFile);
        echo "SHA256 du fichier déchiffré : $hash\n";
    }

} catch (Exception $e) {
    echo "Erreur lors du déchiffrement : " . $e->getMessage() . "\n";
}