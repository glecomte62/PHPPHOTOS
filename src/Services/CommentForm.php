<?php
declare(strict_types=1);

namespace App\Services;

/** Validation d'un commentaire de visiteur (même protection anti-robots que le formulaire de contact). */
final class CommentForm
{
    public const MAX_NAME = 100;
    public const MAX_MESSAGE = 2000;
    private const MAX_LINKS = 2;

    /**
     * @param array<string, mixed> $input
     * @return array{data: array{name: string, message: string}, errors: array<string, string>, spam: bool}
     */
    public static function validate(array $input, string $key, int $now): array
    {
        $field = static fn (string $k): string => is_string($input[$k] ?? null) ? trim($input[$k]) : '';
        $name = (string) preg_replace('/\s+/u', ' ', $field('name'));
        $message = str_replace("\r\n", "\n", $field('message'));

        $errors = [];
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            $errors['name'] = 'Indiquez votre nom (' . self::MAX_NAME . ' caractères maximum).';
        }
        $length = mb_strlen($message);
        if ($length < 2 || $length > self::MAX_MESSAGE) {
            $errors['message'] = 'Votre commentaire doit contenir entre 2 et ' . self::MAX_MESSAGE . ' caractères.';
        }

        $spam = strlen($key) < 32
            || $field('website') !== ''
            || !ContactForm::tokenIsValid($field('ts'), $key, $now)
            || preg_match_all('#https?://#i', $message) > self::MAX_LINKS;

        return ['data' => ['name' => $name, 'message' => $message], 'errors' => $errors, 'spam' => $spam];
    }
}
