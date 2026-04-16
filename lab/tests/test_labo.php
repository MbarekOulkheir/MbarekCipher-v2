<?php

require_once __DIR__ . '/../src/Cipherlabo.php';
require_once __DIR__ . '/../src/Cipher.php';

use MbarekCipher\Cipher;
use MbarekCipher\CipherLab;

$cipher = new Cipher("my_secret_key", 8, true);
$labo = new CipherLab($cipher);

$data = "ABCDEF";

$labo->testCipherOnly($data);
$labo->testBoostOnly($data);
$labo->testFull($data);
?>