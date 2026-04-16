<?php
declare(strict_types=1);

namespace MbarekCipher;

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/classes/DeCubeEnhanced.php';
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
    private const ROUNDS = 2;
    private const SMALL_FILE_THRESHOLD = 2048;
    public array $profile = [
    'sections' => [],
    'deCube_calls' => 0,
];

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

        $isSmall = $dataLen < self::SMALL_FILE_THRESHOLD;

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
        //$prev = str_repeat("\0", strlen($globalIV));
        $blockIndex = 0;

        while (!feof($fpIn)) {
            $block = fread($fpIn, $this->blockSize);
            if ($block === false || $block === '') break;
            $realBlockSize = strlen($block);

            $blockIV = $this->generateDynamicIV($globalIV, $blockIndex, $prev, $realBlockSize);
/*
            if ($isSmall) {  // uniquement pour smal file
                $block = $this->smallFileBoostEncrypt($block, $globalIV, $blockIndex);
            } */
            $encryptedRaw = $this->encrypt3D($block, $blockIndex, $blockIV);

            fwrite($fpOut, $encryptedRaw);

            if ($this->debug) $this->logDebug($block, $encryptedRaw);

            $prev = $encryptedRaw;
            $blockIndex++;
        }

        fclose($fpIn);
        fclose($fpOut);

        if ($this->debug) echo "✅ Fichier chiffré : $outputFile\nHash : $hash\n";
        // compteur du nombre de calls de lancer()
        $this->profile['deCube_calls'] = $this->deCube->profile['deCube_calls'];
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

            /* if ($originalLen < self::SMALL_FILE_THRESHOLD) { // 
                $block = $this->smallFileBoostDecrypt($block, $globalIV, $blockIndex);
            }
            */
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

// ================== 3D ENCRYPTION AMELIOREE ==================
public function encrypt3D(string $block, int $blockIndex, string $blockIV): string {

    $len = strlen($block);

    // ⚡ conversion rapide (pas de array_map)
    $codes = [];
    for ($i = 0; $i < $len; $i++) {
        $codes[$i] = ord($block[$i]);
    }

    // ⚡ cache valeurs
    $couches = $this->deCube->getCouche();
    $faces   = $this->deCube->getFace();
    $ivLen   = strlen($blockIV);
    
    for ($r = 0; $r < self::ROUNDS; $r++) {
        $seed=0;
        $this->prof('core_encrypt_loop', function() use (&$codes, $len, $blockIndex, $r, $couches, $faces) 
        {
             $chunkSize = 32;
             $keyStream = 0;
            for ($i = 0; $i < $len; $i++) {

                $k = intdiv($i, $chunkSize);
                $local = $i % $chunkSize;
               //$seed = (($i * 13) ^ ($blockIndex << 1) ^ ($r << 3)) & 0xFF;

                if ($local === 0) {
                   $seed = $this->deCube->lancer($blockIndex ^ $k, $r);
                } else{
                    $seed = (($i * 13) ^ ($blockIndex << 1) ^ ($r << 3)) & 0xFF;  //dernnier modif faire rentrer seed dans le test
                }                                   // car elle s'excecute comme ca poul local === 0
                $keyStream = $seed ^ $local;
                $next = $codes[($i + 1) % $len];

                $v = ($codes[$i] ^ $keyStream);
                $v = ($v + (($next ^ $keyStream) & 0xFF)) & 0xFF;

                $codes[$i] = $v;
            }
        
        });

        $this->prof('permute_block', function () use (&$codes, $r) {
            $this->permuteBlock($codes, $r);
        });

    }
   
    // IV dynamique (optimisé)
   $this->prof('iv_xor', function () use (&$codes, $blockIV, $len) {

        for ($i = 0; $i < $len; $i++) {
            $codes[$i] ^= ord($blockIV[$i % strlen($blockIV)]);
        }
    });

    // ⚠️ Layer (on garde mais optimisable après)
    //if (($blockIndex & 3) === 0 && $len > 64) {
        $layer = new LayerTransformer($codes, $this->key);
        $codes = $layer->apply();
    //}

    // ⚡ conversion finale rapide
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= chr($codes[$i]);
    }

    return $out;
}

