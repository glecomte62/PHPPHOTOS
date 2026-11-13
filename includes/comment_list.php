<?php
require_once __DIR__ . '/../db.php';

$pdo  = get_pdo();
$stmt = $pdo->prepare(
    'SELECT author_name, author_city, message, created_at
     FROM comments
     WHERE context_type = ? AND context_id = ?
     ORDER BY created_at ASC'
);
$stmt->execute([$commentContextType, $commentContextId]);
$comments = $stmt->fetchAll();
?>
<?php if (!empty($comments)): ?>
<ul class="cf-list">
    <?php foreach ($comments as $c): ?>
    <li class="cf-entry">
        <div class="cf-entry-meta">
            <span class="cf-entry-name"><?= htmlspecialchars($c['author_name']) ?></span>
            <?php if (!empty($c['author_city'])): ?>
            <span class="cf-entry-city"><?= htmlspecialchars($c['author_city']) ?></span>
            <?php endif; ?>
            <span class="cf-entry-date"><?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
        </div>
        <p class="cf-entry-message"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
    </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="cf-empty">Soyez le premier à laisser un commentaire.</p>
<?php endif; ?>
