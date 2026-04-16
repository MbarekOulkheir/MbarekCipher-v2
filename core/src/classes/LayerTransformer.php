<?php
declare(strict_types=1);

namespace MbarekCipher;

class LayerTransformer {
    private array $matrix;
    private string $key;
    private bool $is1D = false;

    private int $prngState;

    public function __construct(array $matrix, string $key) {
        $this->key = $key;
        if (!isset($matrix[0]) || !is_array($matrix[0])) {
            $this->matrix = array_map(fn($v) => [$v], $matrix);
            $this->is1D = true;
        } else {
            $this->matrix = $matrix;
        }
        $this->prngState = crc32($key);
    }

    // =========================
    // PASSAGE 1 : NOISE SEED
    // =========================
    private function noisePass(array &$m, int $rows, int $cols): void {
        $seed = crc32($this->key . $this->prngState);

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $noise = ($seed + $r * 31 + $c * 17) & 0xFF;
                $m[$r][$c] = ($m[$r][$c] ^ $noise) & 0xFF;
            }
        }
    }

    // =========================
    // PASSAGE 2 : KEY MIX
    // =========================
    private function mixPass(array &$m, int $rows, int $cols): void {
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
                $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);

                $m[$r][$c] = ($m[$r][$c] + $keyOffset + $randOffset) % 256;
            }
        }
    }

    // =========================
    // PASSAGE 3 : DIFFUSION
    // =========================
    private function diffusionPass(array &$m, int $rows, int $cols): void {
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $prev = ($c === 0) ? 0 : $m[$r][$c - 1];
                $m[$r][$c] = ($m[$r][$c] + $prev) % 256;
            }
        }
    }

    private function diffusionInverse(array &$m, int $rows, int $cols): void {
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $prev = ($c === 0) ? 0 : $m[$r][$c - 1];
                $m[$r][$c] = ($m[$r][$c] - $prev + 256) % 256;
            }
        }
    }

    // =========================
    // PASSAGE 4 : SHIFT
    // =========================
    private function shiftPass(array &$m, int $rows, int $cols): void {
        for ($r = 0; $r < $rows; $r++) {
            $m[$r] = array_merge(
                array_slice($m[$r], 1),
                array_slice($m[$r], 0, 1)
            );
        }
    }

    private function shiftInverse(array &$m, int $rows, int $cols): void {
        for ($r = 0; $r < $rows; $r++) {
            $m[$r] = array_merge(
                array_slice($m[$r], -1),
                array_slice($m[$r], 0, $cols - 1)
            );
        }
    }

    // =========================
    // APPLY
    // =========================
    public function apply(): array {
        $m = $this->matrix;
        $rows = count($m);
        $cols = count($m[0]);

        $this->noisePass($m, $rows, $cols);
        $this->mixPass($m, $rows, $cols);
        $this->diffusionPass($m, $rows, $cols);
        $this->shiftPass($m, $rows, $cols);

        return $this->is1D ? array_map(fn($v) => $v[0], $m) : $m;
    }

    // =========================
    // REVERT
    // =========================
    public function revert(): array {
        $m = $this->matrix;
        $rows = count($m);
        $cols = count($m[0]);

        $this->shiftInverse($m, $rows, $cols);
        $this->diffusionInverse($m, $rows, $cols);

        // inverse mix
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $randOffset = ord(hash('sha256', $this->key . $r . $c, true)[0]);
                $keyOffset  = ord($this->key[($r + $c) % strlen($this->key)]);

                $m[$r][$c] = ($m[$r][$c] - $keyOffset - $randOffset + 512) % 256;
            }
        }

        // inverse noise
        $seed = crc32($this->key . $this->prngState);
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $noise = ($seed + $r * 31 + $c * 17) & 0xFF;
                $m[$r][$c] = ($m[$r][$c] ^ $noise) & 0xFF;
            }
        }

        return $this->is1D ? array_map(fn($v) => $v[0], $m) : $m;
    }
}
?>