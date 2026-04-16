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

        // === Round 1 & 2 ===
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $val = $this->matrix[$r][$c];
                $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
                $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);
                $newMatrix[$r][$c] = ($val + $keyOffset + $randOffset) % 256;
            }
        }

        // === Round 3 - diffusion locale avant shift ===
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $prev = $c === 0 ? 0 : $newMatrix[$r][$c - 1];
                $newMatrix[$r][$c] = ($newMatrix[$r][$c] + $prev) % 256;
            }
        }

        // === Shift / rotation circulaire ===
        for ($r = 0; $r < $rows; $r++) {
            $newMatrix[$r] = array_merge(array_slice($newMatrix[$r], 1), array_slice($newMatrix[$r], 0, 1));
        }

        return $this->is1D ? array_map(fn($v) => $v[0], $newMatrix) : $newMatrix;
    }

public function revert(): array {
    $rows = count($this->matrix);
    $cols = count($this->matrix[0]);
    $newMatrix = $this->matrix;

    // === Shift inverse / rotation circulaire inverse ===
    for ($r = 0; $r < $rows; $r++) {
        $newMatrix[$r] = array_merge(array_slice($newMatrix[$r], -1), array_slice($newMatrix[$r], 0, $cols - 1));
    }

    // === Round 3 inverse - diffusion locale ===
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $prev = $c === 0 ? 0 : $newMatrix[$r][$c - 1];
            $newMatrix[$r][$c] = ($newMatrix[$r][$c] - $prev) % 256;
            if ($newMatrix[$r][$c] < 0) $newMatrix[$r][$c] += 256;
        }
    }

    // === Round 1 & 2 inverse ===
    $this->prngState = crc32($this->key); // réinitialiser PRNG si nécessaire
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
            $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);
            $newMatrix[$r][$c] = ($newMatrix[$r][$c] - $keyOffset - $randOffset) % 256;
            if ($newMatrix[$r][$c] < 0) $newMatrix[$r][$c] += 256;
        }
    }

    return $this->is1D ? array_map(fn($v) => $v[0], $newMatrix) : $newMatrix;
}
}
?>