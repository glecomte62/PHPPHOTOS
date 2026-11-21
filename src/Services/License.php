<?php
declare(strict_types=1);

namespace App\Services;

/** Licence appliquée aux photos du site (réglée dans Contenus). */
final class License
{
    public const AUTHOR = 'Guillaume Lecomte';

    /** code => [libellé complet, libellé court, chemin creativecommons.org] */
    public const ALL = [
        'arr' => ['Tous droits réservés', 'Tous droits réservés', null],
        'cc-by' => ['Creative Commons Attribution 4.0', 'CC BY 4.0', 'by'],
        'cc-by-sa' => ['Creative Commons Attribution, partage dans les mêmes conditions 4.0', 'CC BY-SA 4.0', 'by-sa'],
        'cc-by-nd' => ['Creative Commons Attribution, pas de modification 4.0', 'CC BY-ND 4.0', 'by-nd'],
        'cc-by-nc' => ['Creative Commons Attribution, pas d\'utilisation commerciale 4.0', 'CC BY-NC 4.0', 'by-nc'],
        'cc-by-nc-sa' => ['Creative Commons Attribution, pas d\'utilisation commerciale, partage dans les mêmes conditions 4.0', 'CC BY-NC-SA 4.0', 'by-nc-sa'],
        'cc-by-nc-nd' => ['Creative Commons Attribution, pas d\'utilisation commerciale, pas de modification 4.0', 'CC BY-NC-ND 4.0', 'by-nc-nd'],
    ];

    /** @return array{code: string, label: string, short: string, url: ?string, reuse: bool} */
    public static function get(?string $code): array
    {
        $code = isset(self::ALL[(string) $code]) ? (string) $code : 'arr';
        [$label, $short, $path] = self::ALL[$code];
        return [
            'code' => $code,
            'label' => $label,
            'short' => $short,
            'url' => $path !== null ? 'https://creativecommons.org/licenses/' . $path . '/4.0/deed.fr' : null,
            'reuse' => $path !== null,
        ];
    }

    /**
     * Mention affichée sous une photo : « © 2016 Guillaume Lecomte · Tous droits réservés ».
     *
     * @param array{short: string} $license
     */
    public static function notice(array $license, ?string $takenAt): string
    {
        $year = $takenAt !== null && ($t = strtotime($takenAt)) !== false ? date('Y', $t) . ' ' : '';
        return '© ' . $year . self::AUTHOR . ' · ' . $license['short'];
    }
}
