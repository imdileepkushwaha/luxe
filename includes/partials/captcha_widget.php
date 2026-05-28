<?php

declare(strict_types=1);

require_once __DIR__ . '/../captcha.php';

if (!luxe_captcha_enabled()) {
    return;
}

$scope = luxe_captcha_normalize_scope((string) ($captchaElementId ?? 'luxe-captcha'));
$inputId = $scope . '-answer';

if (luxe_captcha_provider() === 'recaptcha') {
    ?>
<div class="luxe-captcha-wrap" style="margin:12px 0 16px;">
  <div id="<?= h($scope) ?>" class="luxe-captcha-mount" data-luxe-captcha data-luxe-captcha-scope="<?= h($scope) ?>" aria-label="CAPTCHA verification"></div>
</div>
    <?php
    return;
}

$challenge = luxe_captcha_get_or_create_challenge($scope);
$captchaUiContext = luxe_captcha_ui_context();
$rowHtml = static function () use ($scope, $inputId, $challenge): void {
    ?>
    <div class="luxe-captcha-row">
      <span class="luxe-captcha-q" id="<?= h($scope) ?>-question"><?= h($challenge['question']) ?> = ?</span>
      <button type="button" class="luxe-captcha-refresh" data-captcha-scope="<?= h($scope) ?>" aria-label="New security check" title="New question">↻</button>
      <input type="hidden" name="captcha_scope" value="<?= h($scope) ?>">
      <input
        type="text"
        class="luxe-captcha-input"
        id="<?= h($inputId) ?>"
        name="captcha_answer"
        inputmode="numeric"
        pattern="-?[0-9]*"
        autocomplete="off"
        placeholder="Answer"
        required
      >
    </div>
    <?php
};

if ($captchaUiContext === 'theme-1'): ?>
<div class="theme1-form-field luxe-captcha-wrap luxe-captcha-wrap--theme-1" data-luxe-captcha-scope="<?= h($scope) ?>">
  <label class="luxe-captcha-label" for="<?= h($inputId) ?>">Security check</label>
  <?php $rowHtml(); ?>
</div>
<?php elseif (preg_match('#^theme-\d+$#', $captchaUiContext)): ?>
<label class="t2-login-field luxe-captcha-wrap luxe-captcha-wrap--<?= h($captchaUiContext) ?>" for="<?= h($inputId) ?>" data-luxe-captcha-scope="<?= h($scope) ?>">
  <span class="t2-login-label">Security check</span>
  <?php $rowHtml(); ?>
</label>
<?php elseif ($captchaUiContext === 'default'): ?>
<div class="input-group luxe-captcha-wrap luxe-captcha-wrap--default" data-luxe-captcha-scope="<?= h($scope) ?>">
  <label class="luxe-captcha-label" for="<?= h($inputId) ?>">Security check</label>
  <?php $rowHtml(); ?>
</div>
<?php else: ?>
<div class="luxe-captcha-wrap luxe-captcha-wrap--admin" data-luxe-captcha-scope="<?= h($scope) ?>">
  <label class="luxe-captcha-label" for="<?= h($inputId) ?>">Security check</label>
  <?php $rowHtml(); ?>
</div>
<?php endif; ?>
