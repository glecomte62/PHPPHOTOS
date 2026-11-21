<?php
declare(strict_types=1);

namespace App\Services;

/** Visiteurs anonymes : robots à ignorer dans les statistiques, identifiant de navigateur pour les « j'aime ». */
final class Visitor
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|headless|lighthouse|python|curl|wget|httpclient|java\/|go-http|monitor|scan/i';

    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '' || preg_match(self::BOTS, $userAgent) === 1;
    }

    /**
     * Identifiant d'un navigateur : jeton aléatoire tiré par le navigateur et gardé dans son stockage local,
     * conservé en base sous forme d'empreinte (jamais le jeton lui-même). Null si le jeton est mal formé.
     */
    public static function voterId(mixed $token, string $key): ?string
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return hash_hmac('sha256', $token, $key);
    }
}
