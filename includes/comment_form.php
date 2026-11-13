<?php
$isGuestbook = ($commentContextType === 'guestbook');
$btnLabel    = $isGuestbook ? "Signer le livre d'or" : 'Publier le commentaire';
$heading     = $isGuestbook ? "Livre d'or" : 'Laisser un commentaire';
?>
<div class="cf-wrap" id="comments">

    <?php if ($commentError): ?>
    <div class="cf-notice cf-notice--error">Merci de renseigner votre nom et un message.</div>
    <?php endif; ?>

    <?php if (isset($commentSuccess) && $commentSuccess): ?>
    <div class="cf-notice cf-notice--success">✓ &nbsp; Votre commentaire a bien été enregistré.</div>
    <?php endif; ?>

    <h2 class="cf-heading"><?= $heading ?></h2>

    <form class="cf-form" method="post" action="/comment_submit.php" novalidate>
        <input type="hidden" name="context_type" value="<?= htmlspecialchars($commentContextType) ?>">
        <input type="hidden" name="context_id"   value="<?= htmlspecialchars($commentContextId) ?>">
        <input type="hidden" name="return_url"   value="<?= htmlspecialchars($commentReturnUrl) ?>">
        <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="cf-row">
            <div class="cf-field">
                <label class="cf-label" for="cf_name">Nom <span class="cf-req">*</span></label>
                <input class="cf-input" type="text" id="cf_name" name="author_name" maxlength="100" autocomplete="name" required>
            </div>
            <?php if ($isGuestbook): ?>
            <div class="cf-field">
                <label class="cf-label" for="cf_city">Ville / Pays</label>
                <input class="cf-input" type="text" id="cf_city" name="author_city" maxlength="100" autocomplete="address-level2">
            </div>
            <?php endif; ?>
        </div>

        <div class="cf-field">
            <label class="cf-label" for="cf_message">Message <span class="cf-req">*</span></label>
            <textarea class="cf-input cf-textarea" id="cf_message" name="message" maxlength="2000" rows="4" required></textarea>
        </div>

        <div class="cf-footer">
            <span class="cf-hint"><span class="cf-req">*</span> champs obligatoires</span>
            <button class="cf-btn" type="submit">
                <?= htmlspecialchars($btnLabel) ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </div>
    </form>
</div>
