<?php if(!empty($image)): ?>
<tr>
	<td width="20">&nbsp;</td>
	<td width="240">
		<img width="240" border="0" class="width_240" style="display: block; vertical-align: top; " 
			alt="Hartree - planting the future" src="<?php _i($image, 480) ?>">
	</td>
	<td width="20">&nbsp;</td>
</tr>
<?php endif ?>

<?php if(!empty($heading_image)): ?>
<tr>
	<td width="20">&nbsp;</td>
	<td width="240" style="<?= !empty($image) ? 'padding-top: 18px!important; ' : '' ?>">
		<img width="240" border="0" class="width_240" style="display:block;" alt="Hartree - North East Cambridge" src="<?php _i($heading_image, 480) ?>">
	</td>
	<td width="20">&nbsp;</td>
</tr>
<?php endif ?>

<?php foreach($items as $item): ?>
<tr>
	<td>&nbsp;</td>
	<td width="240" style="vertical-align: top; ">
		<div class="font_degular" style="font-size: 16px!important; line-height: 20px!important; padding-top: 15px!important; padding-bottom: 10px!important; "
				><?= empty(trim($item['text'])) ? '&nbsp;' : str_replace("\n", '<br>', trim($item['text'])) ?></div>
		</div>
		<a style="text-decoration: none; color: black; " href="<?= $item['cta_url'] ?>" target="_blank"><img 
					width="240" border="0" class="width_240" style="display:block;" alt="Hartree - Event" 
					src="<?php _i($item['cta_label_image'], 480) ?>"></a>
	</td>
	<td>&nbsp;</td>
</tr>
<?php endforeach ?>