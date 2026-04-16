<?php
declare(strict_types=1);

namespace MbarekCipher;

class DeCubeV2Hybrid
{
    private string $key;
    private bool $useState = true;
    private array $table = [];
    private string $state = '';

    public function __construct(string $key, int $couche = 8, int $face = 32)
    {
        $this->key = $key;
        $this->couche = $couche;
        $this->face = $face;

        $this->buildTable();
    }

   public function process(int $byte, int $i, bool $forward): int
    {
        $seed = hash('sha256', $this->key . $i, true);
       
        $r = (ord($seed[0]) + ord($seed[1])) % 3;

        if ($forward) {
            $byte = $this->permute($byte, $r);
        } else {
            $byte = $this->inversePermute($byte, $r);
        }

        return $byte;
    }

    private function permute(int $b, int $r): int
    {
        return match ($r) {
            0 => ($b + 31) % 256,
            1 => $b ^ 0x5A,
            2 => (($b << 1) | ($b >> 7)) & 0xFF,
            default => $b
        };
    }

    private function inversePermute(int $b, int $r): int
    {
        return match ($r) {
            0 => ($b - 31 + 256) % 256,
            1 => $b ^ 0x5A,
            2 => (($b >> 1) | (($b & 1) << 7)) & 0xFF,
            default => $b
        };
    }

    public function reset(): void
    {
        $this->state = '';
    }
    public function getCouche(): int
    {
        return 8;
    }

    public function getFace(): int
    {
        return 16;
    }
    public function lancer(int $couche, int $face): int
    {   
        // fallback compatibilité (ancienne API)
        return $this->table[$couche % $this->couche][$face % $this->face];
    }
    public function transformByte(
        int $byte,
        int $blockIndex,
        int $i,
        bool $forward
    ): int {
        return $this->process($byte, $blockIndex, $i, $forward);
    }
    public function buildTable(): void    
    {                                      
        $lenKey = strlen($this->key);

        for ($c = 0; $c < $this->getCouche(); $c++) {
            for ($f = 0; $f < $this->getFace(); $f++) {

                //$seed = hash('sha256', $this->key . "|$c|$f", true); // carractere qui ennui au chifrement et au dechifrement 
                $seed = hash('sha256', $this->key . "\$c\$f", true);
                
                $a = ord($seed[0]);
                $b = ord($seed[1]);
                $c2 = ord($seed[2]);
                $d = ord($seed[3]);

                $x = ($a + ($b ^ $c2)) & 0xFF;
                $y = (($c2 << 1) | ($c2 >> 7)) & 0xFF;
                $this->table[$c][$f] = (($x ^ $y) + $d) & 0xFF;
               
            }
        }
    }
}