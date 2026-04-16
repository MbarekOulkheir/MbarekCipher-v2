<?php

require_once __DIR__ . '/../src/classes/LayerTransformer.php';
use MbarekCipher\LayerTransformer;
$data = range(0, 63);

$layer = new LayerTransformer($data, "key");
$enc = $layer->apply();

$dec = (new LayerTransformer($enc, "key"))->revert();

var_dump($data === $dec);
?>