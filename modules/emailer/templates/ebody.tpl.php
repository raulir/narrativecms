
<div style="colour: transparent; line-height: 0; opacity: 0; font-size: 0; "><?= $preview_text ?></div>
<a href="<?= $link ?>" style="color: black; text-decoration: none; font-family: sans-serif; font-size: 11px; line-height: 20px; ">
	Problems viewing? Click here to open in web browser
</a>
<table cellpadding="0" cellspacing="0" border="0" width="100%" align="center" style="table-layout: fixed; background-color:white; "><tr><td>
<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" 
		style="table-layout: fixed; background-color:#ebf1ea; color: black; margin: 0 auto; ">
		
<tr>
	<td style="background-color:#ebf1ea; ">

		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr style="background-color:#ebf1ea; ">
				<td width="80">
				<td width="640" style="padding-top: 50px!important; "><?php if(!empty($strapline_image)): ?>
						<img width="640" border="0" class="width_640" style="display:block;" alt="We have a new name - Hartree - North East Cambridge" 
     						src="<?php _i($strapline_image, 1280) ?>">
					<?php endif ?><img width="640" border="0" class="width_640" style="display:block;" 
							alt="We have a new name - Hartree - North East Cambridge" 
     						src="<?php _i($title_image, 1280) ?>"></td>
     			<td width="80">
     		</tr>
     	</table>

	</td>
</tr>
<tr>
	<td style="background-color:#d1f43d; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
				<?php $i = _i($main_image, ['width' => 640, 'silent' => 1]) ?>
			
			<tr height="270" style="background-color:#d1f43d; ">
				<td width="80" style="vertical-align: top; ">
					
					<table cellpadding="0" cellspacing="0" border="0" width="80" align="center" style="table-layout: fixed; ">
						<tr height="270">
							<td style="background-color:#ebf1ea; "></td>
						</tr>
					</table>

				</td>
				<td width="640" rowspan="2"><img width="640" border="0" class="width_640" style="display: block; " 
						alt="Relax" src="<?= $GLOBALS['config']['upload_url'].$i['image'] ?>"></td>
				<td width="80" style="vertical-align: top; ">
					
					<table cellpadding="0" cellspacing="0" border="0" width="80" align="center" style="table-layout: fixed; ">
						<tr height="270">
							<td style="background-color:#ebf1ea; "></td>
						</tr>
					</table>
					
					
				</td>
			</tr>
			
		</table>

	</td>
</tr>

<?php if (!empty($lead)): ?>
<tr>
	<td style="background-color:#d1f43d; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="130">&nbsp;</td>
				<td width="540" class="font_degular_bold" style="padding-top: 40px!important; padding-bottom: 10px!important; text-align: center; 
				font-size: 18px!important; line-height: 22px!important; letter-spacing: 0.2px; "><?= $lead ?></td>
				<td width="130">&nbsp;</td>
			</tr>
		</table>

	</td>
</tr>
<?php endif ?>

<?php if (!empty($lead_extra_image)): ?>
<tr>
	<td width="800" style="background-color:#d1f43d; ">

     	<img width="800" border="0" class="width_800" style="display:block;" alt="HARTREE" 
     			src="<?php _i($lead_extra_image) ?>">

	</td>
</tr>
<?php else: ?>
<tr>
	<td width="800" style="background-color:#d1f43d; height: 30px!important; ">
		&nbsp;
	</td>
</tr>
<?php endif ?>

<?php foreach($items as $item): ?>

<tr>
	<td style="background-color:<?= empty($item['colour']) ? '#d8e4d6' : $item['colour'] ?>; ">
		
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
			
				<?php if($item['align'] == 'right'): ?>
					<td width="400" class="font_degular" style="vertical-align: top; padding-top: 40px!important; ">
						<?php if (!empty($item['heading_image'])): ?>
							<img width="400" border="0" class="width_400" style="display: block; vertical-align: top; " alt="Block heading" 
								src="<?php _i($item['heading_image'], 400) ?>">
						<?php endif ?>
						<div class="font_degular" 
								style="box-sizing: border-box; padding-bottom: 40px!important; padding-left: 50px!important; padding-right: 20px!important; 
								text-align: left; font-size: 16px!important; line-height: 19px!important; "><?= $item['text'] ?></div>
					</td>
				<?php endif ?>

				<td width="400" style="vertical-align: top; padding-top: 44px!important; padding-bottom: 44px!important;">
				
					<table cellpadding="0" cellspacing="0" border="0" width="400" align="center" style="table-layout: fixed; ">
						<tr>
							<td width="<?= $item['align'] == 'left' ? '50' : '20' ?>">&nbsp;</td>
							<td width="330"><img width="330" border="0" class="width_330" style="display: block; vertical-align: top; " alt="Block image" 
								src="<?php _i($item['image'], 660) ?>"></td>
							<td width="<?= $item['align'] == 'left' ? '20' : '50' ?>">&nbsp;</td>
						</tr>
					</table>

				</td>

				<?php if($item['align'] == 'left'): ?>
					<td width="400" class="font_degular" style="vertical-align: top; padding-top: 40px!important; ">
						<?php if (!empty($item['heading_image'])): ?>
							<img width="400" border="0" class="width_400" style="display: block; vertical-align: top; " alt="Block heading" 
								src="<?php _i($item['heading_image'], 400) ?>">
						<?php endif ?>
						<div class="font_degular" 
								style="box-sizing: border-box; padding-bottom: 40px!important; padding-left: 20px!important; padding-right: 50px!important; 
								text-align: left; font-size: 16px!important; line-height: 19px!important; "><?= $item['text'] ?></div>
					</td>
				<?php endif ?>

			</tr>
		</table>

	</td>
