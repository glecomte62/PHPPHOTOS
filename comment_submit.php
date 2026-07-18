<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$returnUrl = $_POST['return_url'] ?? 'index.php';
if (!preg_match('#^/(?!/)#', $returnUrl)) {
    $returnUrl = 'index.php';
}

// Honeypot
if (!empty($_POST['website'])) {
    header('Location: ' . $returnUrl . '#comments');
    exit;
}

$contextType = $_POST['context_type'] ?? '';
$contextId   = $_POST['context_id']   ?? '';
$authorName  = trim($_POST['author_name'] ?? '');
$authorCity  = trim($_POST['author_city'] ?? '');
$message     = trim($_POST['message']     ?? '');

if (!in_array($contextType, ['album', 'photo', 'guestbook'], true)) {
    header('Location: ' . $returnUrl . '?error=1#comments');
    exit;
}

if ($contextType !== 'guestbook') {
    $contextId = preg_replace('/[^\d]/', '', $contextId);
} else {
    $contextId = '';
}

$errorUrl = preg_replace('/\?.*/', '', $returnUrl) . '?error=1#comments';
if (empty($authorName) || mb_strlen($authorName) > 100) {
    header('Location: ' . $errorUrl);
    exit;
}
if (empty($message) || mb_strlen($message) > 2000) {
    header('Location: ' . $errorUrl);
    exit;
}
if (mb_strlen($authorCity) > 100) {
    header('Location: ' . $errorUrl);
    exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO comments (context_type, context_id, author_name, author_city, message)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    $contextType,
    $contextId,
    $authorName,
    $authorCity ?: null,
    $message,
]);

$contextLabel = match($contextType) {
    'album'     => 'Album #' . $contextId,
    'photo'     => 'Photo #' . $contextId,
    'guestbook' => "Livre d'or",
};
$emailBody  = "Nouveau commentaire sur guillaumelecomte.net\n\n";
$emailBody .= "Contexte : {$contextLabel}\n";
$emailBody .= "Nom : {$authorName}\n";
if ($authorCity) $emailBody .= "Ville : {$authorCity}\n";
$emailBody .= "Date : " . date('d/m/Y à H:i') . "\n\n";
$emailBody .= "Message :\n{$message}\n";

try {
    $mailer = new Mailer(MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS);
    $mailer->send(NOTIFY_EMAIL, '[guillaumelecomte.net] Nouveau commentaire — ' . $contextLabel, $emailBody);
} catch (Throwable) {
    // échec email ne bloque pas la soumission
}

$separator = str_contains($returnUrl, '?') ? '&' : '?';
header('Location: ' . $returnUrl . $separator . 'success=1#comments');
exit;
