<div class="cms_preview_container"
	data-admin_design_width="1020"
	data-preview_desktop_width="1200"
	data-preview_mobile_width="750"
	data-preview_available="<?= !empty($preview_available) ? '1' : '0' ?>"
	data-preview_url="<?= !empty($preview_url) ? htmlspecialchars($preview_url, ENT_QUOTES) : '' ?>"
	data-preview_highlight_id="<?= !empty($preview_highlight_id) ? (int)$preview_highlight_id : 0 ?>"
	data-desktop_preview_width="<?= (int)($desktop_preview_width ?? 40) ?>"
	data-mobile_preview_width="<?= (int)($mobile_preview_width ?? 40) ?>"
	data-rem_px="<?= (int)($rem_px ?? 1000) ?>"
	data-rem_m_px="<?= (int)($rem_m_px ?? 500) ?>"
	data-rem_k="<?= (int)($rem_k ?? 10) ?>">
	<div class="cms_preview_content">
		<div class="cms_preview_unavailable">
			<div class="cms_preview_unavailable_box">No preview available</div>
		</div>
		<div class="cms_preview_frame_wrap">
			<iframe class="cms_preview_iframe" title="Page preview"></iframe>
		</div>
	</div>
</div>