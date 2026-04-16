<?php

function textToBinary(string $text): string {
    $bin = '';
    for ($i = 0; $i < strlen($text); $i++) {
        $bin .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    return $bin;
}

function binaryToText(string $bin): string {
    $text = '';
    for ($i = 0; $i < strlen($bin); $i += 8) {
        $byte = substr($bin, $i, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0'); // padding si nécessaire
        }
        $text .= chr(bindec($byte));
    }
    return $text;
}

function binaryToCodons(string $bin): array {
    $codons = [];
    // On travaille en 3 bits (sous-codons)
    $subCodons = str_split($bin, 3);

    // Ajouter du padding pour avoir des groupes de 8 sous-codons (24 bits)
    while (count($subCodons) % 8 !== 0) {
        $subCodons[] = '000'; // padding arbitraire
    }

    // Regrouper par codon de 8 sous-codons
    for ($i = 0; $i < count($subCodons); $i += 8) {
        $codon = implode('', array_slice($subCodons, $i, 8));
        $codons[] = $codon;
    }

    return $codons;
}

function codonsToBinary(array $codons): string {
    return implode('', $codons); // on recompose le binaire complet
}

// ==== TEST ====
$texte = "A";
echo "Original: $texte\n";

$bin = textToBinary($texte);
$codons = binaryToCodons($bin);

echo "Codons (24 bits chacun):\n";
print_r($codons);

// Recomposition
$reconstructedBin = codonsToBinary($codons);
$decodedText = binaryToText($reconstructedBin);

echo "Reconstructed: $decodedText\n";
?>