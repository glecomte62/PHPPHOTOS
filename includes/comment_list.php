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
<ul class="comment-list">
    <?php foreach ($comments as $c): ?>
    <li class="comment-item">
        <div class="comment-meta">
            <span class="comment-author"><?= htmlspecialchars($c['author_name']) ?></span>
            <?php if (!empty($c['author_city'])): ?>
            <span class="comment-city"><?= htmlspecialchars($c['author_city']) ?></span>
            <?php endif; ?>
            <span class="comment-date"><?= htmlspecialchars(
                (new DateTime($c['created_at']))->format('d/m/Y à H\hi')
            ) ?></span>
        </div>
        <p class="comment-message"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
    </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="comment-empty">Soyez le premier à laisser un commentaire.</p>
<?php endif; ?>
