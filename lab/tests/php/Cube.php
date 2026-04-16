<?php
declare(strict_types=1);

namespace MbarekCipher;

class Cube {
    public array $data;       // Cube complet z,y,x
    public array $history;    // Historique des permutations

    public function __construct(array $cube) {
        $this->data = $cube;
        $this->history = [];
    }

    // Extraire les 6 faces visibles (U,D,F,B,L,R)
    public function extractFaces(): array {
        $faces = [];
        $Z = count($this->data);
        $Y = count($this->data[0]);
        $X = count($this->data[0][0]);

        // Up et Down
        $faces['U'] = $this->data[$Z-1];
        $faces['D'] = $this->data[0];

        // Front (x max) et Back (x min)
        $faces['F'] = [];
        $faces['B'] = [];
        for ($z=0; $z<$Z; $z++) {
            $faces['F'][] = array_column($this->data[$z], $X-1);
            $faces['B'][] = array_column($this->data[$z], 0);
        }

        // Left (y min) et Right (y max)
        $faces['L'] = [];
        $faces['R'] = [];
        for ($z=0; $z<$Z; $z++) {
            $faces['L'][] = $this->data[$z][0];
            $faces['R'][] = $this->data[$z][$Y-1];
        }

        return $faces;
    }

    // Appliquer une permutation entre deux faces
    public function permuteFaces(string $f1, string $f2) {
        $faces = $this->extractFaces();

        // Sauvegarder l'état avant permutation
        $this->history[] = [$f1 => $faces[$f1], $f2 => $faces[$f2]];

        // Échanger les faces
        [$faces[$f1], $faces[$f2]] = [$faces[$f2], $faces[$f1]];

        // Réinjecter dans $data
        $this->updateDataFromFaces($faces);
    }

    // Undo dernière permutation
    public function undoLastPermutation() {
        if (empty($this->history)) return;
        $last = array_pop($this->history);
        $faces = $this->extractFaces();

        foreach ($last as $face => $values) {
            $faces[$face] = $values;
        }

        $this->updateDataFromFaces($faces);
    }

    // Réinjecter toutes les faces dans le cube complet
    private function updateDataFromFaces(array $faces) {
        $Z = count($this->data);
        $Y = count($this->data[0]);
        $X = count($this->data[0][0]);

        // Up et Down
        $this->data[0] = $faces['D'];
        $this->data[$Z-1] = $faces['U'];

        // Front et Back
        for ($z=0; $z<$Z; $z++) {
            for ($y=0; $y<$Y; $y++) {
                $this->data[$z][$y][$X-1] = $faces['F'][$z][$y];
                $this->data[$z][$y][0] = $faces['B'][$z][$y];
            }
        }

        // Left et Right
        for ($z=0; $z<$Z; $z++) {
            for ($x=0; $x<$X; $x++) {
                $this->data[$z][0][$x] = $faces['L'][$z][$x];
                $this->data[$z][$Y-1][$x] = $faces['R'][$z][$x];
            }
        }
    }

    // Afficher le cube
    public function printCube() {
        foreach ($this->data as $z => $layer) {
            echo "Layer z=$z:\n";
            foreach ($layer as $row) {
                echo "  ".implode(" ", $row)."\n";
            }
        }
        echo "\n";
    }
}
?>