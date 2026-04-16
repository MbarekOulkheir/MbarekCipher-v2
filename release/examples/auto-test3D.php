<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cipher.php';
require_once __DIR__ . '/../src/classes/DeCubeEnhanced.php';
require_once __DIR__ . '/../src/classes/Matrix.php';
require_once __DIR__ . '/../src/classes/LayerTransformer.php';

use MbarekCipher\Cipher;
use MbarekCipher\DeCubeEnhanced;

// ✨ Test multi-Dé avec différents fichiers
$tests = [
    ['file' => __DIR__ . '/sample_input.txt', 'desc' => 'Test texte simple'],
    ['file' => __DIR__ . '/sample_input2.txt', 'desc' => 'Test texte multiple lignes']
];

// Création de fichiers sample si n'existent pas
if (!file_exists(__DIR__ . '/sample_input.txt')) {
    file_put_contents(__DIR__ . '/sample_input.txt', "Bonjour Mbarek, test Cipher avec Dé iv et améliorations!\n");
}
if (!file_exists(__DIR__ . '/sample_input2.txt')) {
    file_put_contents(__DIR__ . '/sample_input2.txt', "Ligne 1\nLigne 2\nLigne 3\nFin du test.\n");
}

// Initialisation du Cipher
$key = 'MBarek123';
$cipher = new Cipher($key);

foreach ($tests as $t) {
    echo "\n--- {$t['desc']} ---\n";

    $input = $t['file'];
    $encrypted = __DIR__ . '/encrypted_' . basename($input) . '.mbk';
    $decrypted = __DIR__ . '/decrypted_' . basename($input) . '.bin';
var_dump($input);
    echo "--- Chiffrement ---\n";
    $cipher->encryptFile($input, $encrypted);

    echo "--- Déchiffrement ---\n";
    $cipher->decryptFile($encrypted, $decrypted);

    // Vérification du diff
    $diff = shell_exec("diff {$input} {$decrypted}");
    if (empty($diff)) {
        echo "✅ Fichiers identiques, test réussi !\n";
    } else {
        echo "⚠ Fichiers différents :\n$diff\n";
    }
}

echo "\n🎉 Tous les tests multi-Dé sont terminés.\n";
?>
