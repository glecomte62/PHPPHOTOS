<?php
$isGuestbook = ($commentContextType === 'guestbook');
$btnLabel    = $isGuestbook ? "Signer le livre d'or" : 'Laisser un commentaire';
?>
<section class="comments-section" id="comments">
    <h2 class="comments-heading"><?= $isGuestbook ? "Livre d'or" : 'Commentaires' ?></h2>

    <?php if ($commentError): ?>
    <p class="comments-error">Tous les champs obligatoires doivent être remplis (nom et message, max 2000 caractères).</p>
    <?php endif; ?>

    <form class="comment-form" method="post" action="/comment_submit.php">
        <input type="hidden" name="context_type" value="<?= htmlspecialchars($commentContextType) ?>">
        <input type="hidden" name="context_id"   value="<?= htmlspecialchars($commentContextId) ?>">
        <input type="hidden" name="return_url"   value="<?= htmlspecialchars($commentReturnUrl) ?>">

        <div class="comment-honeypot">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label class="comment-label">
            Nom <span class="comment-required">*</span>
            <input class="comment-input" type="text" name="author_name" maxlength="100" required>
        </label>

        <?php if ($isGuestbook): ?>
        <label class="comment-label">
            Ville / Pays
            <input class="comment-input" type="text" name="author_city" maxlength="100">
        </label>
        <?php endif; ?>

        <label class="comment-label">
            Message <span class="comment-required">*</span>
            <textarea class="comment-textarea" name="message" maxlength="2000" rows="4" required></textarea>
        </label>

        <button class="comment-submit" type="submit"><?= htmlspecialchars($btnLabel) ?></button>
    </form>
</section>
