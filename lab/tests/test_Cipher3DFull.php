<?php
require_once __DIR__ . '/../src/Cipher3DFull.php';

use MbarekCipher\Cipher3DFull;

$data = file_get_contents('sample_input.txt');
$key = "MBarek123";

$cipher = new Cipher3DFull($key);
$encrypted = $cipher->encrypt($data);
file_put_contents('encrypted_full.mbk', $encrypted);

$decrypted = $cipher->decrypt($encrypted);
file_put_contents('decrypted_full.txt', $decrypted);

echo "✅ Chiffrement et déchiffrement full 3D terminés.\n";
?>