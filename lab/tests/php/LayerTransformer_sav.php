<?php
declare(strict_types=1);

namespace MbarekCipher;

class LayerTransformer {
    private array $matrix;
    private string $key;
    private bool $is1D = false;

    // PRNG interne
    private int $prngState;

    public function __construct(array $matrix, string $key) {
        $this->key = $key;

        // Détecter si c'est 1D
        if (!isset($matrix[0]) || !is_array($matrix[0])) {
            $this->matrix = array_map(fn($v) => [$v], $matrix);
            $this->is1D = true;
        } else {
            $this->matrix = $matrix;
        }

        // Initialiser PRNG interne avec hash de la clé
        $this->prngState = crc32($key);
    }

    // PRNG simple basé sur Linear Congruential Generator (LCG)
    private function nextInt(int $mod): int {
        $this->prngState = (1664525 * $this->prngState + 1013904223) & 0xFFFFFFFF;
        return $this->prngState % $mod;
    }

    public function apply(): array {
        $rows = count($this->matrix);
        $cols = count($this->matrix[0]);
        $newMatrix = $this->matrix;

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $val = is_string($this->matrix[$r][$c]) ? ord($this->matrix[$r][$c]) : $this->matrix[$r][$c];
                $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
                $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);
                
                $newMatrix[$r][$c] = ($val + $keyOffset + $randOffset) % 256;
            }
        }

        return $this->is1D ? array_map(fn($v) => $v[0], $newMatrix) : $newMatrix;
    }

    public function revert(): array {
        $rows = count($this->matrix);
        $cols = count($this->matrix[0]);
        $newMatrix = $this->matrix;

        // Réinitialiser PRNG pour avoir le même flux
        $this->prngState = crc32($this->key);

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $val = is_string($this->matrix[$r][$c]) ? ord($this->matrix[$r][$c]) : $this->matrix[$r][$c];
                $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
                $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);
                $newMatrix[$r][$c] = ($val - $keyOffset - $randOffset) % 256;
                if ($newMatrix[$r][$c] < 0) $newMatrix[$r][$c] += 256;
            }
        }

        return $this->is1D ? array_map(fn($v) => $v[0], $newMatrix) : $newMatrix;
    }
}
?>