public function decrypt3D(string $block, int $blockIndex, string $blockIV): string {

    $len = strlen($block);

    // conversion rapide
    $codes = [];
    for ($i = 0; $i < $len; $i++) {
        $codes[$i] = ord($block[$i]);
    }

    // ⚡ layer inverse (si utilisé en encrypt)
    //if (($blockIndex & 3) === 0 && $len > 64) {
        $layer = new LayerTransformer($codes, $this->key);
        $codes = $layer->revert();
    //}
    $couches = $this->deCube->getCouche();
    $faces   = $this->deCube->getFace();
    $ivLen   = strlen($blockIV);

    // IV inverse (doit être avant rounds)
    for ($i = 0; $i < $len; $i++) {
        $codes[$i] ^= ord($blockIV[$i % $ivLen]);
    }
   
    // rounds inversés
    for ($r = self::ROUNDS - 1; $r >= 0; $r--) {
         $seed=0;
        $codes = $this->inversePermuteBlock($codes, $r);
        $chunkSize = 32;
         $keyStream = 0;
        for ($i = $len - 1; $i >= 0; $i--) {

            $k = intdiv($i, $chunkSize);
            $local = $i % $chunkSize;
            //$seed = (($i * 13) ^ ($blockIndex << 1) ^ ($r << 3)) & 0xFF;
            if ($local === 0) {
                $seed = $this->deCube->lancer($blockIndex ^ $k, $r);
            } else {
                    $seed = (($i * 13) ^ ($blockIndex << 1) ^ ($r << 3)) & 0xFF;
                }
            $keyStream = $seed ^ $local;
            $next = $codes[($i + 1) % $len];
            // 1. retirer addition
            $v = ($codes[$i] - (($next ^ $keyStream) & 0xFF) + 256) & 0xFF;
            // 2. retirer XOR
            $v ^= $keyStream;

           /* $prev = ord($block[$i - 1] ?? "\0");
            if ($len < 64) {
                $v ^= ($prev * 17) & 0xFF;
            }*/
            $codes[$i] = $v;
        }
    }

    // conversion finale rapide
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= chr($codes[$i]);
    }

    return $out;
}
    // ================== IV DYNAMIQUE ==================
    public function generateDynamicIV(
        string $globalIV,
        int $blockIndex,
        string $prevBlock,
        int $length
    ): string {

        // 1. base entropy (stable + strong)
        $ctx = $globalIV
            . pack('N', $blockIndex)
            . $prevBlock;

        // 2. single hash (no loop dependency)
        $seed = hash('sha256', $ctx, true);

        $out = '';
        $hLen = strlen($seed);

        // 3. expansion deterministic (fast + reversible)
        for ($i = 0; $i < $length; $i++) {

            // avoid lancer() here (major gain)
            $b = ord($seed[$i % $hLen]);

            // lightweight diffusion only
            $b ^= ($i * 31) & 0xFF;
            $b ^= ord($globalIV[$i % strlen($globalIV)]);

            $out .= chr($b);
        }

        return $out;
    }

// ================== PERMUTATION OPTIMISÉE ==================
private function permuteBlock(array &$block, int $seed): void {

    $len = count($block);
    $perm = range(0, $len - 1);

    $hash = hash('sha256', $this->key . $seed, true);
    $hLen = strlen($hash);

    for ($i = $len - 1; $i > 0; $i--) {
        $j = ord($hash[$i % $hLen]) % ($i + 1);
        [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]];
    }

    // 🔥 swap direct sur le tableau original
    $tmp = $block;
    for ($i = 0; $i < $len; $i++) {
        $block[$i] = $tmp[$perm[$i]];
    }
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

    // ================== LAYER ================== 
    private function layerEncrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->apply();
    }

    private function layerDecrypt(array $matrix): array {
        $layer = new LayerTransformer($matrix, $this->key);
        return $layer->revert();
    }
    public  function smallFileBoostEncrypt(string $data, string $iv, int $index): string
    {
        // PASS 1: padding léger
        $data = $this->boostPad($data, $iv, $index);

        // PASS 2: entropy injection
        $data = $this->boostEntropy($data, $iv, $index);

        // PASS 3: shuffle
        $data = $this->boostShuffle($data, $iv, $index);

        return $data;
    }
    public function smallFileBoostDecrypt(string $data, string $iv, int $index): string
    {
        // inverse shuffle
        $data = $this->boostUnshuffle($data, $iv, $index);

        // remove entropy
        $data = $this->boostRemoveEntropy($data, $iv, $index);

        // remove padding
        $data = $this->boostUnpad($data, $iv, $index);

        return $data;
    }
    private function boostPad(string $data, string $iv, int $i): string
    {
        $padLen = (strlen($iv) + $i) % 4;
        return $data . str_repeat(chr($padLen), $padLen);
    }
    private function boostEntropy(string $data, string $iv, int $i): string
    {
        $out = '';
        for ($j = 0; $j < strlen($data); $j++) {
            $noise = ord(hash('sha256', $iv . $i . $j, true)[0]);
            $out .= chr((ord($data[$j]) ^ $noise) & 0xFF);
        }
        return $out;
    }
    private function boostShuffle(string $data, string $iv, int $i): string
    {
        $arr = str_split($data);
        $len = count($arr);

        for ($k = $len - 1; $k > 0; $k--) {
            $j = ord(hash('sha256', $iv . $i . $k, true)[0]) % ($k + 1);
            [$arr[$k], $arr[$j]] = [$arr[$j], $arr[$k]];
        }

        return implode('', $arr);
    }
    private function boostUnshuffle(string $data, string $iv, int $i): string
    {
        $arr = str_split($data);
        $len = count($arr);

        $order = range(0, $len - 1);

        for ($k = $len - 1; $k > 0; $k--) {
            $j = ord(hash('sha256', $iv . $i . $k, true)[0]) % ($k + 1);
            [$order[$k], $order[$j]] = [$order[$j], $order[$k]];
        }

        $inv = array_fill(0, $len, 0);
        foreach ($order as $i2 => $p) {
            $inv[$p] = $i2;
        }

        $out = [];
        foreach ($inv as $idx) {
            $out[] = $arr[$idx];
        }

        return implode('', $out);
    }
    private function boostRemoveEntropy(string $data, string $iv, int $i): string
    {
        return $this->boostEntropy($data, $iv, $i); // XOR reversible
    }
    private function boostUnpad(string $data): string
    {
        $len = strlen($data);
        if ($len === 0) return $data;

        $pad = ord($data[$len - 1]);
        if ($pad > 4) return $data;

        return substr($data, 0, $len - $pad);
    }
    
    private function prof(string $name, callable $fn)
    {
        $start = microtime(true);

        $res = $fn();

        $dt = microtime(true) - $start;

        if (!isset($this->profile['sections'][$name])) {
            $this->profile['sections'][$name] = 0;
        }

        $this->profile['sections'][$name] += $dt;

        return $res;
    }
    public function getProfile(): array
    {
        return $this->profile;
    }

    }
?>