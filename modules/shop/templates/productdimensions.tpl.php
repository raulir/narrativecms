<div class="shop_productdimensions_container" data-product_id="<?= $product_id ?>">
	<div class="shop_productdimensions_contents">

		<?php foreach($variations as $dimension => $dimension_data): ?>
		
			<div class="product_feature shop_productdimensions_dimension">
				
				<input type="hidden" class="productbuy_input shop_productdimensions_dimension_input" name="<?= $dimension ?>" value="">
	
				<div class="cg_h3 product_feature_label shop_productdimensions_dimension_label"><?= $dimension_labels[$dimension] ?></div>
				
				<div class="cg_h3 product_feature_text shop_productdimensions_dimension_text">
				
					<?php foreach($dimension_data as $dimension_value => $dimension_value_data): ?>
						<div class="shop_productdimensions_dimension_value" data-value="<?= $dimension_value ?>" data-name="<?= $dimension ?>">
							<div class="shop_productdimensions_dimension_value_label"><?= $dimension_value_data['data']['label'] ?></div>
							<?php if(!empty($dimension_value_data['data']['description']) || !empty($dimension_value_data['availability'])): ?>
								<div class="shop_productdimensions_dimension_value_description">
									<?php if(!empty($dimension_value_data['data']['description'])): ?>
										<div class="shop_productdimensions_dimension_value_description_text"><?= $dimension_value_data['data']['description'] ?></div>
									<?php endif ?>
									<?php if(!empty($dimension_value_data['availability'])): ?>
										<div class="shop_productdimensions_dimension_value_description_heading"><?= $hint_heading ?></div>
										<div class="shop_productdimensions_dimension_value_description_availability">
											<?php foreach($dimension_value_data['availability'] as $dkey => $dvals): ?>
												<div class="shop_productdimensions_dimension_value_description_availability_label"
														><?= $dimension_labels[$dkey] ?></div>
												<?php foreach($dvals as $dval => $rest): ?>
													<div class="shop_productdimensions_dimension_value_description_availability_item"
															><?= $variations[$dkey][$dval]['data']['label'] ?></div>
												<?php endforeach ?>
											<?php endforeach ?>
										</div>
									<?php endif ?>
								</div>
							<?php endif ?>
							<div class="shop_productdimensions_dimension_value_count"><?= $dimension_value_data['count'] ?></div>
						</div>
					<?php endforeach ?>
					
					<?php if(!empty($errors[$dimension])): ?>
						<div class="shop_productdimensions_error"><?= $errors[$dimension] ?></div>
					<?php endif ?>
					
				</div>
			
			</div>
	
		<?php endforeach ?>

	</div>
</div>