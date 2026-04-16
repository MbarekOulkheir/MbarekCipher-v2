<?php
declare(strict_types=1);

namespace MbarekCipher;

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/classes/DeCubeEnhanced.php';
require_once __DIR__ . '/classes/Matrix.php';
require_once __DIR__ . '/classes/LayerTransformer.php';
require_once __DIR__ . '/classes/Header.php';

use MbarekCipher\DeCubeEnhanced;
use MbarekCipher\Matrix;
use MbarekCipher\LayerTransformer;
use MbarekCipher\Header;

class Cipher {

    private string $key;
    private int $blockSize;
    private DeCubeEnhanced $deCube;
    private bool $debug = false;
    private const ROUNDS = 2; // rounds fixes

    public function __construct(string $key, int $blockSize = 64, bool $debug = false) {
        $this->key = $key;
        $this->blockSize = $blockSize;
        $this->debug = $debug;

        // Initialisation unique du DeCube
        $this->deCube = new DeCubeEnhanced($key, 8, 32);
    }

    public function setDebug(bool $debug): void {
        $this->debug = $debug;
    }

    // ================== ENCRYPT FILE ==================
    public function encryptFile(string $inputFile, string $outputFile): void {
        if (!file_exists($inputFile)) throw new \Exception("Fichier source inexistant : $inputFile");

        $dataLen = filesize($inputFile);
        $fpIn = fopen($inputFile, 'rb');
        $fpOut = fopen($outputFile, 'wb');

        // IV global + hash
        $globalIV = random_bytes($this->blockSize);
        $hash = hash_file('sha256', $inputFile);

        // Header
        $headerObj = new Header(
            base64_encode($globalIV),
            ['block_size' => $this->blockSize, 'original_len' => $dataLen, 'hash' => $hash]
        );
        $headerEncoded = $headerObj->encode();
        fwrite($fpOut, pack('N', strlen($headerEncoded)));
        fwrite($fpOut, $headerEncoded);

        $prev = $this->initPrev($globalIV);
        $blockIndex = 0;

        while (!feof($fpIn)) {
            $block = fread($fpIn, $this->blockSize);
            if ($block === false || $block === '') break;
            $realBlockSize = strlen($block);

            $blockIV = $this->generateDynamicIV($globalIV, $blockIndex, $prev, $realBlockSize);
            $encryptedRaw = $this->encrypt3D($block, $blockIndex, $blockIV);

            fwrite($fpOut, $encryptedRaw);

            if ($this->debug) $this->logDebug($block, $encryptedRaw);

            $prev = $encryptedRaw;
            $blockIndex++;
        }

        fclose($fpIn);
        fclose($fpOut);

        if ($this->debug) echo "✅ Fichier chiffré : $outputFile\nHash : $hash\n";
    }

    // ================== DECRYPT FILE ==================
    public function decryptFile(string $inputFile, string $outputFile): void {
        if (!file_exists($inputFile)) throw new \Exception("Fichier chiffré inexistant : $inputFile");

        $fpIn = fopen($inputFile, 'rb');
        $headerSize = unpack('N', fread($fpIn, 4))[1];
        $headerRaw = fread($fpIn, $headerSize);
        $header = Header::decode($headerRaw);

        $globalIV = base64_decode($header['iv']);
        $blockSize = (int)$header['meta']['block_size'];
        $originalLen = (int)$header['meta']['original_len'];

        $fpOut = fopen($outputFile, 'wb');
        $prev = $this->initPrev($globalIV);
        $blockIndex = 0;

        while (!feof($fpIn)) {
            $remaining = $originalLen - $blockIndex * $blockSize;
            if ($remaining <= 0) break;

            $realBlockSize = min($blockSize, $remaining);
            $block = fread($fpIn, $realBlockSize);
            if ($block === false || $block === '') break;

            $blockIV = $this->generateDynamicIV($globalIV, $blockIndex, $prev, $realBlockSize);
            $decryptedBlock = $this->decrypt3D($block, $blockIndex, $blockIV);

            fwrite($fpOut, $decryptedBlock);
            $prev = $block;
            $blockIndex++;
        }

        ftruncate($fpOut, $originalLen);
        fclose($fpIn);
        fclose($fpOut);

        if ($this->debug) echo "✅ Fichier déchiffré : $outputFile\n";
    }

