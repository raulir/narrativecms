<?php if (!empty($allowed)): ?>
<div class="page_translate_container"
		data-cms_page_id="<?= (int)($cms_page_id ?? 0) ?>"
		data-visitor_language="<?= htmlspecialchars((string)($visitor_language ?? ''), ENT_QUOTES, 'UTF-8') ?>"
		data-is_cms_user="<?= !empty($is_cms_user) ? 1 : 0 ?>">
</div>
<?php endif ?>
