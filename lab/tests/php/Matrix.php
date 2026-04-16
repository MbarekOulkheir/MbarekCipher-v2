<?php
declare(strict_types=1);

namespace MbarekCipher;

class Matrix {
    public array $data = [];
    public int $rows = 0;
    public int $cols = 0;
    public int $depth = 0; // pour 3D

    public function __construct(array $data = [], int $rows = 0, int $cols = 0, int $depth = 0) {
        $this->data = $data;
        $this->rows = $rows;
        $this->cols = $cols;
        $this->depth = $depth;
    }

    // -------------------------------------------------
    // Aplatir la matrice en 1D
    public function flatten(): array {
        $flat = [];
        array_walk_recursive($this->data, function($v) use (&$flat) {
            $flat[] = $v;
        });
        return $flat;
    }

    // -------------------------------------------------
    // Reconstituer la matrice depuis un tableau 1D
    public function unflatten(array $flat, int $rows = 0, int $cols = 0, int $depth = 0): void {
        $this->rows = $rows;
        $this->cols = $cols;
        $this->depth = $depth;

        if ($rows > 0 && $cols > 0 && $depth > 0) {
            // matrice 3D : profondeur x lignes x colonnes
            $this->data = [];
            $idx = 0;
            for ($d = 0; $d < $depth; $d++) {
                $layer = [];
                for ($r = 0; $r < $rows; $r++) {
                    $layer[$r] = array_slice($flat, $idx, $cols);
                    $idx += $cols;
                }
                $this->data[$d] = $layer;
            }
        } elseif ($rows > 0 && $cols > 0) {
            // matrice 2D : lignes x colonnes
            $this->data = [];
            for ($r = 0; $r < $rows; $r++) {
                $this->data[$r] = array_slice($flat, $r * $cols, $cols);
            }
        } else {
            // 1D simple
            $this->data = $flat;
        }
    }

    // -------------------------------------------------
    // Pour modifier la matrice complète
    public function setData(array $data): void {
        $this->data = $data;
    }

    // -------------------------------------------------
    // Pour récupérer la matrice complète
    public function getData(): array {
        return $this->data;
    }
   
  function autoDimensions(int $len): array {
    // chercher depth proche de cube root et qui divise len
    for ($d = (int) floor(pow($len, 1/3)); $d > 0; $d--) {
        if ($len % $d === 0) {
            $depth = $d;
            break;
        }
    }

    $rest = $len / $depth;

    // chercher rows proche de sqrt(rest) qui divise rest
    for ($r = (int) floor(sqrt($rest)); $r > 0; $r--) {
        if ($rest % $r === 0) {
            $rows = $r;
            break;
        }
    }

    $cols = $rest / $rows;

    return [$rows, $cols, $depth];
    }
}
?>