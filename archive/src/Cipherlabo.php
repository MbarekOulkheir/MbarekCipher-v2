<?php
declare(strict_types=1);
namespace MbarekCipher;
require_once __DIR__ . '/../src/Cipher.php';

class CipherLab {

    private Cipher $cipher;

    public function __construct(Cipher $cipher) {
        $this->cipher = $cipher;
    }

    // =========================
    // TEST 1 : CIPHER ONLY
    // =========================
    public function testCipherOnly(string $data): void {
        echo "\n=== TEST CIPHER ONLY ===\n";

        $enc = $this->cipher->encrypt3D($data, 0, str_repeat('A', strlen($data)));
        $dec = $this->cipher->decrypt3D($enc, 0, str_repeat('A', strlen($data)));

        $this->compare($data, $dec);
    }

    // =========================
    // TEST 2 : BOOST ONLY
    // =========================
    public function testBoostOnly(string $data): void {
        echo "\n=== TEST BOOST ONLY ===\n";

        $enc = $this->cipher->smallFileBoostEncrypt($data, 'iv', 0);
        $dec = $this->cipher->smallFileBoostDecrypt($enc, 'iv', 0);

        $this->compare($data, $dec);
    }

    // =========================
    // TEST 3 : FULL PIPELINE
    // =========================
    public function testFull(string $data): void {
        echo "\n=== TEST FULL PIPELINE ===\n";

        $enc = $this->cipher->smallFileBoostEncrypt($data, 'iv', 0);
        $enc = $this->cipher->encrypt3D($enc, 0, str_repeat('A', strlen($enc)));

        $dec = $this->cipher->decrypt3D($enc, 0, str_repeat('A', strlen($enc)));
        $dec = $this->cipher->smallFileBoostDecrypt($dec, 'iv', 0);

        $this->compare($data, $dec);
    }

    // =========================
    // COMPARISON TOOL
    // =========================
    private function compare(string $original, string $result): void {
        if ($original === $result) {
            echo "✅ OK - match exact\n";
        } else {
            echo "❌ KO - mismatch\n";
            echo "Original: " . bin2hex($original) . "\n";
            echo "Result  : " . bin2hex($result) . "\n";
        }
    }
}