<?php
namespace MbarekCipher;

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/../src/classes/DeCubeEnhanced.php';

class Cipher3DFull {
    private string $key;
    private int $blockSize;
    private DeCubeEnhanced $deCube;

    public function __construct(string $key, int $blockSize = 64) {
        $this->key = $key;
        $this->blockSize = $blockSize;
        $this->deCube = new DeCubeEnhanced($key);
    }

    public function encrypt(string $data): string {
        $encrypted = '';
        $blocks = str_split($data, $this->blockSize);

        foreach ($blocks as $bIndex => $block) {
            $prev = 0; // effet cumulatif par bloc
            for ($i = 0; $i < strlen($block); $i++) {
                $val = ord($block[$i]);
                $mixed = $val;

                // Mélange avec plusieurs lancers du DéCube
                for ($round = 0; $round < 3; $round++) {
                    $couche = ($bIndex + $round + $i) % 3;
                    $face = ($i + $round) % 6;
                    $deVal = $this->deCube->lancer($couche, $face);
                    $mixed ^= $deVal;      // XOR
                    $mixed = ($mixed + $prev) % 256; // cumulatif
                }

                $encrypted .= chr($mixed);
                $prev = $mixed; // influence sur l'octet suivant
            }
        }

        return $encrypted;
    }

    public function decrypt(string $data): string {
        // Déchiffrement identique car symétrie XOR + cumulatif inversé
        $decrypted = '';
        $blocks = str_split($data, $this->blockSize);

        foreach ($blocks as $bIndex => $block) {
            $prev = 0;
            for ($i = 0; $i < strlen($block); $i++) {
                $mixed = ord($block[$i]);
                $orig = $mixed;
                // Inversion cumulatif et XOR dans l’ordre inverse
                for ($round = 2; $round >= 0; $round--) {
                    $couche = ($bIndex + $round + $i) % 3;
                    $face = ($i + $round) % 6;
                    $deVal = $this->deCube->lancer($couche, $face);
                    $orig = ($orig - $prev + 256) % 256;
                    $orig ^= $deVal;
                }

                $decrypted .= chr($orig);
                $prev = $mixed;
            }
        }

        return $decrypted;
    }
}
?>