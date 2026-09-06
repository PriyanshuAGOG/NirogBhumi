<?php
defined('ABSPATH') || exit;
?>
<div class="store-empty-shelf">
  <p class="eyebrow"><?php esc_html_e('Nothing here yet', 'nirog-bhumi'); ?></p>
  <h2><?php esc_html_e('This shelf is still being stocked.', 'nirog-bhumi'); ?></h2>
  <p><?php esc_html_e('Try another shelf, or write to us and we will tell you when it is ready.', 'nirog-bhumi'); ?></p>
  <a class="pill ghost" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Back to the store', 'nirog-bhumi'); ?></a>
</div>
