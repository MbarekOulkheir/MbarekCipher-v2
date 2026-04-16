<?php
declare(strict_types=1);

namespace MbarekCipher;

class Header {
    private string $iv;
    private array $meta = [];
    private int $flags = 0;

    public function __construct(string $iv, array $meta = [], int $flags = 0) {
        $this->iv = $iv;
        $this->meta = $meta;
        $this->flags = $flags;
    }

    // Retourne le header sous forme de tableau pour json_encode
    public function toArray(): array {
        return [
            'magic' => 'MBK',
            'version' => 2,
            'algo' => 'MBC-2D',
            'iv' => $this->iv,
            'flags' => $this->flags,
            'meta' => $this->meta,
            'checksum_meta' => $this->computeMetaChecksum()
        ];
    }

    // Encode le header en JSON + padding dynamique
    public function encode(): string {
        $headerJson = json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
        return $headerJson; // 🔥 PAS de padding
    }
    // Décode un header JSON (avec padding éventuel)
    public static function decode(string $headerPadded): array {
        $headerJson = rtrim($headerPadded, "\0"); // supprime le padding
        $data = json_decode($headerJson, true);

        if ($data === null) {
            throw new \Exception("Header invalide : JSON corrompu !");
        }

        // Vérifie checksum meta si présent
        if (isset($data['meta']) && isset($data['checksum_meta'])) {
            $computed = hash('sha256', json_encode($data['meta'], JSON_UNESCAPED_SLASHES));
            if ($computed !== $data['checksum_meta']) {
                throw new \Exception("Header corrompu (checksum meta mismatch) !");
            }
        }

        return $data;
    }

    // Calcule le checksum de la meta
    private function computeMetaChecksum(): string {
        if (empty($this->meta)) return '';
        return hash('sha256', json_encode($this->meta, JSON_UNESCAPED_SLASHES));
    }
}