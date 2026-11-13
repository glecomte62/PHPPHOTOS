<?php
/** @var array $page */
use App\Services\PresentationImages;

$picture = static function (?string $key, string $sizes, string $alt, string $class, string $loading = 'lazy'): string {
    if (!$key) {
        return '';
    }
    $set = static fn (string $ext): string => PresentationImages::url($key, 1280, $ext) . ' 1280w, ' . PresentationImages::url($key, 2560, $ext) . ' 2560w';
    return '<picture><source type="image/webp" srcset="' . e($set('webp')) . '" sizes="' . e($sizes) . '">'
        . '<img src="' . e(PresentationImages::url($key, 1280)) . '" srcset="' . e($set('jpg')) . '" sizes="' . e($sizes) . '"'
        . ' alt="' . e($alt) . '" class="' . e($class) . '" loading="' . e($loading) . '" decoding="async"></picture>';
};

$pilot = [];
foreach (preg_split('/\R/', (string) $page['pilot_info']) ?: [] as $line) {
    $parts = array_map('trim', explode(':', $line, 2));
    if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
        $pilot[] = $parts;
    }
}
?>
<?php if ($page['banner_image']): ?>
<section class="banner">
  <?= $picture($page['banner_image'], '100vw', 'Guillaume Lecomte photographiant sur le tarmac d\'un aéroport', 'banner__img', 'eager') ?>
</section>
<?php endif; ?>

<header class="page-header">
  <h1 class="page-title"><?= e($page['title']) ?></h1>
  <?php if ($page['lead']): ?><p class="lead"><?= e($page['lead']) ?></p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<section class="about">
  <?php if ($page['portrait_image']): ?>
  <div class="about__portrait">
    <?= $picture($page['portrait_image'], '(min-width: 760px) 40vw, 100vw', 'Portrait de Guillaume Lecomte, appareil photo en main', 'about__img') ?>
  </div>
  <?php endif; ?>
  <div class="about__body prose">
    <?= $page['body_html'] /* HTML filtré par HtmlSanitizer à l'enregistrement */ ?>
    <?php if ($pilot): ?>
    <aside class="pilot">
      <h2 class="pilot__title">Côté pilote</h2>
      <dl>
        <?php foreach ($pilot as [$label, $value]): ?>
          <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach; ?>
      </dl>
    </aside>
    <?php endif; ?>
    <p><a class="link-arrow" href="/contact">Me contacter</a></p>
  </div>
</section>
