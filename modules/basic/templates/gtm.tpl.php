<?php if(!empty($GLOBALS['config']['analytics']) && !empty($gtm_id)): ?>
<?php // Consent is checked in the browser (gtm.js basic_tracking_allowed): this html may come from the page cache ?>
<div class="gtm_container" data-gtm_id="<?= $gtm_id ?>" data-delay="<?= $delay ?>" data-consent="<?= in_array('gdpr', $GLOBALS['config']['modules'] ?? [], true) ? 1 : 0 ?>"></div>
<script type="text/javascript">window.dataLayer = window.dataLayer || [{'gtm.start': new Date().getTime(),event:'gtm.js'}]</script>
<?php endif ?>
