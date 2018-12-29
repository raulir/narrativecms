<div class="cms_toolbar">

	<div class="admin_tool_text">Download and upload site dumps</div>

</div>

<div class="cms_dump_item">
	<div class="cms_dump_item_heading">Description</div>
	<div class="cms_dump_item_filemtime">Generated</div>
	<div class="cms_dump_item_size">Size</div>
</div>

<?php foreach($files as $file): ?>

	<div class="cms_dump_item">

		<div class="cms_dump_item_heading"><?= $file['heading'] ?></div>
		<div class="cms_dump_item_filemtime"><?= $file['filemtime'] ?></div>
		<div class="cms_dump_item_size"><?= $file['size'] ?></div>

		<div class="cms_dump_item_generate">
			<form style="display: inline; " method="post" enctype="multipart/form-data" >
				<input type="hidden" name="what" value="<?= $file['trigger'] ?>">
				<input type="hidden" name="do" value="generate">
				<input type="submit" value="update"> 
			</form>
		</div>

		<?php if (!empty($file['size'])): ?>
			<div style="text-decoration: underline; " class="cms_dump_item_download">
				<a <?php _lh('admin/export/'.str_replace('.zip', '', $file['filename']).'/') ?>>download</a>
			</div>
		<?php endif ?>

	</div>

<?php endforeach ?>

<br>
<br>
UPLOAD DUMP:<br>
<br>

<div>
	Upload dump (.zip):<br>
	<form class="cms_dump_resources_form" method="post" enctype="multipart/form-data" >
		<input type="file" name="file"><br>
		<input type="hidden" name="do" value="cms_dump_upload"><br>
		<input type="submit" value="upload"> 
	</form>
</div>
