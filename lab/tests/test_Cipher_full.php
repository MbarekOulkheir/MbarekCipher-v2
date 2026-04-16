<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/classes/Header.php';
require_once __DIR__ . '/../src/Cipher.php';

use MbarekCipher\Header;
use MbarekCipher\Cipher;

// ------------------------------
// 1️⃣ Préparer le fichier test
// ------------------------------
$inputFile = __DIR__ . '/../sample_input.txt';
file_put_contents($inputFile, "Bonjour Mbarek, test Cipher 2.1 avec header dynamique!");

// ------------------------------
// 2️⃣ Créer la meta et le header dynamique
// ------------------------------
$meta = [
    'filename' => 'sample_input.txt',
    'filesize' => filesize($inputFile),
    'timestamp' => time()
];
$iv = base64_encode(random_bytes(16));
$header = new Header($iv, $meta);
$encodedHeader = $header->encode();

// ------------------------------
// 3️⃣ Chiffrement avec Cipher
// ------------------------------
$cipher = new Cipher($iv);
$tempEncryptedFile = __DIR__ . '/temp_encrypted.bin';
$cipher->encryptFile($inputFile, $tempEncryptedFile);

// Lire le contenu chiffré
$encryptedData = file_get_contents($tempEncryptedFile);

// Combiner header + payload dans le .mbk final
$outputFile = __DIR__ . '/test_output_dynamic.mbk';
file_put_contents($outputFile, $encodedHeader . $encryptedData);
unlink($tempEncryptedFile);

echo "✅ Fichier chiffré généré : $outputFile\n";

// ------------------------------
// 4️⃣ Lecture et déchiffrement
// ------------------------------
$fileContent = file_get_contents($outputFile);

// Taille exacte du header encodé
$headerSize = strlen($encodedHeader);

// Lire uniquement le header
$headerRead = substr($fileContent, 0, $headerSize);
$decodedHeaderArray = Header::decode($headerRead);

// Extraire la payload chiffrée
$encryptedRead = substr($fileContent, $headerSize);

// Écrire la payload chiffrée dans un fichier temporaire pour déchiffrement
$tempEncryptedPayloadFile = __DIR__ . '/temp_payload.bin';
file_put_contents($tempEncryptedPayloadFile, $encryptedRead);

// Déchiffrement dans le fichier final
$cipherForDecrypt = new Cipher($decodedHeaderArray['iv']);
$decryptedFile = __DIR__ . '/decrypted_output.txt';
$cipherForDecrypt->decryptFile($tempEncryptedPayloadFile, $decryptedFile);

unlink($tempEncryptedPayloadFile);

echo "✅ Déchiffrement terminé. Vérifie '$decryptedFile'.\n";