<?php if(!empty($GLOBALS['config']['analytics']) && !empty($gtm_id)): ?>
<div class="gtm_container" data-gtm_id="<?= $gtm_id ?>" data-delay="<?= $delay ?>"></div>
<script type="text/javascript">window.dataLayer = window.dataLayer || [{'gtm.start': new Date().getTime(),event:'gtm.js'}]</script>
<?php endif ?>