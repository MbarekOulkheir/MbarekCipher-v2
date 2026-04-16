<?php
declare(strict_types=1);

namespace MbarekCipher;

require_once __DIR__ . '/classes/DeCubeEnhanced.php';

use MbarekCipher\DeCubeEnhanced;

class CipherASCII {

    private string $key;
    private int $blockSize;
    private DeCubeEnhanced $deCube;
    private const ROUNDS = 1; // juste 1 round pour simplifier

    public function __construct(string $key, int $blockSize = 64) {
        $this->key = $key;
        $this->blockSize = $blockSize;
        $this->deCube = new DeCubeEnhanced($key, 8, 32);
    }

    // ================== ENCRYPTION ==================
    public function encryptBlock(string $block, int $blockIndex, string $blockIV): string {
        $flat = str_split($block);
        $len = count($flat);

        // Round simple
        foreach ($flat as $i => $val) {
            $couche = ($blockIndex + $i) % $this->deCube->getCouche();
            $face   = $i % $this->deCube->getFace();
            $v = ord($val) ^ $this->deCube->lancer($couche, $face);
            $v = ($v + ord($flat[($i + 1) % $len])) % 128; // ASCII-only
            $flat[$i] = chr($v);
        }

        // IV XOR simple, modulo 128 pour rester ASCII
        foreach ($flat as $i => $val) {
            $flat[$i] = chr((ord($val) ^ ord($blockIV[$i % strlen($blockIV)])) % 128);
        }

        return implode('', $flat);
    }

    public function decryptBlock(string $block, int $blockIndex, string $blockIV): string {
        $flat = str_split($block);
        $len = count($flat);

        // IV XOR simple
        foreach ($flat as $i => $val) {
            $flat[$i] = chr((ord($val) ^ ord($blockIV[$i % strlen($blockIV)])) % 128);
        }

        // Round inversé simple
        for ($i = $len - 1; $i >= 0; $i--) {
            $couche = ($blockIndex + $i) % $this->deCube->getCouche();
            $face   = $i % $this->deCube->getFace();
            $v = (ord($flat[$i]) - ord($flat[($i + 1) % $len]) + 128) % 128;
            $v ^= $this->deCube->lancer($couche, $face);
            $flat[$i] = chr($v);
        }

        return implode('', $flat);
    }

    // ================== DYNAMIC IV ==================
    public function generateDynamicIV(string $globalIV, int $blockIndex, string $prevBlock, int $length): string {
        $iv = hash('sha256', $globalIV . pack('N', $blockIndex) . $prevBlock, true);
        $dynamicIV = '';
        for ($i = 0; $i < $length; $i++) {
            $val = ord($iv[$i % strlen($iv)]) ^ $this->deCube->lancer($i % $this->deCube->getCouche(), $i % $this->deCube->getFace());
            $dynamicIV .= chr($val % 128); // rester ASCII
        }
        return $dynamicIV;
    }
}