<?php
declare(strict_types=1);
use MbarekCipher\Cipher;
require_once __DIR__ . '/../src/Cipher.php';
require_once __DIR__ . '/../src/Utils.php';

$tmpDir = __DIR__ . '/../tmp';
$cipher = new Cipher("testkey", 64);

$cipher->encryptFile("tests/test.txt", "tmp/test.mbk");
$cipher->decryptFile("tmp/test.mbk", "tests/output.txt");

echo file_get_contents("tests/test.txt") === file_get_contents("tests/output.txt")
    ? "✅ OK"
    : "❌ ERROR";
?>