<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
<?php $idsx = []; foreach($ids as $id){ $idsx[] = $id['gtag_id']; }; ?>
<div class="basic_gtag_container" data-ids="<?= implode(',', $idsx) ?>" data-delay="<?= $delay ?>"></div>
<?php endif ?>