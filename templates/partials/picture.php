<?php
/**
 * @var string $key
 * @var ?string $alt
 * @var ?string $sizes
 * @var ?string $class
 * @var ?string $loading
 * @var int|string|null $width
 * @var int|string|null $height
 */
$sizes ??= '100vw';
$srcset = static fn (string $ext): string => implode(', ', array_map(
    static fn (int $w): string => media_url($key, $w, $ext) . ' ' . $w . 'w',
    [480, 1280, 2560]
));
?>
<picture>
  <source type="image/webp" srcset="<?= e($srcset('webp')) ?>" sizes="<?= e($sizes) ?>">
  <img src="<?= e(media_url($key, 1280, 'jpg')) ?>" srcset="<?= e($srcset('jpg')) ?>" sizes="<?= e($sizes) ?>"
       alt="<?= e($alt ?? '') ?>"<?php if (!empty($width) && !empty($height)): ?> width="<?= (int) $width ?>" height="<?= (int) $height ?>"<?php endif; ?>
       loading="<?= e($loading ?? 'lazy') ?>" decoding="async" class="<?= e($class ?? '') ?>">
</picture>
