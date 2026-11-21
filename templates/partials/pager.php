<?php /** @var int $page @var int $pages @var callable(int): string $href */ ?>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pages de résultats">
  <?php if ($page > 1): ?><a class="link-arrow pager__prev" href="<?= e($href($page - 1)) ?>" rel="prev">Page précédente</a><?php endif; ?>
  <span class="pager__info"><?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="link-arrow" href="<?= e($href($page + 1)) ?>" rel="next">Page suivante</a><?php endif; ?>
</nav>
<?php endif; ?>
