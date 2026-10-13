<?php
	$heading = 'Import panel';
	$save_button = '';
	include __DIR__.'/cms_popup_toolbar.tpl.php';
?>
<div class="cms_page_panel_import_body" data-token="<?= htmlspecialchars((string)($token ?? ''), ENT_QUOTES) ?>">

	<?php if (($view ?? 'upload') === 'upload'): ?>

		<form class="cms_page_panel_import_form">
			<input type="file" name="import_file" class="cms_page_panel_import_input" accept=".zip,application/zip">
		</form>

	<?php elseif (($view ?? '') === 'message'): ?>

		<div class="cms_page_panel_import_message"><?= htmlspecialchars((string)($message ?? ''), ENT_QUOTES) ?></div>

	<?php elseif (($view ?? '') === 'list'): ?>

		<div class="cms_page_panel_import_list">
			<?php foreach ($blocks as $block): ?>
				<div class="cms_page_panel_import_block" data-zip_id="<?= (int)$block['zip_id'] ?>">
					<div class="cms_page_panel_import_line">Zip: <?= (int)$block['zip_id'] ?><?php if ($block['zip_title'] !== ''): ?>, <?= htmlspecialchars($block['zip_title'], ENT_QUOTES) ?><?php endif ?></div>
					<?php if (!empty($block['cms_id'])): ?>
						<div class="cms_page_panel_import_line">CMS: <?= (int)$block['cms_id'] ?><?php if ($block['cms_title'] !== ''): ?>, <?= htmlspecialchars($block['cms_title'], ENT_QUOTES) ?><?php endif ?></div>
					<?php endif ?>
					<?php foreach ($block['fks'] as $fk): ?>
						<div class="cms_page_panel_import_fk">
							<div class="cms_page_panel_import_tick" data-fk_id="<?= (int)$fk['id'] ?>">[ ]</div>
							Import <?= (int)$fk['id'] ?><?php if ($fk['title'] !== ''): ?>, <?= htmlspecialchars($fk['title'], ENT_QUOTES) ?><?php endif ?>
						</div>
					<?php endforeach ?>
					<div class="cms_page_panel_import_actions">
						<div class="cms_page_panel_import_go" data-mode="new">as new</div>
						<?php if (!empty($block['cms_id'])): ?>
							<div class="cms_page_panel_import_go" data-mode="overwrite">overwrite</div>
						<?php endif ?>
					</div>
				</div>
			<?php endforeach ?>
		</div>

	<?php endif ?>

</div>
