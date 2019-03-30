<?php if(!empty($GLOBALS['language'])): ?>
	<div class="admin_right cms_language_select_container">
		<div class="cms_toolbar_buttons_hidden cms_language_select_content">
			
			<div class="cms_tool_button cms_language_select_current" data-language="<?= $_SESSION['cms_language'] ?>">
				<?= $_SESSION['cms_language'] ?>
				<div class="cms_toolbar_buttons_hidden_arrow" style="background-image: url('/modules/cms/img/cms_down.png'); "></div>	
			</div>
			
			<div class="cms_toolbar_buttons_hidden_container cms_language_select_options">
				<?php foreach($GLOBALS['language']['languages'] as $language_id => $language_label): ?>
					<div class="cms_tool_button cms_language_select_option" data-language="<?= $language_id ?>"><?= $language_id.' - '.$language_label ?></div>
				<?php endforeach ?>
			</div>
			
		</div>
	</div>
<?php endif ?>