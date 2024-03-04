<div class="shop_checkout_container" data-active="<?= $active ?>" data-number="<?= $order['number'] ?>"
		data-success_page="<?= $success_page['target'] != '_none' ? $success_page['url'] : '' ?>">
		
	<div class="shop_checkout_content">

		<div class="shop_checkout_close"><?= $close_label ?></div>
		
		<div class="shop_checkout_heading"><?= $heading ?></div>
		
		<div class="shop_checkout_menu">
			<div class="shop_checkout_menu_item shop_checkout_menu_item_basket shop_checkout_menu_item_available"><?= $menu_label_basket ?></div>
			<div class="shop_checkout_menu_item shop_checkout_menu_item_delivery"><?= $menu_label_delivery ?></div>
			<div class="shop_checkout_menu_item shop_checkout_menu_item_review"><?= $menu_label_review ?></div>
			<div class="shop_checkout_menu_item shop_checkout_menu_item_payment"><?= $menu_label_payment ?></div>
		</div>
		
		<div class="shop_checkout_tabs">
		
			<div class="shop_checkout_tab shop_checkout_tab_delivery">
			
				<div class="shop_checkout_delivery">
					
					<?php if(empty($delivery)): ?>
					
						<div class="shop_checkout_delivery_methods">
							<div class="shop_checkout_regions_label"><?= $region_label ?></div>
							<div class="shop_checkout_deliveries">
								<?php foreach($delivery_methods as $method): ?>
									<div class="shop_checkout_delivery_method" data-method_id="<?= $method['cms_page_panel_id'] ?>"><?= $method['description'] ?></div>
								<?php endforeach ?>
							</div>
						</div>
						
					<?php elseif(empty($address)): ?>
					
						<div class="shop_checkout_address">
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_delivery_label ?></div>
								<div class="shop_checkout_data">
									<div class="shop_checkout_delivery_description"><?= $delivery['description'] ?></div>
									<?php if(!empty($delivery['price'])): ?>
										<div class="shop_checkout_delivery_price"><?= $delivery['price'] ?></div>
									<?php endif ?>
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"></div>
								<div class="shop_checkout_data"><div class="shop_checkout_delivery_change"><?= $address_delivery_change_label ?></div></div>
							</div>
							<div class="shop_checkout_spacer"></div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_name_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_name"
											value="<?= !empty($delivery_meta['name']) ? $delivery_meta['name'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_address_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_address1 
											<?= !empty($address_error) && empty($delivery_meta['address1']) ? ' shop_checkout_error ' : '' ?>"
											value="<?= !empty($delivery_meta['address1']) ? $delivery_meta['address1'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_address2"
											value="<?= !empty($delivery_meta['address2']) ? $delivery_meta['address2'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_address3"
											value="<?= !empty($delivery_meta['address3']) ? $delivery_meta['address3'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_postcode_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_postcode 
											<?= !empty($address_error) && empty($delivery_meta['postcode']) ? ' shop_checkout_error ' : '' ?>"
											value="<?= !empty($delivery_meta['postcode']) ? $delivery_meta['postcode'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_county_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_county 
											<?= !empty($address_error) && empty($delivery_meta['county']) ? ' shop_checkout_error ' : '' ?>"
											value="<?= !empty($delivery_meta['county']) ? $delivery_meta['county'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_country_label ?></div>
								<div class="shop_checkout_data">
									<select class="shop_checkout_delivery_input shop_checkout_input_country
											<?= !empty($address_error) && empty($delivery_meta['country']) ? ' shop_checkout_error ' : '' ?>">
											
										<option value=""><?= $address_country_select_label ?></option>
										<?php foreach($countries as $country): ?>
											<option value="<?= $country['code'] ?>" 
													<?= ((!empty($delivery_meta['country']) && $delivery_meta['country'] == $country['code']) 
															|| (!empty($selected_country) && $selected_country == $country['code'])) 
															? ' selected="selected" ' : '' ?>>
												
												<?= $country['name'] ?>
												
											</option>
										<?php endforeach ?>
										
									</select>
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_email_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_email 
											<?= !empty($address_error) && empty($delivery_meta['email']) ? ' shop_checkout_error ' : '' ?>"
											value="<?= !empty($delivery_meta['email']) ? $delivery_meta['email'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_row">
								<div class="shop_checkout_label"><?= $address_phone_label ?></div>
								<div class="shop_checkout_data">
									<input class="shop_checkout_delivery_input shop_checkout_input_phone"
											value="<?= !empty($delivery_meta['phone']) ? $delivery_meta['phone'] : '' ?>">
								</div>
							</div>
							<div class="shop_checkout_spacer"></div>
							<div class="cg_cta shop_checkout_delivery_save"><?= $address_save_label ?></div>
						</div>
						
					<?php endif ?>
					
				</div>

			</div>
		
			<div class="shop_checkout_tab shop_checkout_tab_review">
			
				<?php if(!empty($delivery_line) && !empty($delivery_meta) && empty($address_error)): ?>
			
					<div class="shop_checkout_lines">
					
						<?php if(!empty($lines) && count($lines)): ?>
							<?php foreach($lines as $line): ?>
								<?php if (!empty($line['product'])): ?>
									<div class="shop_checkout_line">
					
										<?php if (!empty($line['product']['image'])): ?>
											<div class="shop_checkout_line_image" <?php _ib($line['product']['image'], 150) ?>></div>
										<?php endif ?>
										
										<div class="shop_checkout_line_heading"><?= $line['item'] ?></div>
										<div class="cg_h4 shop_checkout_line_description"><?= $line['description'] ?></div>
										<div class="shop_checkout_line_price"><?= number_format($line['price_main'], 0, '.', '&nbsp;') 
												?>.<div class="shop_checkout_line_decimals"><?= str_pad($line['price_decimals'], 2, '0', STR_PAD_LEFT) ?></div></div>
						
									</div>
								<?php endif ?>
							<?php endforeach ?>
						<?php endif ?>
						
					</div>
					
					<div class="shop_checkout_review_delivery">
					
						<div class="shop_checkout_review_delivery_heading"><?= $review_delivery_heading ?></div>
						
						<div class="cg_h4 shop_checkout_review_delivery_address">
							<div class="shop_checkout_review_delivery_address_line">
								<div class="shop_checkout_review_delivery_address_label"><?= $review_delivery_address_label ?></div>
								<?= $delivery_meta['address1'] ?>
							</div>
							<?php if(!empty($delivery_meta['address2'])): ?>
								<div class="shop_checkout_review_delivery_address_line"><?= $delivery_meta['address2'] ?></div>
							<?php endif ?>
							<?php if(!empty($delivery_meta['address3'])): ?>
								<div class="shop_checkout_review_delivery_address_line"><?= $delivery_meta['address3'] ?></div>
							<?php endif ?>
							<div class="shop_checkout_review_delivery_address_line">
								<div class="shop_checkout_review_delivery_address_label"><?= $review_delivery_county_label ?></div>
								<?= $delivery_meta['county'] ?>
							</div>
							<div class="shop_checkout_review_delivery_spacer"></div>
							<div class="shop_checkout_review_delivery_address_line">
								<div class="shop_checkout_review_delivery_address_label"><?= $review_delivery_postcode_label ?></div>
								<?= $delivery_meta['postcode'] ?>
							</div>
							<div class="shop_checkout_review_delivery_spacer"></div>
							<div class="shop_checkout_review_delivery_address_line">
								<div class="shop_checkout_review_delivery_address_label"><?= $review_delivery_country_label ?></div>
								<?php foreach($countries as $country): ?>
									<?php if($country['code'] == $delivery_meta['country']): ?>
										<?= $country['name'] ?>
									<?php endif ?>
								<?php endforeach ?>
							</div>
							<div class="shop_checkout_review_delivery_spacer"></div>
							<div class="shop_checkout_review_delivery_address_line">
								<div class="shop_checkout_review_delivery_address_label"><?= $review_delivery_email_label ?></div>
								<?= $delivery_meta['email'] ?>
							</div>
						</div>

						<div class="shop_checkout_review_delivery_price"><?= number_format($delivery_line['price_main'], 0, '.', '&nbsp;') 
								?>.<div class="shop_checkout_line_decimals"><?= str_pad($delivery_line['price_decimals'], 2, '0', STR_PAD_LEFT) ?></div></div>
					
					</div>
					
					<div class="shop_checkout_review_calculation">
					
						<div class="shop_checkout_review_total_label"><?= $review_total_label ?></div>
						<div class="shop_checkout_review_total"><?= number_format($total, 2, '.', '&nbsp;') ?></div>
					
						<?php if($show_tax): ?>
							<div class="shop_checkout_review_tax_label"><?= $review_tax_label ?></div>
							<div class="shop_checkout_review_tax"><?= number_format($tax, 2, '.', '&nbsp;') ?></div>
						<?php endif ?>
						
						<div class="shop_checkout_review_row">
							<div class="shop_checkout_review_topay_label"><?= $review_topay_label ?></div>
							<div class="shop_checkout_review_topay" data-amount="<?= $topay_main.'.'.$topay_decimals ?>">
								<?= number_format($topay_main, 0, '.', '&nbsp;') 
								?>.<div class="shop_checkout_line_decimals"><?= str_pad($topay_decimals, 2, '0', STR_PAD_LEFT) ?></div></div>
						</div>

					</div>
					
					<?php // _print_r($delivery_line) ?>
					
					<div class="cg_cta shop_checkout_pay"><?= $proceed_label ?></div>
					
				<?php else: ?>
				
					<div class="shop_checkout_tab_error"><?= $noreview_label ?></div>
				
				<?php endif ?>

			</div>
			
			<div class="shop_checkout_tab shop_checkout_tab_payment">
			
				<?php if (!empty($topay) && !empty($delivery_meta) && empty($address_error)): ?>
				
				
					<?php _panel('stripe/payment', []) ?>
				
				
					<?php /*
						if(empty($is_live)):
							$url = 'https://secure-test.worldpay.com/wcc/purchase?instId=1410061'.
									'&cartId=' . $order['number'] .
									'&amount=' . $topay .
									'&currency=GBP' .
									'&hideCurrency=true' .
									'&lang=en' .
									'&noLanguageMenu=true' .
									'&testMode=100' .
									'&address1=' . urlencode($delivery_meta['address1']) .
									(!empty($delivery_meta['address2']) ? ('&address2=' . urlencode($delivery_meta['address2'])) : '') .
									(!empty($delivery_meta['address3']) ? ('&address3=' . urlencode($delivery_meta['address3'])) : '') .
									'&postcode=' . urlencode($delivery_meta['postcode']) .
									'&country=' . urlencode($delivery_meta['country']) .
									'&town=' . urlencode($delivery_meta['county']) .
									'&email=' . urlencode($delivery_meta['email']);
						else:
							$url = 'https://secure.worldpay.com/wcc/purchase?instId=1410061'.
									'&cartId=' . $order['number'] .
									'&amount=' . $topay .
									'&currency=GBP' .
									'&hideCurrency=true' .
									'&lang=en' .
									'&noLanguageMenu=true' .
									'&testMode=0' .
									'&address1=' . urlencode($delivery_meta['address1']) .
									(!empty($delivery_meta['address2']) ? ('&address2=' . urlencode($delivery_meta['address2'])) : '') .
									(!empty($delivery_meta['address3']) ? ('&address3=' . urlencode($delivery_meta['address3'])) : '') .
									'&postcode=' . urlencode($delivery_meta['postcode']) .
									'&country=' . urlencode($delivery_meta['country']) .
									'&town=' . urlencode($delivery_meta['county']) .
									'&email=' . urlencode($delivery_meta['email']);
						endif;
					?>
			
					<iframe class="shop_checkout_iframe" width="780" height="500" 
							src="<?= $url ?>"></iframe> */ ?>
							
				<?php else: ?>
				
					<div class="shop_checkout_tab_error"><?= $nopay_label ?></div>
				
				<?php endif ?>
						
			</div>

		</div>

	</div>

</div>
