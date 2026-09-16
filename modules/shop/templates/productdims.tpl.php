<div class="shop_productdims_container" data-product_id="<?= $product_id ?>">
	<div class="shop_productdims_contents">

		<?php foreach($variations as $dim => $dim_data): ?>
		
			<div class="product_feature shop_productdims_dim">
				
				<input type="hidden" class="productbuy_input shop_productbuy_input shop_productdims_dim_input" name="<?= $dim ?>" value="">
	
				<div class="cg_h3 product_feature_label shop_productdims_dim_label"><?= $dim_labels[$dim] ?></div>
				
				<div class="cg_h3 product_feature_text shop_productdims_dim_text">
				
					<?php foreach($dim_data as $dim_value => $dim_value_data): ?>
						<div class="shop_productdims_dim_value" data-value="<?= $dim_value ?>" data-name="<?= $dim ?>">
							<div class="shop_productdims_dim_value_label"><?= $dim_value_data['data']['label'] ?></div>
							<?php if(!empty($dim_value_data['data']['description']) || !empty($dim_value_data['availability'])): ?>
								<div class="shop_productdims_dim_value_description">
									<?php if(!empty($dim_value_data['data']['description'])): ?>
										<div class="shop_productdims_dim_value_description_text"><?= $dim_value_data['data']['description'] ?></div>
									<?php endif ?>
									<?php if(!empty($dim_value_data['availability'])): ?>
										<div class="shop_productdims_dim_value_description_heading"><?= $hint_heading ?></div>
										<div class="shop_productdims_dim_value_description_availability">
											<?php foreach($dim_value_data['availability'] as $dkey => $dvals): ?>
												<div class="shop_productdims_dim_value_description_availability_label"
														><?= $dim_labels[$dkey] ?></div>
												<?php foreach($dvals as $dval => $rest): ?>
													<div class="shop_productdims_dim_value_description_availability_item"
															><?= $variations[$dkey][$dval]['data']['label'] ?></div>
												<?php endforeach ?>
											<?php endforeach ?>
										</div>
									<?php endif ?>
								</div>
							<?php endif ?>
							<div class="shop_productdims_dim_value_count"><?= $dim_value_data['count'] ?></div>
						</div>
					<?php endforeach ?>
					
					<?php if(!empty($errors[$dim])): ?>
						<div class="shop_productdims_error"><?= $errors[$dim] ?></div>
					<?php endif ?>
					
				</div>
			
			</div>
	
		<?php endforeach ?>

	</div>
</div>
