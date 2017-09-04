GENERATE AND DOWNLOAD DUMPS:<br>
<br>
<?php if (!empty($filemdate)): ?>

	<a style="text-decoration: underline; " <?php _lh('admin/export/_resources/'); ?>>Download resources (images, videos etc) dump<br><?= $filemdate ?></a><br><br>

<?php endif ?>

<?php if (!empty($filemdate2)): ?>

	<a style="text-decoration: underline; " <?php _lh('admin/export/_database/'); ?>>Download database (texts, links etc) sql dump<br><?= $filemdate2 ?></a><br><br>

<?php endif ?>

<a <?php _lh('admin/dump/generate/') ?>>[ Regenerate dumps ]</a><br>
<br>
<br>
<br>
UPLOAD DUMPS:<br>
<br>

<div>
	Upload resources dump (_resources.zip):<br>
	<form class="cms_dump_resources_form" method="post" enctype="multipart/form-data" >
		<input type="file" name="file"><br>
		<input type="hidden" name="do" value="cms_dump_resources"><br>
		<input type="submit" value="upload"> 
	</form>
</div>

<br>
<br>
<br>

<div>
	Upload database dump (_database.zip):<br>
	<form class="cms_dump_resources_form" method="post" enctype="multipart/form-data" >
		<input type="file" name="file"><br>
		<input type="hidden" name="do" value="cms_dump_database"><br>
		<input type="submit" value="upload"> 
	</form>
</div>