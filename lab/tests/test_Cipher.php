<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/classes/Header.php';
require_once __DIR__ . '/../src/classes/Matrix.php';
require_once __DIR__ . '/../src/classes/LayerTransformer.php';

use MbarekCipher\Header;
use MbarekCipher\Matrix;
use MbarekCipher\LayerTransformer;

// --- 1. Créer un IV et le Header ---
$iv = random_bytes(16);
$header = new Header(base64_encode($iv));
$headerPadded = $header->encode();

// --- 2. Donnée à chiffrer (exemple) ---
$plaintext = "Bonjour Mbarek, test Cipher 2.0!";
$data = str_split($plaintext); // simple split pour l'exemple

// --- 3. Chiffrement (Matrix 1D simple pour test) ---
$matrix = new Matrix($data);           // initialiser la matrice
LayerTransformer::shuffle($matrix);    // transformation simple
$encryptedData = $matrix->flatten();   // récupérer le tableau chiffré

// --- 4. Écrire dans un fichier .mbk ---
$filePath = __DIR__ . '/test_output.mbk';
file_put_contents($filePath, $headerPadded . implode('', $encryptedData));
echo "Fichier chiffré généré : $filePath\n";

// --- 5. Lecture et déchiffrement ---
$fileContent = file_get_contents($filePath);
$headerRead = substr($fileContent, 0, Header::MBK_HEADER_SIZE);
$encryptedRead = substr($fileContent, Header::MBK_HEADER_SIZE);

$decodedHeader = Header::decode($headerRead);
$encryptedArray = str_split($encryptedRead);

// Déchiffrement : inverser les transformations
$matrix2 = new Matrix($encryptedArray);
LayerTransformer::unshuffle($matrix2);
$decrypted = implode('', $matrix2->flatten());

echo "Données déchiffrées : $decrypted\n";
?>