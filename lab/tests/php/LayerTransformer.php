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
        $new = $this->matrix;

        $this->prngState = crc32($this->key);

        // =========================
        // PASS 1 — confusion base
        // =========================
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {

                $val = $new[$r][$c];

                $hash = hash('sha256', $this->key . $r . $c, true);
                $rand = ord($hash[0]);
                $key  = ord($this->key[($r + $c) % strlen($this->key)]);

                $new[$r][$c] = ($val + $rand + $key) % 256;
            }
        }

        // =========================
        // PASS 2 — DECUBE INJECTION
        // =========================
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                
                $noise = $this->deCube->lancer(
                    ($r + $c) % $this->deCube->getCouche(),
                    ($r * $c + $c) % $this->deCube->getFace()
                );

                $left = $new[$r][$c - 1] ?? 0;
                $up   = $new[$r - 1][$c] ?? 0;

                $new[$r][$c] = ($new[$r][$c] + $noise + $left + $up) % 256;
            }
        }

        // =========================
        // PASS 3 — diffusion horizontale
        // =========================
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 1; $c < $cols; $c++) {
                $new[$r][$c] = ($new[$r][$c] + $new[$r][$c - 1]) % 256;
            }
        }

        // =========================
        // PASS 4 — diffusion verticale
        // =========================
        for ($c = 0; $c < $cols; $c++) {
            for ($r = 1; $r < $rows; $r++) {
                $new[$r][$c] = ($new[$r][$c] + $new[$r - 1][$c]) % 256;
            }
        }

        // =========================
        // PASS 5 — PRNG noise final
        // =========================
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $new[$r][$c] ^= $this->nextInt(256);
            }
        }

        // =========================
        // SHIFT FINAL
        // =========================
        for ($r = 0; $r < $rows; $r++) {
            $new[$r] = array_merge(
                array_slice($new[$r], 1),
                array_slice($new[$r], 0, 1)
            );
        }

        return $this->is1D
            ? array_map(fn($v) => $v[0], $new)
            : $new;
    }
    
    public function revert(): array {
        $rows = count($this->matrix);
        $cols = count($this->matrix[0]);
        $new = $this->matrix;

        $this->prngState = crc32($this->key);

        // inverse shift
        for ($r = 0; $r < $rows; $r++) {
            $new[$r] = array_merge(
                array_slice($new[$r], -1),
                array_slice($new[$r], 0, $cols - 1)
            );
        }

        // inverse PRNG
        $noise = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $noise[$r][$c] = $this->nextInt(256);
            }
        }

        for ($r = $rows - 1; $r >= 0; $r--) {
            for ($c = $cols - 1; $c >= 0; $c--) {
                $new[$r][$c] ^= $noise[$r][$c];
            }
        }

        // inverse vertical
        for ($c = 0; $c < $cols; $c++) {
            for ($r = $rows - 1; $r >= 1; $r--) {
                $new[$r][$c] =
                    ($new[$r][$c] - $new[$r - 1][$c] + 256) % 256;
            }
        }

        // inverse horizontal
        for ($r = 0; $r < $rows; $r++) {
            for ($c = $cols - 1; $c >= 1; $c--) {
                $new[$r][$c] =
                    ($new[$r][$c] - $new[$r][$c - 1] + 256) % 256;
            }
        }

        // inverse DeCube pass (IMPORTANT)
        for ($r = $rows - 1; $r >= 0; $r--) {
            for ($c = $cols - 1; $c >= 0; $c--) {

                $noise = $this->deCube->lancer(
                    ($r + $c) % $this->deCube->getCouche(),
                    ($r * $c + $c) % $this->deCube->getFace()
                );

                $left = $new[$r][$c - 1] ?? 0;
                $up   = $new[$r - 1][$c] ?? 0;

                $new[$r][$c] =
                    ($new[$r][$c] - $noise - $left - $up + 768) % 256;
            }
        }

        // inverse base confusion
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {

                $hash = hash('sha256', $this->key . $r . $c, true);
                $rand = ord($hash[0]);
                $key  = ord($this->key[($r + $c) % strlen($this->key)]);

                $new[$r][$c] =
                    ($new[$r][$c] - $rand - $key + 512) % 256;
            }
        }

        return $this->is1D
            ? array_map(fn($v) => $v[0], $new)
            : $new;
    }
}
?>