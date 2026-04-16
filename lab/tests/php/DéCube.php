<?php
class DeCube {
    private array $cube;   // Cube 3D : [couche][face]
    private array $key;    // Clé segmentée
    private int $couches;
    private int $faces;

    public function __construct(string $keyStr, int $couches = 3, int $faces = 6) {
        $this->couches = $couches;
        $this->faces = $faces;
        $this->key = array_map('ord', str_split($keyStr)); // Convertit la clé en codes ASCII
        $this->initCube();
    }

    private function initCube() {
        $this->cube = [];
        $keyIndex = 0;
        for ($c = 0; $c < $this->couches; $c++) {
            $this->cube[$c] = [];
            for ($f = 0; $f < $this->faces; $f++) {
                // Chaque face = (valeur de face initiale XOR segment de clé) % 256
                $faceVal = ($f + 1) ^ ($this->key[$keyIndex % count($this->key)]);
                $this->cube[$c][$f] = $faceVal % 256;
                $keyIndex++;
            }
        }
    }

    // "Lancer du dé" déterministe selon un index et la clé
    public function lancer(int $couche, int $indice): int {
        $couche = $couche % $this->couches;
        $indice = $indice % $this->faces;
        return $this->cube[$couche][$indice];
    }

    // Affiche le cube pour visualisation
    public function printCube() {
        foreach ($this->cube as $c => $faces) {
            echo "Couche " . ($c + 1) . ": " . implode(", ", $faces) . "\n";
        }
    }
}

// Exemple d'utilisation
$key = "MBarek123";  // clé de chiffrement
$deCube = new DeCube($key);
$deCube->printCube();

// Lancer le dé : couche 2, face 4
$val = $deCube->lancer(1, 3);
echo "Valeur générée par le dé : $val\n";
?>