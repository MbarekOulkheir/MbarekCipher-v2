<?php
declare(strict_types=1);

namespace MbarekCipher;

class DNA
{
    // ===================== ENCODAGE ADN =====================
   public static function encodeBlock(string $block, int $fixedDnaLen, bool $isLastBlock = false, int $realBlockLen = 0): string {

    if ($isLastBlock && $realBlockLen > 0) {
        $block = substr($block, 0, $realBlockLen);
    }

    $dna = self::binaryToDNA($block);

    // padding ADN (bases)
    return str_pad($dna, $fixedDnaLen, 'A');
}

    // ===================== DECODAGE ADN =====================
   public static function decodeBlock(string $dnaBlock, int $fixedDnaLen, bool $isLastBlock = false, int $realBlockLen = 0): string {

    $dnaBlock = substr($dnaBlock, 0, $fixedDnaLen);

    $binary = self::DNAToBinary($dnaBlock);

    if ($isLastBlock && $realBlockLen > 0) {
        $binary = substr($binary, 0, $realBlockLen);
    }

    return $binary;
}
    // ===================== BINAIRE → ADN =====================
    public static function binaryToDNA(string $binaryData): string {
        $dna = '';

        for ($i = 0; $i < strlen($binaryData); $i++) {
            $byte = ord($binaryData[$i]);

            for ($shift = 6; $shift >= 0; $shift -= 2) {
                $bits = ($byte >> $shift) & 0b11;

                switch ($bits) {
                    case 0: $dna .= 'A'; break;
                    case 1: $dna .= 'C'; break;
                    case 2: $dna .= 'G'; break;
                    case 3: $dna .= 'T'; break;
                }
            }
        }

        return $dna;
    }

    // ===================== ADN → BINAIRE =====================
    public static function DNAToBinary(string $dna): string {
        $binary = '';
        $len = strlen($dna);

        for ($i = 0; $i < $len; $i += 4) {
            $byte = 0;

            for ($j = 0; $j < 4; $j++) {
                $byte <<= 2;
                $base = $dna[$i + $j] ?? 'A';

                switch ($base) {
                    case 'A': $byte |= 0; break;
                    case 'C': $byte |= 1; break;
                    case 'G': $byte |= 2; break;
                    case 'T': $byte |= 3; break;
                    default:  $byte |= 0; break;
                }
            }

            $binary .= chr($byte);
        }

        return $binary;
    }
}