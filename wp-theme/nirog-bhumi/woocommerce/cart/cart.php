<?php defined('ABSPATH') || exit; do_action('woocommerce_before_cart'); ?>
<form class="woocommerce-cart-form nb-wc-cart clean-cart" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
  <?php do_action('woocommerce_before_cart_table'); ?>
  <div class="cart-items">
    <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :
      $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
      if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0 || !apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) { continue; }
      $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
    ?>
    <article class="cart-row <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
      <figure><?php echo apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image('woocommerce_thumbnail'), $cart_item, $cart_item_key); ?></figure>
      <div>
        <span><?php echo esc_html($_product->get_type()); ?></span>
        <h2><?php echo $product_permalink ? '<a href="' . esc_url($product_permalink) . '">' . wp_kses_post($_product->get_name()) . '</a>' : wp_kses_post($_product->get_name()); ?></h2>
        <?php echo wc_get_formatted_cart_item_data($cart_item); ?>
        <?php echo apply_filters('woocommerce_cart_item_remove_link', sprintf('<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>', esc_url(wc_get_cart_remove_url($cart_item_key)), esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($_product->get_name()))), esc_attr($_product->get_id()), esc_attr($_product->get_sku()), esc_html__('Remove', 'nirog-bhumi')), $cart_item_key); ?>
      </div>
      <div class="qty-control"><?php woocommerce_quantity_input(['input_name' => "cart[{$cart_item_key}][qty]", 'input_value' => $cart_item['quantity'], 'min_value' => 0, 'max_value' => $_product->get_max_purchase_quantity()], $_product, true); ?></div>
      <strong><?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?></strong>
    </article>
    <?php endforeach; ?>
    <div class="cart-options">
      <?php if (wc_coupons_enabled()) : ?>
      <label><?php esc_html_e('Coupon code', 'nirog-bhumi'); ?><input type="text" name="coupon_code" placeholder="<?php esc_attr_e('Enter code', 'nirog-bhumi'); ?>"></label>
      <button class="pill ghost" type="submit" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_html_e('Apply Coupon', 'nirog-bhumi'); ?></button>
      <?php endif; ?>
      <label><?php esc_html_e('Order note', 'nirog-bhumi'); ?><textarea rows="3" name="nb_order_note" placeholder="<?php esc_attr_e('Any food, medical or delivery notes', 'nirog-bhumi'); ?>"></textarea></label>
      <button class="pill primary" type="submit" name="update_cart" value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>"><?php esc_html_e('Update Cart', 'nirog-bhumi'); ?></button>
      <?php do_action('woocommerce_cart_actions'); wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
    </div>
  </div>
  <?php do_action('woocommerce_after_cart_table'); ?>
</form>
<aside class="cart-summary-panel nb-cart-totals">
  <?php do_action('woocommerce_before_cart_collaterals'); ?>
  <?php woocommerce_cart_totals(); ?>
  <?php do_action('woocommerce_after_cart'); ?>
</aside>