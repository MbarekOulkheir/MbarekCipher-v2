<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cipher.php';
use MbarekCipher\Cipher;

$key = "TestKey123";
$blockSize = 16;
$debug = true;
$optimizedDebug = true;

$cipher = new Cipher($key, $blockSize, $debug, $optimizedDebug);

$tests = [
    'fichier_vide' => '',
    'petit_fichier' => 'ABCD',
    'non_multiple_block' => 'ABCDEFGH12345',
    'multiple_block' => str_repeat('ABCDEFGH', 4),
    'gros_fichier' => str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 1000)
];

echo "=== Tests Cipher MBK v2.7 ===\n\n";

foreach ($tests as $name => $content) {
    echo "=== Test : $name ===\n";

    $inputFile = __DIR__ . "/{$name}.txt";
    $encryptedFile = __DIR__ . "/{$name}.mbk";
    $decryptedFile = __DIR__ . "/{$name}_dec.txt";

    file_put_contents($inputFile, $content);

    $cipher->encryptFile($inputFile, $encryptedFile);
    echo "✅ Fichier chiffré : " . filesize($encryptedFile) . " octets\n";

    $cipher->decryptFile($encryptedFile, $decryptedFile);
    echo "✅ Fichier déchiffré : " . filesize($decryptedFile) . " octets\n";

    if (file_get_contents($inputFile) === file_get_contents($decryptedFile)) {
        echo "🎯 Succès : contenu identique\n\n";
    } else {
        echo "❌ Échec : contenu différent\n\n";
    }
}

echo "=== Tous tests terminés ===\n";