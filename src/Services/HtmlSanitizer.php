<?php
declare(strict_types=1);

namespace App\Services;

use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

final class HtmlSanitizer
{
    /** Balises autorisées => attributs autorisés */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'a' => ['href'], 'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'blockquote' => [],
    ];

    /** Balises supprimées avec leur contenu */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>',
            LIBXML_NOERROR
        );
        $root = $doc->getElementById('root');
        if ($root === null) {
            return '';
        }
        self::walk($root);
        return trim($root->innerHTML);
    }

    /** Texte brut (paragraphes séparés par une ligne vide) => HTML. Laisse intact un texte qui contient déjà des blocs. */
    public static function paragraphs(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return '';
        }
        if (preg_match('#<(p|ul|ol|h2|h3|blockquote)\b#i', $text)) {
            return $text;
        }
        $parts = preg_split("/\n{2,}/", $text) ?: [];
        return implode("\n", array_map(
            static fn (string $p): string => '<p>' . str_replace("\n", '<br>', trim($p)) . '</p>',
            $parts
        ));
    }

    /** HTML stocké => texte éditable (inverse de paragraphs()). */
    public static function toEditable(string $html): string
    {
        $t = preg_replace('#\s*</p>\s*<p>\s*#i', "\n\n", trim($html)) ?? '';
        $t = preg_replace('#^<p>|</p>$#i', '', $t) ?? '';
        return preg_replace('#<br\s*/?>\n?#i', "\n", $t) ?? '';
    }

    private static function walk(Node $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof Comment) {
                $child->remove();
                continue;
            }
            if (!$child instanceof Element) {
                continue;
            }
            $tag = strtolower($child->localName);
            if (in_array($tag, self::DROP, true)) {
                $child->remove();
                continue;
            }
            self::walk($child);
            if (!array_key_exists($tag, self::ALLOWED)) {
                $child->replaceWith(...iterator_to_array($child->childNodes));
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);
                } elseif ($name === 'href' && !preg_match('#^(https?://|mailto:|/)#i', trim($attr->value))) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && preg_match('#^https?://#i', (string) $child->getAttribute('href'))) {
                $child->setAttribute('rel', 'noopener');
            }
        }
    }
}
