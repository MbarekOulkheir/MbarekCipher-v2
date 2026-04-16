<?php
declare(strict_types=1);

use MbarekCipher\Cipher;
require_once __DIR__ . '/../src/Cipherenc3D.php';
//require_once __DIR__ . '/../src/Cipher.php';
require_once __DIR__ . '/../src/Utils.php';

$key = "maCleSecrete123";
$tmpDir = __DIR__ . '/../tmp';

// Création du dossier tmp si inexistant
if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

// ================== FICHIERS DE TEST ==================
$files = [
    'petit' => $tmpDir . '/input_petit.txt'
    ];
    /*
    'moyen' => $tmpDir . '/input_moyen.txt',
    'gros'  => $tmpDir . '/input_gros.txt',
];
*/
// Génération fichiers
file_put_contents($files['petit'], "ABCDEF"); // 6 bytes
//file_put_contents($files['moyen'], str_repeat("MFILE", 200_000)); // ~1 MB
//file_put_contents($files['gros'], str_repeat("BIGFILE", 1_500_000)); // ~10 MB

// ================== TEST ==================
//$cipher = new Cipher($key);
$cipher = new Cipher($key, 2048); // debug activé
$cipher->setDebug(false); // pour ne plus afficher les logs

// Ou activer/désactiver après création

foreach ($files as $size => $inputFile) {
    echo "\n=== TEST $size ===\n";
    $encryptedFile = str_replace('.txt', '_8_32_2048_enc3D.mbk', $inputFile);
    $decryptedFile = str_replace('.txt', '_dec.txt', $inputFile);

    $dataOriginal = file_get_contents($inputFile);
    echo "Bloc original : " . substr($dataOriginal,0,50) . (strlen($dataOriginal)>50?"...":"") . "\n";

    // ----- CHIFFREMENT -----
    $time_start = microtime(true);
    try {
        $cipher->encryptFile($inputFile, $encryptedFile);
        
    } catch (\Exception $e) {
        echo "Erreur chiffrement : " . $e->getMessage() . "\n";
        continue;
    }
    $time_end = microtime(true);
    $timech = $time_end - $time_start;
    echo "Temps de chiffrement : $timech\n";

    // ----- DÉCHIFFREMENT -----
    $time_start = microtime(true);
    try {
        $cipher->decryptFile($encryptedFile, $decryptedFile);
        $decryptedData = file_get_contents($decryptedFile);
        $status = ($decryptedData === $dataOriginal) ? "✅ OK" : "❌ KO";
        echo "Vérification contenu : $status\n";
        echo "Bloc déchiffré : " . substr($decryptedData,0,50) . (strlen($decryptedData)>50?"...":"") . "\n";
    } catch (\Exception $e) {
        echo "Erreur déchiffrement : " . $e->getMessage() . "\n";
    }
    $time_end = microtime(true);
    $timede = $time_end - $time_start;
    echo "Temps de dechiffrement : $timede\n";
    echo "Temps total chiffrement et dechiffrement :".($timede + $timech)."\n";
}
?>