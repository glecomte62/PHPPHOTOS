<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Filtres de la page de recherche (appareil, objectif, focale, ouverture, ISO, année, thème),
 * lus dans l'URL. Seules les valeurs connues sont retenues : le reste est ignoré sans erreur.
 */
final class PhotoFilter
{
    /** Tranches : valeur > min (si min) et <= max (si max). */
    public const FOCALS = [
        'grand-angle' => ['label' => 'Grand-angle (≤ 24 mm)', 'min' => null, 'max' => 24],
        'standard' => ['label' => 'Standard (25–70 mm)', 'min' => 24, 'max' => 70],
        'tele' => ['label' => 'Télé (71–200 mm)', 'min' => 70, 'max' => 200],
        'long-tele' => ['label' => 'Long télé (> 200 mm)', 'min' => 200, 'max' => null],
    ];

    public const APERTURES = [
        'grande' => ['label' => 'Grande (≤ f/2.8)', 'min' => null, 'max' => 2.8],
        'moyenne' => ['label' => 'Moyenne (f/3.2–5.6)', 'min' => 2.8, 'max' => 5.6],
        'fermee' => ['label' => 'Fermée (≥ f/6.3)', 'min' => 5.6, 'max' => null],
    ];

    public const ISOS = [
        'basse' => ['label' => 'Basse (≤ 200)', 'min' => null, 'max' => 200],
        'moyenne' => ['label' => 'Moyenne (250–800)', 'min' => 200, 'max' => 800],
        'haute' => ['label' => 'Haute (1000–3200)', 'min' => 800, 'max' => 3200],
        'tres-haute' => ['label' => 'Très haute (> 3200)', 'min' => 3200, 'max' => null],
    ];

    /** Paramètre d'URL => colonne comparée par égalité. */
    private const EXACT = [
        'appareil' => ['allowed' => 'camera', 'sql' => 'p.camera = ?'],
        'objectif' => ['allowed' => 'lens', 'sql' => 'p.lens = ?'],
        'annee' => ['allowed' => 'year', 'sql' => 'YEAR(p.taken_at) = ?'],
        'theme' => ['allowed' => 'theme', 'sql' => 't.slug = ?'],
    ];

    /** Paramètre d'URL => [tranches, expression SQL numérique]. */
    private const RANGES = [
        'focale' => [self::FOCALS, "CAST(SUBSTRING_INDEX(p.focal_length, ' ', 1) AS DECIMAL(7,1))"],
        'ouverture' => [self::APERTURES, 'CAST(SUBSTRING(p.aperture, 3) AS DECIMAL(5,2))'],
        'iso' => [self::ISOS, 'p.iso'],
    ];

    /** Ordre des paramètres dans l'URL et le formulaire. */
    private const ORDER = ['appareil', 'objectif', 'focale', 'ouverture', 'iso', 'annee', 'theme'];

    /** @param array<string, string> $values */
    private function __construct(private array $values)
    {
    }

    /**
     * @param array<string, mixed> $input $_GET
     * @param array{camera?: list<string>, lens?: list<string>, year?: list<int>, theme?: list<string>} $allowed valeurs présentes en base
     */
    public static function fromInput(array $input, array $allowed): self
    {
        $values = [];
        foreach (self::ORDER as $key) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (isset(self::EXACT[$key])) {
                $known = array_map('strval', $allowed[self::EXACT[$key]['allowed']] ?? []);
                if (in_array($value, $known, true)) {
                    $values[$key] = $value;
                }
            } elseif (isset(self::RANGES[$key][0][$value])) {
                $values[$key] = $value;
            }
        }
        return new self($values);
    }

    /** @return array<string, string> filtres actifs, tels qu'ils figurent dans l'URL */
    public function values(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /** @return array{0: string, 1: list<mixed>} conditions reliées par AND (chaîne vide si aucun filtre) et leurs paramètres */
    public function sql(): array
    {
        $where = [];
        $params = [];
        foreach ($this->values as $key => $value) {
            if (isset(self::EXACT[$key])) {
                $where[] = self::EXACT[$key]['sql'];
                $params[] = $key === 'annee' ? (int) $value : $value;
                continue;
            }
            [$ranges, $expr] = self::RANGES[$key];
            $range = $ranges[$value];
            $parts = [];
            if ($range['min'] !== null) {
                $parts[] = "$expr > " . $range['min'];
            }
            if ($range['max'] !== null) {
                $parts[] = "$expr <= " . $range['max'];
            }
            $where[] = '(' . implode(' AND ', $parts) . ')';
        }
        return [implode(' AND ', $where), $params];
    }

    /** Lien vers la recherche avec ces filtres (moins un, pour l'enlever), le texte et la page. */
    public function url(string $query, int $page = 1, ?string $without = null): string
    {
        $params = $query !== '' ? ['q' => $query] : [];
        foreach ($this->values as $key => $value) {
            if ($key !== $without) {
                $params[$key] = $value;
            }
        }
        if ($page > 1) {
            $params['page'] = $page;
        }
        return '/recherche' . ($params ? '?' . http_build_query($params) : '');
    }

    /** Libellé lisible d'un filtre actif (« Nikon D850 », « Ouverture grande (≤ f/2.8) »…). */
    public static function label(string $key, string $value): string
    {
        return match ($key) {
            'appareil' => self::cameraLabel($value),
            'objectif' => self::lensLabel($value),
            'focale' => self::FOCALS[$value]['label'] ?? $value,
            'ouverture' => 'Ouverture ' . mb_strtolower(self::APERTURES[$value]['label'] ?? $value),
            'iso' => 'ISO ' . mb_strtolower(self::ISOS[$value]['label'] ?? $value),
            default => $value,
        };
    }

    /** « NIKON D850 » devient « Nikon D850 » ; les autres marques restent telles quelles. */
    public static function cameraLabel(string $camera): string
    {
        return preg_replace('/^NIKON\b/', 'Nikon', $camera) ?? $camera;
    }

    /** « 24.0-120.0 mm f/4.0 » devient « 24-120 mm f/4 ». */
    public static function lensLabel(string $lens): string
    {
        return preg_replace('/(\d+)\.0(?!\d)/', '$1', $lens) ?? $lens;
    }
}