</tr>

<?php endforeach ?>


<tr>
	<td style="background-color:#fe6033; ">
		<img width="800" border="0" class="width_800" style="display:block;" alt="Get involved" src="<?php _i($events_heading_image, 1600) ?>">
	</td>
</tr>
<tr>
	<td style="background-color:#fe6033; ">
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="120">&nbsp;</td>
				<td width="560" class="font_degular_bold" style="padding-top: 35px!important; padding-bottom: 35px!important; text-align: center; 
					font-size: 18px!important; line-height: 22px!important; letter-spacing: 0.2px!important; "><?= $events_lead ?></td>
				<td width="120">&nbsp;</td>
			</tr>
		</table>
	</td>
</tr>
<tr>
	<td style="background-color:#fe6033; ">

     	<img width="800" border="0" class="width_800" style="display:block;" alt="Next month" src="<?php _i($upcoming_heading_image, 1600) ?>">

	</td>
</tr>
<tr>
	<td style="background-color:#fe6033; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="120">&nbsp;</td>
				<td width="280" style="vertical-align: top; ">

					<table cellpadding="0" cellspacing="0" border="0" width="280" align="center" style="table-layout: fixed; ">
						<?php foreach($left_panels as $panel_id): ?>
							<?php _panel_id($panel_id) ?>
						<?php endforeach ?>
					</table>

				</td>
				<td width="280" style="vertical-align: top; ">

					<table cellpadding="0" cellspacing="0" border="0" width="280" align="center" style="table-layout: fixed; ">
						<?php foreach($right_panels as $panel_id): ?>
							<?php _panel_id($panel_id) ?>
						<?php endforeach ?>
					</table>

				</td>
				<td width="120">&nbsp;</td>
			</tr>
		</table>

	</td>
</tr>

<tr>
	<td style="background-color:#fe6033; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				<td width="120">&nbsp;</td>
				<td width="560" class="font_degular_bold" style="padding-top: 70px!important; padding-bottom: 80px!important; 
						text-align: center; font-size: 18px!important; line-height: 22px!important; letter-spacing: 0.2px; "
						><?= str_replace("\n", '<br>', trim($events_text)) ?></td>
				<td width="120">&nbsp;</td>
			</tr>
		</table>
	
	</td>
</tr>

<!-- footer -->
<tr>
	<td style="background-color:black; ">
		<a href="https://hartree.life/" target="_blank"><img width="800" border="0" class="width_800" style="display:block;" 
				alt="Find out more about Hartree" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f12.png"></a>
	</td>
</tr>
<tr>
	<td style="background-color:black; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				
				<td width="400"><a href="https://www.instagram.com/hartreecambridge/" target="_blank"><img 
						width="400" border="0" class="width_400" style="display:block;" 
						alt="Mr X" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f13.png"></a></td>
				
				<td width="400"><a href="https://www.instagram.com/hartreecambridge/" target="_blank"><img 
						width="400" border="0" class="width_400" style="display:block;" 
						alt="Instagram" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f14.png"></a></td>
				
			</tr>
		</table>

	</td>
</tr>
<tr>
	<td width="800" style="background-color:black; ">
		<img width="800" border="0" class="width_800" style="display:block; max-width: 800px!important; " alt="Master developers" 
				src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f15.png">
	</td>
</tr>
<tr>
	<td style="background-color:black; ">
	
		<table cellpadding="0" cellspacing="0" border="0" width="800" align="center" style="table-layout: fixed; ">
			<tr>
				
				<td width="400"><a href="https://landsec-uandi.com/" target="_blank"><img width="400" border="0" class="width_400" 
				style="display:block;" alt="UI" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f16.png"></a></td>
				
				<td width="400"><a href="https://wearetown.co.uk/" target="_blank"><img width="400" border="0" class="width_400" 
				style="display:block;" alt="Town" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f17.png"></a></td>
				
			</tr>
		</table>

	</td>
</tr>
<tr>
	<td width="800" style="background-color:black; max-width: 800px; ">
		<img width="800" border="0" class="width_800" style="display:block;" alt="Hartree" src="https://mediaseo.com/_emails/20230725_ui_hartree/img/f18.png">
	</td>
</tr>

<tr style="background-color: black; color: white; ">
	<td class="font_neuzeit" style="font-size: 11px!important; line-height: 20px!important; letter-spacing: 0.2px; " align="center">

		<div style="border-top: 70px solid black; border-bottom: 10px solid black; font-size: 12px!important; line-height: 15px!important; ">
			<span style="color:white; ">You are receiving this email because you are part of the<br>
			U+I digital network. You can <unsubscribe style="color:white; text-decoration: underline; ">unsubscribe</unsubscribe> below
			or view our <a href="https://www.uandiplc.com/privacy-notice/" style="color:white; text-decoration: underline; " 
			target="_blank">privacy policy</a></span>.
		</div>
	</td>
</tr>

<tr style="background-color: black; color: white; ">
	<td class="font_neuzeit" style="font-size: 11px!important; line-height: 20px!important; letter-spacing: 0.2px; 
			border-bottom: 40px solid black!important; " align="center">

		<div style="border-top: 20px solid black!important; font-size: 12px!important; line-height: 15px!important; ">
			<!-- span style="color:white; ">U and I Group PLC (U+I) | Registered in England & Wales<br></span>
			<span style="color:white; ">No: 1528784, 7A Howick Place, London, SW1P 1DZ.<br></span -->
			<span style="color:white; ">© 2023 All rights reserved.</span><br>
			<span style="color:white; ">ECF, Unit 418, 418 Union Street, London, SE1 0LH, UK</span>
		</div>

	</td>
</tr>

</table>
</td></tr></table>	
