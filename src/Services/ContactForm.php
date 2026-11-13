<?php
declare(strict_types=1);

namespace App\Services;

final class ContactForm
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 86400;

    /** Jeton signé contenant l'heure d'affichage du formulaire. */
    public static function token(string $key, int $now): string
    {
        return $now . '.' . hash_hmac('sha256', (string) $now, $key);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array{name: string, email: string, message: string}, errors: array<string, string>, spam: bool}
     */
    public static function validate(array $input, string $key, int $now): array
    {
        $field = static fn (string $k): string => is_string($input[$k] ?? null) ? trim($input[$k]) : '';
        $name = $field('name');
        $email = $field('email');
        $message = $field('message');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Indiquez votre nom (100 caractères maximum).';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        $length = mb_strlen($message);
        if ($length < 20 || $length > 5000) {
            $errors['message'] = 'Votre message doit contenir entre 20 et 5000 caractères.';
        }

        $keyOk = strlen($key) >= 32;
        if (!$keyOk) {
            error_log('APP_KEY manquante ou trop courte');
        }
        $spam = !$keyOk
            || $field('website') !== ''
            || !self::tokenIsValid($field('ts'), $key, $now);

        return ['data' => ['name' => $name, 'email' => $email, 'message' => $message], 'errors' => $errors, 'spam' => $spam];
    }

    private static function tokenIsValid(string $token, string $key, int $now): bool
    {
        if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $m[1], $key), $m[2])) {
            return false;
        }
        $age = $now - (int) $m[1];
        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
