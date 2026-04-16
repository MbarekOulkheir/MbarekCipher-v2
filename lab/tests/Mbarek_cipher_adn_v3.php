<?php

$ADN = ['A','C','G','T'];

// --- Convertit texte en binaire ---
function textToBinary(string $text): string {
    $bin = '';
    for ($i=0; $i<strlen($text); $i++) {
        $bin .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    return $bin;
}

// --- Convertit binaire en texte ---
function binaryToText(string $binary, int $originalBytes): string {
    $binary = substr($binary, 0, $originalBytes * 8);
    $text = '';
    for ($i=0; $i<strlen($binary); $i+=8) {
        $text .= chr(bindec(substr($binary, $i, 8)));
    }
    return $text;
}

// --- Découpe binaire en codons 24 bits ---
function binaryToCodons24(string $binary): array {
    $codons = [];
    for ($i=0; $i<strlen($binary); $i+=24) {
        $chunk = substr($binary, $i, 24);
        if(strlen($chunk) < 24) $chunk = str_pad($chunk, 24, '0', STR_PAD_RIGHT);
        $codons[] = $chunk;
    }
    return $codons;
}

// --- Convertit codons 24 bits en binaire ---
function codons24ToBinary(array $codons, int $originalBits): string {
    $bin = implode('', $codons);
    return substr($bin, 0, $originalBits);
}

// --- Convertit codon 24 bits en ADN (8x3 bits) ---
function codonToDNA24(string $codon): string {
    global $ADN;
    $dna = '';
    for ($i=0; $i<strlen($codon); $i+=2) {
        $bits = substr($codon, $i, 2);
        $dna .= $ADN[bindec($bits)];
    }
    return $dna;
}

// --- Convertit ADN en codon 24 bits ---
function dnaToCodon24(string $dna): string {
    $map = ['A'=>'00','C'=>'01','G'=>'10','T'=>'11'];
    $bin = '';
    for ($i=0; $i<strlen($dna); $i++) {
        $bin .= $map[$dna[$i]] ?? '00';
    }
    return $bin;
}

// --- Encrypt un texte ---
function encryptDNA(string $text): string {
    $binary = textToBinary($text);
    $lengthBits = strlen($binary);
    $codons = binaryToCodons24($binary);

    $dna = '';
    foreach ($codons as $c) $dna .= codonToDNA24($c);

    // Retour Base64 + longueur pour décryptage
    return base64_encode($dna) . ':' . $lengthBits;
}

// --- Decrypt un texte ---
function decryptDNA(string $b64WithLen): string {
    list($b64, $lengthBits) = explode(':', $b64WithLen);
    $lengthBits = intval($lengthBits);

    $dna = base64_decode($b64);

    $codons = [];
    for ($i=0; $i<strlen($dna); $i+=12) { // 24 bits / 2bits = 12 bases
        $codons[] = dnaToCodon24(substr($dna, $i, 12));
    }

    $binary = codons24ToBinary($codons, $lengthBits);

    return binaryToText($binary, ceil($lengthBits/8));
}
?>