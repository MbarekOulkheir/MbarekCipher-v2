<?php
declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php {$argv[0]} <fichier.mbk>\n";
    exit(1);
}

$filePath = $argv[1];
$pageSize = 4096; // taille d'une page en bytes

if (!file_exists($filePath)) {
    echo "Fichier introuvable : $filePath\n";
    exit(1);
}

// Lecture du fichier
$handle = fopen($filePath, 'rb');
if (!$handle) {
    echo "Impossible d'ouvrir le fichier.\n";
    exit(1);
}

$totalBytes = filesize($filePath);
$totalPages = (int) ceil($totalBytes / $pageSize);

$entropies = [];
$globalHistogram = array_fill(0, 256, 0);

for ($page = 0; $page < $totalPages; $page++) {
    $data = fread($handle, $pageSize);
    $length = strlen($data);

    if ($length === 0) break;

    // Calcul de l'entropie
    $counts = array_fill(0, 256, 0);
    for ($i = 0; $i < $length; $i++) {
        $byte = ord($data[$i]);
        $counts[$byte]++;
        $globalHistogram[$byte]++;
    }

    $entropy = 0.0;
    for ($b = 0; $b < 256; $b++) {
        if ($counts[$b] > 0) {
            $p = $counts[$b] / $length;
            $entropy -= $p * log($p, 2);
        }
    }
    $entropies[] = $entropy;

    // Affichage page par page
    printf("📄 Page #%d — Taille: %d bytes — Entropie: %.5f %s\n",
        $page + 1,
        $length,
        $entropy,
        str_repeat("█", (int)($entropy * 10))
    );
}

fclose($handle);

// Statistiques globales
$minEntropy = min($entropies);
$maxEntropy = max($entropies);
$avgEntropy = array_sum($entropies) / count($entropies);

echo "\n📊 Entropie moyenne: $avgEntropy bits/byte\n";
echo "📊 Min: $minEntropy — Max: $maxEntropy\n";

// Histogramme top 16 octets
arsort($globalHistogram);
$top = array_slice($globalHistogram, 0, 16, true);
echo "\n📈 Histogramme global (top 16 octets) :\n";
foreach ($top as $byte => $count) {
    printf(" %d: %s %d\n", $byte, str_repeat("█", (int)($count / max($top) * 50)), $count);
}

echo "\n✅ Analyse terminée pour $totalPages pages.\n";