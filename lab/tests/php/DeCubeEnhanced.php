<?php
declare(strict_types=1);

namespace MbarekCipher;

class DeCubeEnhanced {
    private array $cube;   // cube [couche][face]
    private array $key;    
    private int $couches;
    private int $faces;

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

    // ✅ Retourne valeur du cube pour couche et face
    public function lancer(int $couche, int $face): int {
        $couche %= $this->couches;
        $face   %= $this->faces;
        return $this->cube[$couche][$face];
    }

    // Getters
    public function getCouche(): int { return $this->couches; }
    public function getFace(): int { return $this->faces; }
}
?>