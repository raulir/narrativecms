
<div style="colour: transparent; line-height: 0; opacity: 0; font-size: 0; "><?= $preview_text ?></div>
<?php if($show_link): ?>
<a href="<?= $link ?>" style="color: black; text-decoration: none; font-family: sans-serif; font-size: 11px; line-height: 20px; "><?= $link_text ?></a>
<?php endif ?>
<table cellpadding="0" cellspacing="0" border="0" width="100%" align="center" style="table-layout: fixed; background-color:white; "><tr><td>
<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" 
		style="table-layout: fixed; background-color:#fafafa; color: black; margin: 0 auto; ">
		
<tr>
	<td style="background-color: #fafafa; ">

		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="50">
				<td width="700" style="padding-top: 50px!important; "><img width="700" border="0" 
						style="display:block;" alt="Header image" src="<?= 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
							$_SERVER['SERVER_NAME'] ?><?php _i($image, 1400) ?>"></td>
     			<td width="50">
     		</tr>
     	</table>

	</td>
</tr>

<tr>
	<td style="background-color: #fafafa; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="100">&nbsp;</td>
				<td width="600" style="padding-top: 100px!important; padding-bottom: 50px!important; text-align: left; 
				font-size: 17px!important; line-height: 20px!important; letter-spacing: 0.1px; "><?= $text ?></td>
				<td width="100">&nbsp;</td>
			</tr>
		</table>

	</td>
</tr>

<?php foreach($extra_panels as $panel_id): ?>
	<?php _panel_id($panel_id) ?>
<?php endforeach ?>

</table>
</td></tr></table>	
