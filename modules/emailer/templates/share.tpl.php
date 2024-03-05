<tr>
	<td style="background-color: #fafafa; padding-bottom: 20px; padding-top: 30px;">

		<table cellpadding="0" cellspacing="0" border="0" width="800" align="left" style="table-layout: fixed; ">
			<tr>
				<td width="100">&nbsp;</td>
				<td width="600" style="text-align: left; font-weight: normal; font-size: 17px; "><?= $heading ?></td>
				<td width="100">&nbsp;</td>
			</tr>
		</table>

	</td>
</tr>
<tr>
	<td style="background-color: #fafafa; padding-bottom: 30px; ">

		<table cellpadding="0" cellspacing="0" border="0" width="800" align="left" style="table-layout: fixed; ">
			<tr>
				<td width="100">&nbsp;</td>
				<td width="600" style="text-align:left;">
				
					<table cellpadding="0" cellspacing="0" border="0" width="600" align="left" style="table-layout: fixed; ">
						<tr>
								<td width="40"><a style="text-decoration: none; color: black; " href="https://facebook.com/sharer/sharer.php?u=<?= $url ?>" 
								target="_blank"><img width="40" border="0" style="display:block;" alt="FB share" 
								src="<?= 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
								$_SERVER['SERVER_NAME'] ?><?php _i($fb_image, 80) ?>"></a></td>
							<td width="15">&nbsp;</td>
							<td width="40"><a style="text-decoration: none; color: black; " href="https://www.twitter.com/share?url=<?= $url ?>" 
								target="_blank"><img width="40" border="0" style="display:block;" alt="X share" 
								src="<?= 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
								$_SERVER['SERVER_NAME'] ?><?php _i($x_image, 80) ?>"></a></td>
							<td width="15">&nbsp;</td>
							<td width="40"><a style="text-decoration: none; color: black; " 
								href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $url ?>" target="_blank"><img width="40" border="0" 
								style="display:block;" alt="LI share" src="<?= 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
								$_SERVER['SERVER_NAME'] ?><?php _i($li_image, 80) ?>"></a></td>
							<td></td>
						</tr>
					</table>

				</td>
				<td width="100">&nbsp;</td>
			</tr>
		</table>

	</td>
</tr>
