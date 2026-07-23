<div class="cms_pages_container">

	<div class="cms_column">

		<div class="cms_pages_section">
			<div class="cms_toolbar">
				<div class="cms_tool_text">Pages</div>
				<a class="cms_tool_button cms_right" <?php _lh('admin/page/0') ?> data-cms_ctrl="a">New Page</a>
			</div>

			<?php if (!(empty($main_pages['user']) || count($main_pages['user']) == 0)): ?>
				<ul class="cms_pages_list cms_pages_list_user">
					<?php cms_pages_list($main_pages['user']) ?>
				</ul>
			<?php else: ?>
				<div class="cms_pages_position_empty">No pages defined</div>
			<?php endif ?>
		</div>

		<div class="cms_pages_section">
			<div class="cms_toolbar">
				<div class="cms_tool_text">Lists</div>
			</div>

			<?php if (!(empty($main_pages['list']) || count($main_pages['list']) == 0)): ?>
				<ul class="cms_pages_list cms_pages_list_list">
					<?php cms_pages_list($main_pages['list']) ?>
				</ul>
			<?php else: ?>
				<div class="cms_pages_position_empty">No list page templates</div>
			<?php endif ?>
		</div>

		<div class="cms_pages_section">
			<div class="cms_toolbar">
				<div class="cms_tool_text">System</div>
			</div>

			<?php if (!(empty($main_pages['system']) || count($main_pages['system']) == 0)): ?>
				<ul class="cms_pages_list cms_pages_list_system">
					<?php cms_pages_list($main_pages['system']) ?>
				</ul>
			<?php else: ?>
				<div class="cms_pages_position_empty">No system pages</div>
			<?php endif ?>
		</div>

	</div>
	
	<div class="cms_column">
	
		<?php foreach($positions as $position): ?>

			<div class="cms_pages_position">
				<div class="cms_toolbar">
					<div class="cms_tool_text"><?= $position['plural'] ?></div>
					<a class="cms_tool_button cms_right" <?php _lh('admin/page/0/'.$position['id']) ?>>New <?= $position['id'] ?></a>
				</div>
			</div>
			
			<?php if (!(empty($pages[$position['id']]) || count($pages[$position['id']]) == 0)): ?>
				<ul class="cms_pages_list">
					<?php cms_pages_list($pages[$position['id']]) ?>
				</ul>
			<?php else: ?>
				<div class="cms_pages_position_empty">No <?= $position['plural'] ?> defined</div>
			<?php endif ?>
		
		<?php endforeach ?>

	</div>

</div>

<?php function cms_pages_list($pages){ ?>

	<?php foreach($pages as $page): ?>

		<li class="cms_pages_page
				<?= !empty($page['status']) ? ' cms_item_hidden ' : '' ?>
				<?= $page['cms_page_id'] == $GLOBALS['config']['landing_page']['_value'] ? ' cms_pages_page_landing ' : '' ?>">
		
			<?php if($page['cms_page_id'] == $GLOBALS['config']['landing_page']['_value']): ?>
				<div class="cms_pages_landing" <?php _ib('cms/cms_landing.png', 16) ?>></div>
			<?php endif ?>

			<div class="cms_pages_label"><?= !empty($page['title']) ? $page['title'] : '[ no title ]' ?></div>
			<a class="cms_small_button" <?php _lh('admin/page/' . $page['cms_page_id']) ?>>edit</a>
			
			<a class="cms_pages_link" <?php _lh('admin/page/' . $page['cms_page_id']) ?>></a>
		
		</li>

	<?php endforeach ?>

<?php } ?>
