<?php
declare(strict_types=1);

namespace MbarekCipher;

class DeCubeEnhanced {
    private array $cube;   // cube [couche][face]
    private array $key;    
    private int $couches;
    private int $faces;
    private array $cacheLancer = [];
    public int $lancerCalls = 0;
    public array $profile = []; 

    private array $buffer = [];
    private int $bufferIndex = 0;
    private int $bufferSize = 32;

    public function __construct(string $keyStr, int $couches = 3, int $faces = 6) {
        $this->couches = $couches;
        $this->faces = $faces;
        $this->key = array_map('ord', str_split($keyStr));

        $this->initCube();
        $this->rotateFaces();
        $this->interactFacesOpposees();
        $this->interactCouches();
    }

    private function initCube(): void {
        $this->cube = [];
        $keyIndex = 0;
        for ($c = 0; $c < $this->couches; $c++) {
            $this->cube[$c] = [];
            for ($f = 0; $f < $this->faces; $f++) {
                $this->cube[$c][$f] = (($f + 1) ^ $this->key[$keyIndex % count($this->key)]) % 256;
                $keyIndex++;
            }
        }
    }

    private function rotateFaces(): void {
        foreach ($this->cube as $c => &$faces) {
            $rot = $this->key[$c % count($this->key)] % $this->faces;
            $faces = array_merge(array_slice($faces, $rot), array_slice($faces, 0, $rot));
        }
    }

    private function interactFacesOpposees(): void {
        foreach ($this->cube as &$faces) {
            for ($i = 0; $i < floor($this->faces / 2); $i++) {
                $opposite = $this->faces - $i - 1;
                $faces[$opposite] = ($faces[$opposite] + $faces[$i]) % 256;
            }
        }
    }

    private function interactCouches(): void {
        for ($c = 1; $c < $this->couches; $c++) {
            for ($f = 0; $f < $this->faces; $f++) {
                $this->cube[$c][$f] = ($this->cube[$c][$f] + $this->cube[$c-1][$f]) % 256;
            }
        }
    }

     /**
     * 🔥 Génère un byte unique déterministe
     */
    private array $hashCache = [];
    public function lancer(int $index): int
    {
       $this->profile['deCube_calls'] = ($this->profile['deCube_calls'] ?? 0) + 1;

        $key = $this->normalizeKey();

        $blockId = intdiv($index, 32);
        $offset  = $index % 32;

        $hash = hash('sha256', $key . '|' . $blockId, true);

        return ord($hash[$offset]);
    }
    public function lanceryy(int $index): int
    {
        $this->profile['deCube_calls'] = ($this->profile['deCube_calls'] ?? 0) + 1;

        $key = $this->normalizeKey();

        // 🔥 regrouper les index par bloc de 32
        $blockId = intdiv($index, 32);
        $offset  = $index % 32;

        static $cache = [];

        if (!isset($cache[$blockId])) {
            $cache[$blockId] = hash('sha256', $key . '|' . $blockId, true);
        }

        return ord($cache[$blockId][$offset]);
    }

    public function lancerxx(int $index): int
    {
        $this->profile['deCube_calls'] = ($this->profile['deCube_calls'] ?? 0) + 1;

        $block = intdiv($index, 32);
        $offset = $index % 32;

        if (!isset($this->hashCache[$block])) {
            $key = $this->normalizeKey();
            $base = $block << 8;

            $this->hashCache[$block] =
                hash('sha256', $key . '|' . $base, true);
        }

        return ord($this->hashCache[$block][$offset]);
    }
    // Getters
    public function getCouche(): int { return $this->couches; }
    public function getFace(): int { return $this->faces; }
    
    /**
     * 🔥 normalisation de la cle
     */
   
    private function normalizeKey(): string
    {
        if (is_array($this->key)) {
            return implode('|', array_map('strval', $this->key));
        }

        return (string)$this->key;
    }
    public function getProfile(): array
    {
        return $this->profile;
    }
    }
?>