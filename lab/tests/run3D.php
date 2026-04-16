<?php
require_once __DIR__ . '/../src/Cipher3DFull.php';

use MbarekCipher\Cipher3DFull;

// Vérification des arguments CLI
if ($argc < 4) {
    echo "Usage : php run3D.php <encrypt|decrypt> <input_file> <output_file> [clé]\n";
    exit(1);
}

$action = strtolower($argv[1]);
$inputFile = $argv[2];
$outputFile = $argv[3];
$key = $argv[4] ?? "MBarek123";

// Lire le fichier
if (!file_exists($inputFile)) {
    echo "Erreur : fichier $inputFile introuvable.\n";
    exit(1);
}
$data = file_get_contents($inputFile);

// Initialiser le Cipher 3D full
$cipher = new Cipher3DFull($key);

if ($action === 'encrypt') {
    $result = $cipher->encrypt($data);
    file_put_contents($outputFile, $result);
    echo "✅ Fichier chiffré : $outputFile\n";
} elseif ($action === 'decrypt') {
    $result = $cipher->decrypt($data);
    file_put_contents($outputFile, $result);
    echo "✅ Fichier déchiffré : $outputFile\n";
} else {
    echo "Erreur : action invalide, utilisez 'encrypt' ou 'decrypt'.\n";
    exit(1);
}
?>