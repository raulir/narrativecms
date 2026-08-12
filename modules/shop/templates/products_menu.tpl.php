<?php
	$cat_id = (int)($category_id ?? 0);
	$sub_id = (int)($subcategory_id ?? 0);
	$coll_id = (int)($collection_id ?? 0);
	$cat_label = $cat_id > 0
			? ($active_category_heading ?? $label_all_categories)
			: ($label_all_categories ?? 'All categories');
	$coll_label = $active_collection_heading ?? ($label_all_collections ?? 'All collections');
	$show_collections = !empty($has_collections);
?>
<div class="products_menu_container"
		data-category_id="<?= $cat_id ?>"
		data-subcategory_id="<?= $sub_id ?>"
		data-collection_id="<?= $coll_id ?>">
	<div class="products_menu_content">

		<div class="products_menu_left">

			<div class="products_menu_category<?= $cat_id > 0 ? ' products_menu_category_selected' : '' ?>"
					data-all_label="<?= htmlspecialchars($label_all_categories ?? 'All categories', ENT_QUOTES, 'UTF-8') ?>">
				<div class="products_menu_category_toggle products_menu_item<?= $cat_id > 0 ? ' products_menu_item_active' : '' ?>"
						data-filter="category_toggle">
					<div class="products_menu_category_label"><?= $cat_label ?></div>
					<div class="products_menu_caret" aria-hidden="true"></div>
				</div>
				<div class="products_menu_category_list">
					<div class="products_menu_category_option<?= $cat_id < 1 ? ' products_menu_option_active' : '' ?>"
							data-filter="category"
							data-category_id="0"
							data-heading="<?= htmlspecialchars($label_all_categories ?? 'All categories', ENT_QUOTES, 'UTF-8') ?>"><?=
								$label_all_categories ?? 'All categories'
					?></div>
					<?php foreach(($categories ?? []) as $category): ?>
						<?php $id = (int)($category['cms_page_panel_id'] ?? 0); if ($id < 1) continue; ?>
						<div class="products_menu_category_option<?= $cat_id === $id ? ' products_menu_option_active' : '' ?>"
								data-filter="category"
								data-category_id="<?= $id ?>"
								data-heading="<?= htmlspecialchars($category['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?=
									$category['heading'] ?? ''
						?></div>
					<?php endforeach ?>
				</div>
			</div>

			<?php if ($cat_id > 0 && !empty($subcategories)): ?>
				<div class="products_menu_item products_menu_item_all_subs<?= $sub_id < 1 ? ' products_menu_item_active' : '' ?>"
						data-filter="subcategory"
						data-subcategory_id="0"><?= $label_all_in_category ?? 'All' ?></div>
				<?php foreach($subcategories as $subcategory): ?>
					<?php $sid = (int)($subcategory['cms_page_panel_id'] ?? 0); if ($sid < 1) continue; ?>
					<div class="products_menu_item products_menu_item_subcategory<?= $sub_id === $sid ? ' products_menu_item_active' : '' ?>"
							data-filter="subcategory"
							data-subcategory_id="<?= $sid ?>"
							data-heading="<?= htmlspecialchars($subcategory['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?=
								$subcategory['heading'] ?? ''
					?></div>
				<?php endforeach ?>
			<?php endif ?>

		</div>

		<?php if ($show_collections): ?>
			<div class="products_menu_right">
				<div class="products_menu_collection<?= $coll_id > 0 ? ' products_menu_collection_selected' : '' ?>"
						data-all_label="<?= htmlspecialchars($label_all_collections ?? 'All collections', ENT_QUOTES, 'UTF-8') ?>">
					<div class="products_menu_collection_toggle products_menu_item<?= $coll_id > 0 ? ' products_menu_item_active' : '' ?>"
							data-filter="collection_toggle">
						<div class="products_menu_collection_label"><?= $coll_label ?></div>
						<div class="products_menu_caret" aria-hidden="true"></div>
					</div>
					<div class="products_menu_collection_list">
						<div class="products_menu_collection_option<?= $coll_id < 1 ? ' products_menu_option_active' : '' ?>"
								data-filter="collection"
								data-collection_id="0"
								data-heading="<?= htmlspecialchars($label_all_collections ?? 'All collections', ENT_QUOTES, 'UTF-8') ?>"><?=
									$label_all_collections ?? 'All collections'
						?></div>
						<?php foreach(($collections ?? []) as $collection): ?>
							<?php $cid = (int)($collection['cms_page_panel_id'] ?? 0); if ($cid < 1) continue; ?>
							<div class="products_menu_collection_option<?= $coll_id === $cid ? ' products_menu_option_active' : '' ?>"
									data-filter="collection"
									data-collection_id="<?= $cid ?>"
									data-heading="<?= htmlspecialchars($collection['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?=
										$collection['heading'] ?? ''
							?></div>
						<?php endforeach ?>
					</div>
				</div>
			</div>
		<?php endif ?>

	</div>
</div>