    // ================== 3D ENCRYPTION ==================
// ================== 3D ENCRYPTION AMELIOREE ==================
public function encrypt3D(string $block, int $blockIndex, string $blockIV): string {
    // Conversion ASCII → entiers une seule fois
    $codes = array_map('ord', str_split($block));
    $len = count($codes);

    // 2 rounds fixes
    for ($r = 0; $r < self::ROUNDS; $r++) {
        for ($i = 0; $i < $len; $i++) {
            $couche = ($blockIndex + $r + $i) % $this->deCube->getCouche();
            $face   = ($i + $r) % $this->deCube->getFace();

            $v = $codes[$i] ^ $this->deCube->lancer($couche, $face);
            $v = ($v + $codes[($i + 1) % $len]) % 256; // op ASCII/byte
            $codes[$i] = $v;
        }
        $codes = $this->permuteBlock($codes, $r);
    }

    // IV dynamique
    for ($i = 0; $i < $len; $i++) {
        $codes[$i] ^= ord($blockIV[$i % strlen($blockIV)]);
    }

    // Layer transformation
 
    $matrix = new Matrix();
    $matrix->setData($codes);
    $layer = $this->layerEncrypt($matrix->data);

    return implode('', array_map(fn($v) => chr($v), $layer));

    
}

public function decrypt3D(string $block, int $blockIndex, string $blockIV): string {
    // Conversion ASCII → entiers dès le départ
    $codes = array_map('ord', str_split($block));
    $codes = $this->layerDecrypt($codes);
    $len = count($codes);

    // IV dynamique
    for ($i = 0; $i < $len; $i++) {
        $codes[$i] ^= ord($blockIV[$i % strlen($blockIV)]);
    }

    // 2 rounds fixes inversées
    for ($r = self::ROUNDS - 1; $r >= 0; $r--) {
        $codes = $this->inversePermuteBlock($codes, $r);
        for ($i = $len - 1; $i >= 0; $i--) {
            $couche = ($blockIndex + $r + $i) % $this->deCube->getCouche();
            $face   = ($i + $r) % $this->deCube->getFace();

            $v = ($codes[$i] - $codes[($i + 1) % $len] + 256) % 256;
            $v ^= $this->deCube->lancer($couche, $face);
            $codes[$i] = $v;
        }
    }

    return implode('', array_map(fn($v) => chr($v), $codes));
}

    // ================== IV DYNAMIQUE ==================
    public function generateDynamicIV(string $globalIV, int $blockIndex, string $prevBlock, int $length): string {
        $iv = hash('sha256', $globalIV . pack('N', $blockIndex) . $prevBlock, true);
        $dynamicIV = '';
        for ($i = 0; $i < $length; $i++) {
            $val = ord($iv[$i % strlen($iv)]) ^ $this->deCube->lancer($i % $this->deCube->getCouche(), $i % $this->deCube->getFace());
            $dynamicIV .= chr($val);
        }
        return $dynamicIV;
    }

    // ================== PERMUTATION ==================
// ================== PERMUTATION OPTIMISÉE ==================
private function permuteBlock(array $block, int $seed): array {
    $len = count($block);
    $perm = range(0, $len - 1);

    // Génération d'un flux pseudo-aléatoire basé sur le seed + clé
    $hash = hash('sha256', $this->key . $seed, true);
    $hLen = strlen($hash);

    for ($i = $len - 1; $i > 0; $i--) {
        $j = ord($hash[$i % $hLen]) % ($i + 1);
        if ($i !== $j) {
            [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]]; // swap in-place
        }
    }

    // Application de la permutation directement in-place
    $permuted = [];
    foreach ($perm as $idx) {
        $permuted[] = $block[$idx];
    }

    return $permuted;
}

private function inversePermuteBlock(array $block, int $seed): array {
    $len = count($block);
    $perm = range(0, $len - 1);

    $hash = hash('sha256', $this->key . $seed, true);
    $hLen = strlen($hash);

    for ($i = $len - 1; $i > 0; $i--) {
        $j = ord($hash[$i % $hLen]) % ($i + 1);
        if ($i !== $j) {
            [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]]; // swap in-place
        }
    }

    // Inverse de la permutation
    $inverse = array_fill(0, $len, 0);
    foreach ($perm as $i => $p) $inverse[$p] = $i;

    $restored = [];
    foreach ($inverse as $idx) {
        $restored[] = $block[$idx];
    }

    return $restored;
}

    private function getPermutation(int $len, int $seed): array {
        $perm = range(0, $len - 1);
        $hash = hash('sha256', $this->key . $seed, true);
        $hLen = strlen($hash);
        for ($i = $len - 1; $i > 0; $i--) {
            $j = ord($hash[$i % $hLen]) % ($i + 1);
            [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]];
        }
        return $perm;
    }

    // ================== LAYER ==================
    private function layerEncrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->apply();
    }

    private function layerDecrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->revert();
    }

    // ================== HELPERS ==================
    private function logDebug(string $before, string $after): void {
        echo "Bloc avant 3D : " . substr($before,0,64) . "...\n";
        echo "Bloc après 3D : " . substr($after,0,64) . "...\n";
    }

    public function initPrev(string $globalIV): string {
        $lenIV = strlen($globalIV);
        $lenKey = strlen($this->key);
        $prev = '';
        for ($i = 0; $i < $lenIV; $i++) {
            $byte = ord($globalIV[$i]);
            $couche = ord($this->key[$i % $lenKey]) % $this->deCube->getCouche();
            $face   = ord($globalIV[$i % $lenIV]) % $this->deCube->getFace();
            $prev .= chr($byte ^ $this->deCube->lancer($couche, $face));
        }
        return $prev;
    }

}
?>