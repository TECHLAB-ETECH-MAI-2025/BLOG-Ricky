<?php

namespace App\Service;

use Firebase\JWT\JWT;

class JWTService
{
    private string $secret;

    public function __construct(string $mercureJwtSecret)
    {
        $this->secret = $mercureJwtSecret;
    }

    /**
     * Générer un token JWT pour Mercure
     */
    public function generateMercureToken(
        array $subscribe = [],
        array $publish = [],
        ?int $userId = null
    ): string {
        $payload = [
            'mercure' => [
                'subscribe' => $subscribe,
                'publish' => $publish,
            ],
            'iat' => time(),
            'exp' => time() + 3600, // Expire dans 1 heure
        ];

        if ($userId !== null) {
            $payload['sub'] = (string) $userId;
        }

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * Générer un token pour un topic de chat
     */
    public function generateChatToken(int $userId1, int $userId2): string
    {
        $topic = sprintf('chat/%d/%d', min($userId1, $userId2), max($userId1, $userId2));

        return $this->generateMercureToken([$topic], [], $userId1);
    }
}
