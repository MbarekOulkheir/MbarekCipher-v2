<?php
declare(strict_types=1);

$file = $argv[1] ?? null;
$pageSize = 4096;
$sampleStep = 50; // Affiche une page sur $sampleStep pour gros fichiers

if (!$file || !file_exists($file)) {
    die("Usage: php test_entropy_sample.php <fichier>\n");
}

function shannonEntropy(string $data): float {
    $len = strlen($data);
    if ($len === 0) return 0.0;

    $freq = array_count_values(str_split($data));
    $entropy = 0.0;
    foreach ($freq as $count) {
        $p = $count / $len;
        $entropy -= $p * log($p, 2);
    }
    return $entropy;
}

function asciiHistogram(string $data): array {
    $freq = array_fill(0, 256, 0);
    foreach (str_split($data) as $char) {
        $freq[ord($char)]++;
    }
    return $freq;
}

function topNBytes(array $freq, int $n = 16): array {
    arsort($freq);
    return array_slice($freq, 0, $n, true);
}

// --- Initialisation ---
$fp = fopen($file, 'rb');
$pageIndex = 0;
$entropyValues = [];
$globalFreq = array_fill(0, 256, 0);

while (!feof($fp)) {
    $pageData = fread($fp, $pageSize);
    if ($pageData === '') break;

    $entropy = shannonEntropy($pageData);
    $entropyValues[] = $entropy;

    // Histogramme global
    foreach (asciiHistogram($pageData) as $byte => $count) {
        $globalFreq[$byte] += $count;
    }

    // Affichage échantillonné
    if ($pageIndex % $sampleStep === 0) {
        $bar = str_repeat("█", (int)($entropy * 10)); // graphique simple
        printf("📄 Page #%d — Taille: %d bytes — Entropie: %.5f %s\n",
            $pageIndex + 1, strlen($pageData), $entropy, $bar
        );
    }

    $pageIndex++;
}

fclose($fp);

// --- Stats globales ---
$minEntropy = min($entropyValues);
$maxEntropy = max($entropyValues);
$avgEntropy = array_sum($entropyValues) / count($entropyValues);

echo "\n📊 Entropie moyenne: $avgEntropy bits/byte\n";
echo "📊 Min: $minEntropy — Max: $maxEntropy\n\n";

// --- Histogramme global top 16 ---
$topGlobal = topNBytes($globalFreq, 16);
echo "📈 Histogramme global (top 16 octets) :\n";
$maxCount = max($topGlobal);
foreach ($topGlobal as $byte => $count) {
    $barLength = min(50, (int)($count * 50 / $maxCount));
    printf(" %3d: %s %d\n", $byte, str_repeat("█", $barLength), $count);
}

echo "\n✅ Analyse terminée pour $pageIndex pages.\n";
?>