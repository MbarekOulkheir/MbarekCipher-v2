<?php

// ========================
// ADN Mapping
// ========================
$ADN = ['A','C','G','T'];

function bitsToDNA($bits) {
    $map = ['00'=>'A','01'=>'C','10'=>'G','11'=>'T'];
    $dna = '';
    for ($i = 0; $i < strlen($bits); $i += 2) {
        $dna .= $map[substr($bits, $i, 2)];
    }
    return $dna;
}

function dnaToBits($dna) {
    $map = ['A'=>'00','C'=>'01','G'=>'10','T'=>'11'];
    $bits = '';
    for ($i = 0; $i < strlen($dna); $i++) {
        $bits .= $map[$dna[$i]];
    }
    return $bits;
}

// ========================
// TEXT ↔ BINARY
// ========================
function textToBinary($text) {
    $bin = '';
    for ($i=0; $i<strlen($text); $i++) {
        $bin .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    return $bin;
}

function binaryToText($bin) {
    $text = '';
    for ($i=0; $i<strlen($bin); $i+=8) {
        $byte = substr($bin, $i, 8);
        if (strlen($byte) < 8) break;
        $text .= chr(bindec($byte));
    }
    return $text;
}

// ========================
// CODONS (6 bits)
// ========================
function binaryToCodons($bin) {
    $codons = [];
    for ($i=0; $i<strlen($bin); $i+=6) {
        $chunk = substr($bin, $i, 6);
        if (strlen($chunk) < 6) {
            $chunk = str_pad($chunk, 6, '0');
        }
        $codons[] = bindec($chunk);
    }
    return $codons;
}

function codonsToBinary($codons) {
    $bin = '';
    foreach ($codons as $c) {
        $bin .= str_pad(decbin($c), 6, '0', STR_PAD_LEFT);
    }
    return $bin;
}

// ========================
// XOR CHAIN (stable)
// ========================
function processCodons($codons, $key, $mode='encrypt') {
    $result = [];

    $seed = hexdec(substr(hash('sha256', $key), 0, 8));
    $prev = $seed & 63;

    foreach ($codons as $i => $value) {
        $mask = (1 << (($i % 6)+1)) - 1;

        if ($mode === 'encrypt') {
            $new = ($value ^ $mask ^ $prev) & 63;
            $prev = $new;
        } else {
            $new = ($value ^ $mask ^ $prev) & 63;
            $prev = $value;
        }

        $result[] = $new;
    }

    return $result;
}

// ========================
// PERMUTATION (reversible)
// ========================
function permuteCodons($codons, $key) {
    $seed = hexdec(substr(hash('sha256', $key), 0, 8));
    mt_srand($seed);

    $n = count($codons);
    for ($i=$n-1; $i>0; $i--) {
        $j = mt_rand(0, $i);
        [$codons[$i], $codons[$j]] = [$codons[$j], $codons[$i]];
    }

    return $codons;
}

function inversePermuteCodons($codons, $key) {
    $seed = hexdec(substr(hash('sha256', $key), 0, 8));
    mt_srand($seed);

    $count = count($codons);
    $indices = range(0, $count - 1);
    for ($i = $count - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $tmp = $indices[$i];
        $indices[$i] = $indices[$j];
        $indices[$j] = $tmp;
    }

    $decoded = [];
    foreach ($indices as $original => $new) {
        $decoded[$new] = $codons[$original];
    }

    return $decoded;
}

// ========================
// ENCRYPT
// ========================
function encryptDNA($input, $key) {

    // ✅ Gestion tableau
    if (is_array($input)) {
        return array_map(function($item) use ($key) {
            return encryptDNA($item, $key);
        }, $input);
    }

    // ❌ Sécurité type
    if (!is_string($input)) {
        throw new Exception("encryptDNA attend string ou array");
    }

    // --- traitement normal ---
    $bin = textToBinary($input);
    $codons = binaryToCodons($bin);

    $codons = processCodons($codons, $key, 'encrypt');
    $codons = permuteCodons($codons, $key);

    $bin2 = codonsToBinary($codons);
    $dna = bitsToDNA($bin2);

    return base64_encode($dna);
}   
function decryptDNA($input, $key) {

    // ✅ Gestion tableau
    if (is_array($input)) {
        return array_map(function($item) use ($key) {
            return decryptDNA($item, $key);
        }, $input);
    }

    // ❌ Sécurité type
    if (!is_string($input)) {
        throw new Exception("decryptDNA attend string ou array");
    }
}
?>
