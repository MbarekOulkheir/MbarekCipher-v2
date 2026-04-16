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
             $chunkSize = 64;
             $keyStream = 0;
            for ($i = 0; $i < $len; $i++) {

                $k = intdiv($i, $chunkSize);
                $local = $i % $chunkSize;

                if ($local === 0) {
                   $seed = $this->deCube->lancer($blockIndex ^ $k, $r);
                } else{
                    $seed = (($i * 13) ^ ($blockIndex << 1) ^ ($r << 3)) & 0xFF; 
                }                                   
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
    $layer = new LayerTransformer($codes, $this->key);
    $codes = $layer->apply();
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
        $layer = new LayerTransformer($codes, $this->key);
        $codes = $layer->revert();
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
            $this->inversePermuteBlock($codes, $r);
            $chunkSize = 64;
            $keyStream = 0;
            for ($i = $len - 1; $i >= 0; $i--) {

                $k = intdiv($i, $chunkSize);
                $local = $i % $chunkSize;
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

                [$block[$i], $block[$j]] = [$block[$j], $block[$i]];

            }
        }
        private function inversePermuteBlock(array &$block, int $seed): void {

        $len = count($block);

        $hash = hash('sha256', $this->key . $seed, true);
        $hLen = strlen($hash);

        for ($i = 1; $i < $len; $i++) {

            $j = ord($hash[$i % $hLen]) % ($i + 1);

            // swap inverse logique
            [$block[$i], $block[$j]] = [$block[$j], $block[$i]];
        }
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