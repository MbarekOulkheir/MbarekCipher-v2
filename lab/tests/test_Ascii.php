<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cipher.php';
require_once __DIR__ . '/../src/CipherAscii.php';
require_once __DIR__ . '/../src/classes/DeCubeEnhanced.php';

use MbarekCipher\Cipher;
use MbarekCipher\CipherASCII;

// ================== CONFIG ==================
$inputFile = __DIR__ . '/../tmp/input_test.txt';
$key = 'ma-cle-secrete-123';
$blockSize = 64;

// Lire le fichier
$data = file_get_contents($inputFile);
$len = strlen($data);

// IV global aléatoire
$globalIV = random_bytes($blockSize);

// ================== ASCII-ONLY ==================
$cipherAscii = new CipherASCII($key, $blockSize);

// Chiffrement ASCII
$prevAscii = $globalIV; // CBC-style chaining
$encryptedAscii = '';
for ($i = 0; $i < $len; $i += $blockSize) {
    $block = substr($data, $i, $blockSize);
    $blockIV = $cipherAscii->generateDynamicIV($globalIV, (int)($i/$blockSize), $prevAscii, strlen($block));
    $enc = $cipherAscii->encryptBlock($block, (int)($i/$blockSize), $blockIV);
    $encryptedAscii .= $enc;
    $prevAscii = $enc; // CBC chaining
}

// Déchiffrement ASCII
$prevAscii = $globalIV;
$decryptedAscii = '';
for ($i = 0; $i < strlen($encryptedAscii); $i += $blockSize) {
    $block = substr($encryptedAscii, $i, $blockSize);
    $blockIV = $cipherAscii->generateDynamicIV($globalIV, (int)($i/$blockSize), $prevAscii, strlen($block));
    $dec = $cipherAscii->decryptBlock($block, (int)($i/$blockSize), $blockIV);
    $decryptedAscii .= $dec;
    $prevAscii = $block; // CBC chaining
}

// ================== CIPHER 3D ==================
$cipher3D = new Cipher($key, $blockSize, false);

// Chiffrement 3D
$prev3D = $cipher3D->initPrev($globalIV);
$encrypted3D = '';
for ($i = 0; $i < $len; $i += $blockSize) {
    $block = substr($data, $i, $blockSize);
    $blockIV = $cipher3D->generateDynamicIV($globalIV, (int)($i/$blockSize), $prev3D, strlen($block));
    $enc = $cipher3D->encrypt3D($block, (int)($i/$blockSize), $blockIV);
    $encrypted3D .= $enc;
    $prev3D = $enc;
}

// Déchiffrement 3D
$prev3D = $cipher3D->initPrev($globalIV);
$decrypted3D = '';
for ($i = 0; $i < strlen($encrypted3D); $i += $blockSize) {
    $block = substr($encrypted3D, $i, $blockSize);
    $blockIV = $cipher3D->generateDynamicIV($globalIV, (int)($i/$blockSize), $prev3D, strlen($block));
    $dec = $cipher3D->decrypt3D($block, (int)($i/$blockSize), $blockIV);
    $decrypted3D .= $dec;
    $prev3D = $block;
}

// ================== ENTROPIE ==================
function entropy(string $str): float {
    $h = array_count_values(str_split($str));
    $len = strlen($str);
    $ent = 0.0;
    foreach ($h as $c => $freq) {
        $p = $freq / $len;
        $ent -= $p * log($p, 2);
    }
    return $ent;
}

// ================== RESULTATS ==================
echo "Entropie ASCII-only: " . entropy($encryptedAscii) . " bits/byte\n";
echo ($decryptedAscii === $data ? "✅ ASCII OK\n" : "❌ ASCII FAIL\n");

echo "Entropie 3D cipher: " . entropy($encrypted3D) . " bits/byte\n";
echo ($decrypted3D === $data ? "✅ 3D OK\n" : "❌ 3D FAIL\n");