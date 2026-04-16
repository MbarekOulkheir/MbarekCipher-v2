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
    private int $rounds;
    private DeCubeEnhanced $deCube;
    private bool $debug = false;

    public function __construct(string $key, int $blockSize = 64, int $rounds = 2, bool $debug = false) {
        $this->key = $key;
        $this->blockSize = $blockSize;
        $this->rounds = $rounds;
        $this->debug = $debug;

        // Initialisation unique du DeCube
        $this->deCube = new DeCubeEnhanced($key, 8, 32);
    }

    public function setDebug(bool $debug): void {
        $this->debug = $debug;
    }

    // ================== FILE ENCRYPTION ==================
    public function encryptFile(string $inputFile, string $outputFile, bool $useDNA = true): void {
        if (!file_exists($inputFile)) throw new \Exception("Fichier source inexistant : $inputFile");

        $dataLen = filesize($inputFile);
        $fpIn = fopen($inputFile, 'rb');
        $fpOut = fopen($outputFile, 'wb');

        $globalIV = random_bytes($this->blockSize);
        $hash = hash_file('sha256', $inputFile);

        $headerObj = new Header(
            base64_encode($globalIV),
            [
                'block_size' => $this->blockSize,
                'original_len' => $dataLen,
                'hash' => $hash
            ]
        );

        $headerEncoded = $headerObj->encode();
        fwrite($fpOut, pack('N', strlen($headerEncoded)));
        fwrite($fpOut, $headerEncoded);
        //------appel initPrev
        $prev = $this->initPrev($globalIV);
        
        $blockIndex = 0;

        while (!feof($fpIn)) {
            $block = fread($fpIn, $this->blockSize);
            if ($block === false || $block === '') break;
            $realBlockSize = strlen($block);

            $blockIV = $this->generateDynamicIV($globalIV, $blockIndex, $prev, $realBlockSize);

            $encryptedRaw = $this->encrypt3D($block, $blockIndex, $blockIV);

            fwrite($fpOut, $encryptedRaw);

            if ($this->debug) {
                echo "Bloc avant 3D : " . substr($block,0,64) . "...\n";
                echo "Bloc après 3D : " . substr($encryptedRaw,0,64) . "...\n";
            }

            $prev = $encryptedRaw;
            $blockIndex++;
        }

        fclose($fpIn);
        fclose($fpOut);

        if ($this->debug) echo "✅ Fichier chiffré : $outputFile\nHash : $hash\n";
    }

    // ================== FILE DECRYPTION ==================
    public function decryptFile(string $inputFile, string $outputFile, bool $useDNA = true): void {
        if (!file_exists($inputFile)) throw new \Exception("Fichier chiffré inexistant : $inputFile");

        $fpIn = fopen($inputFile, 'rb');
        $headerSizeData = fread($fpIn, 4);
        $headerSize = unpack('N', $headerSizeData)[1];
        $headerRaw = fread($fpIn, $headerSize);
        $header = Header::decode($headerRaw);

        $globalIV = base64_decode($header['iv']);
        $blockSize = (int)$header['meta']['block_size'];
        $originalLen = (int)$header['meta']['original_len'];
        $originalHash = $header['meta']['hash'];

        $fpOut = fopen($outputFile, 'wb');

        //------appel initPrev
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

        if ($this->debug) echo "✅ Fichier déchiffré : $outputFile\nHash vérifié : $originalHash\n";
    }

    // ================== 3D ENCRYPTION ==================
    private function encrypt3D(string $block, int $blockIndex, string $blockIV): string {
        $len = strlen($block);
        $flat = str_split($block); // travail direct en 1D

        for ($r = 0; $r < $this->rounds; $r++) {
            foreach ($flat as $i => $val) {
                $couche = ($blockIndex + $r + $i) % $this->deCube->getCouche();
                $face   = ($i + $r) % $this->deCube->getFace();
                $v      = ord($val) ^ $this->deCube->lancer($couche, $face);
                $v      = ($v + ord($flat[($i + 1) % $len])) % 256;
                $flat[$i] = chr($v);
            }
            $flat = $this->permuteBlock($flat, $r);
        }

        // Appliquer l’IV dynamique une seule fois
        foreach ($flat as $i => $val) {
            $flat[$i] = chr(ord($val) ^ ord($blockIV[$i % strlen($blockIV)]));
        }

        // Ensuite seulement on met dans la matrice pour layerEncrypt
        $matrix = new Matrix();
        $matrix->setData($flat);
        $layer = $this->layerEncrypt($matrix->data);
        return implode('', array_map(fn($v) => chr($v), $layer));
    }

    private function decrypt3D(string $block, int $blockIndex, string $blockIV): string {
        // Conversion en array d'entiers
        $flat = array_map('ord', str_split($block));

        // Revert layer
        $flat = $this->layerDecrypt($flat);
        $flat = array_map(fn($v) => chr($v), $flat);

        // Appliquer l'IV dynamique
        foreach ($flat as $i => $val) {
            $flat[$i] = chr(ord($val) ^ ord($blockIV[$i % strlen($blockIV)]));
        }

        // Boucle inverse des rounds
        for ($r = $this->rounds - 1; $r >= 0; $r--) {
            $flat = $this->inversePermuteBlock($flat, $r);

            $len = count($flat);
            for ($i = $len - 1; $i >= 0; $i--) {
                $couche = ($blockIndex + $r + $i) % $this->deCube->getCouche();
                $face   = ($i + $r) % $this->deCube->getFace();
                $v      = ord($flat[$i]);
                $v      = ($v - ord($flat[($i + 1) % $len]) + 256) % 256;
                $v      ^= $this->deCube->lancer($couche, $face);
                $flat[$i] = chr($v);
            }
        }

        return implode('', $flat);
    }

    // ================== IV DYNAMIQUE ==================
    public function generateDynamicIV(string $globalIV, int $blockIndex, string $prevBlock, int $length): string {
        $iv = hash('sha256', $globalIV . pack('N', $blockIndex) . $prevBlock, true);
        $dynamicIV = '';
        for ($i=0; $i<$length; $i++) {
            $val = ord($iv[$i % strlen($iv)]) ^ $this->deCube->lancer($i % $this->deCube->getCouche(), $i % $this->deCube->getFace());
            $dynamicIV .= chr($val);
        }
        return $dynamicIV;
    }

    // ================== PERMUTATION ==================
    private function permuteBlock(array $block, int $seed): array {
        $len = count($block);
        $hashSeed = unpack('L', substr(hash('sha256',$this->key.$seed,true),0,4))[1];
        $perm = $this->getPermutation($len, $hashSeed);
        $newBlock = [];
        foreach($perm as $i) $newBlock[] = $block[$i];
        return $newBlock;
    }

    private function inversePermuteBlock(array $block, int $seed): array {
        $len = count($block);
        $hashSeed = unpack('L', substr(hash('sha256',$this->key.$seed,true),0,4))[1];
        $perm = $this->getPermutation($len, $hashSeed);
        $inv = array_fill(0,$len,0);
        foreach($perm as $i=>$p) $inv[$p] = $i;
        $newBlock = [];
        for($i=0;$i<$len;$i++) $newBlock[] = $block[$inv[$i]];
        return $newBlock;
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
    public function layerEncrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->apply();
    }

    public function layerDecrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->revert();
    }

    // ================== GETTERS ==================
    public function getRounds(): int { return $this->rounds; }
    public function getDeCube(): DeCubeEnhanced { return $this->deCube; }

    // ================== RAW AES ==================
    public function encryptRaw(string $rawBlock, string $iv): string {
        $encrypted = openssl_encrypt($rawBlock, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) throw new \RuntimeException("Erreur lors du chiffrement RAW");
        return $encrypted;
    }

    public function decryptRaw(string $rawBlock, string $iv): string {
        $decrypted = openssl_decrypt($rawBlock, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) throw new \RuntimeException("Erreur lors du déchiffrement RAW");
        return $decrypted;
    }

    public function decryptFileContent(string $data): string {
        $tempFile = sys_get_temp_dir() . '/_tempDecrypt.mbk';
        file_put_contents($tempFile, $data);
        $output = sys_get_temp_dir() . '/_tempOutput.txt';
        $this->decryptFile($tempFile, $output);
        return file_get_contents($output);
    }
    private function initPrev(string $globalIV): string {
        $lenIV = strlen($globalIV);
        $lenKey = strlen($this->key);
        $prev = '';
        for ($i = 0; $i < $lenIV; $i++) {
            $byte = ord($globalIV[$i]);
            $couche = ord($this->key[$i % $lenKey]) % $this->deCube->getCouche();
            $face   = ord($globalIV[$i % $lenIV]) % $this->deCube->getFace();
            $dice   = $this->deCube->lancer($couche, $face);
            $prev  .= chr($byte ^ $dice);
        }
        return $prev;
    }
}
?>