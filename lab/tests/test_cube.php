<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/classes/Cube.php';

use MbarekCipher\Cube;

// Création d’un cube 3x3x3 pour tester
$cubeData = [
    [ // z=0
        [0,1,2],
        [3,4,5],
        [6,7,8]
    ],
    [ // z=1
        [9,10,11],
        [12,13,14],
        [15,16,17]
    ],
    [ // z=2
        [18,19,20],
        [21,22,23],
        [24,25,26]
    ]
];

// Instanciation
$cube = new Cube($cubeData);

echo "Cube initial:\n";
$cube->printCube();

// Permutations de test
echo "Après permutation U↔D:\n";
$cube->permuteFaces('U','D');
$cube->printCube();

echo "Après permutation F↔B:\n";
$cube->permuteFaces('F','B');
$cube->printCube();

echo "Après permutation L↔R:\n";
$cube->permuteFaces('L','R');
$cube->printCube();

// Undo dans l’ordre inverse
echo "Après undo permutation L↔R:\n";
$cube->undoLastPermutation();
$cube->printCube();

echo "Après undo permutation F↔B:\n";
$cube->undoLastPermutation();
$cube->printCube();

echo "Après undo permutation U↔D:\n";
$cube->undoLastPermutation();
$cube->printCube();

// Vérification finale
echo "Vérification finale (doit être identique au cube initial):\n";
$cube->printCube();
?>