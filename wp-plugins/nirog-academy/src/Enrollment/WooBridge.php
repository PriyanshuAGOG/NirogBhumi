<?php
namespace NirogBhumi\Academy\Enrollment;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Roles;

defined('ABSPATH') || exit;

/**
 * WooCommerce + Razorpay → Courses access.
 *
 *  - A product's "Course" tab links it to one or more programmes.
 *  - When an order is paid (Processing or Completed) the buyer is enrolled.
 *  - Refunded, cancelled or failed orders close the access they opened.
 *  - Partial refunds are flagged for a person to decide.
 *  - Staff can create a "payment offer": a pending order with a pay link, for
 *    programmes that are joined after a consultation.
 */
final class WooBridge {
  private static bool $registered = false;

  public static function register(): void {
    if (self::$registered || !function_exists('wc_get_order')) {
      return;
    }
    self::$registered = true;

    add_filter('woocommerce_product_data_tabs', [self::class, 'product_tab']);
    add_action('woocommerce_product_data_panels', [self::class, 'product_panel']);
    add_action('woocommerce_admin_process_product_object', [self::class, 'save_product']);

    add_action('woocommerce_order_status_processing', [self::class, 'grant'], 20);
    add_action('woocommerce_order_status_completed', [self::class, 'grant'], 20);
    add_action('woocommerce_payment_complete', [self::class, 'grant'], 20);

    add_action('woocommerce_order_status_refunded', [self::class, 'revoke'], 20);
    add_action('woocommerce_order_status_cancelled', [self::class, 'revoke'], 20);
    add_action('woocommerce_order_status_failed', [self::class, 'revoke'], 20);
    add_action('woocommerce_order_partially_refunded', [self::class, 'partial_refund'], 20, 2);

    add_filter('woocommerce_account_menu_items', [self::class, 'account_menu']);
    add_filter('woocommerce_get_endpoint_url', [self::class, 'account_menu_url'], 10, 2);
    add_action('woocommerce_thankyou', [self::class, 'thank_you'], 5);
  }

  // ------------------------------------------------------------ product tab

  public static function product_tab(array $tabs): array {
    $tabs['nba_academy'] = [
      'label' => __('Courses', 'nirog-academy'),
      'target' => 'nba_academy_data',
      'class' => [],
      'priority' => 75,
    ];
    return $tabs;
  }

  public static function product_panel(): void {
    global $post;
    $selected = array_map('intval', (array) get_post_meta($post->ID, '_nba_programme_ids', true));
    $programmes = get_posts(['post_type' => PostTypes::PROGRAMME, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    echo '<div id="nba_academy_data" class="panel woocommerce_options_panel"><div class="options_group">';
    echo '<p class="form-field"><label>' . esc_html__('Gives access to', 'nirog-academy') . '</label>';
    if (!$programmes) {
      echo '<span>' . esc_html__('Create a programme under Courses → Programmes first.', 'nirog-academy') . '</span>';
    }
    foreach ($programmes as $p) {
      echo '<label style="display:block;float:none;width:auto;margin:0 0 6px"><input type="checkbox" name="nba_programme_ids[]" value="' . (int) $p->ID . '"' . checked(in_array($p->ID, $selected, true), true, false) . '> ' . esc_html($p->post_title) . '</label>';
    }
    echo '</p><p class="form-field description" style="padding-left:12px">' . esc_html__('When an order for this product is paid, the buyer is enrolled automatically and receives a welcome email. Mark programme products as Virtual so no shipping is asked for.', 'nirog-academy') . '</p>';
    wp_nonce_field('nba_product_programmes', 'nba_product_nonce');
    echo '</div></div>';
  }

  public static function save_product(\WC_Product $product): void {
    if (!isset($_POST['nba_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nba_product_nonce'])), 'nba_product_programmes')) {
      return;
    }
    $ids = isset($_POST['nba_programme_ids']) ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['nba_programme_ids'])))) : [];
    $old = array_map('intval', (array) $product->get_meta('_nba_programme_ids'));
    $product->update_meta_data('_nba_programme_ids', $ids);

    // Keep the reverse link on each programme, for the programme screen.
    foreach (array_unique(array_merge($old, $ids)) as $programme_id) {
      $linked = array_map('intval', (array) get_post_meta($programme_id, '_nba_product_ids', true));
      $linked = in_array($programme_id, $ids, true)
        ? array_values(array_unique(array_merge($linked, [$product->get_id()])))
        : array_values(array_diff($linked, [$product->get_id()]));
      update_post_meta($programme_id, '_nba_product_ids', array_filter($linked));
    }
  }

  /** @return int[] */
  public static function programmes_for_product(int $product_id): array {
    $ids = array_map('intval', (array) get_post_meta($product_id, '_nba_programme_ids', true));
    if (!$ids) {
      $parent = (int) wp_get_post_parent_id($product_id);
      if ($parent) {
        $ids = array_map('intval', (array) get_post_meta($parent, '_nba_programme_ids', true));
      }
    }
    return array_values(array_filter($ids));
  }

  /** @return int[] */
  public static function programmes_in_order(\WC_Order $order): array {
    $ids = [];
    foreach ($order->get_items() as $item) {
      if ($item instanceof \WC_Order_Item_Product) {
        $ids = array_merge($ids, self::programmes_for_product($item->get_variation_id() ?: $item->get_product_id()));
      }
    }
    return array_values(array_unique($ids));
  }

  // ------------------------------------------------------------ order events

  public static function grant(int $order_id): void {
    $order = wc_get_order($order_id);
    if (!$order instanceof \WC_Order || !$order->is_paid()) {
      return;
    }
    $programmes = self::programmes_in_order($order);
    if (!$programmes) {
      return;
    }
    $user_id = self::ensure_customer($order);
    if (!$user_id) {
      $order->add_order_note(__('Courses: could not create an account for this order. Enroll the buyer manually.', 'nirog-academy'));
      return;
    }
    foreach ($programmes as $programme_id) {
      $result = nba()->enrollments->create([
        'user_id' => $user_id,
        'programme_id' => $programme_id,
        'source' => 'woocommerce',
        'order_id' => $order_id,
      ]);
      if (is_wp_error($result)) {
        $order->add_order_note(sprintf('Courses: %s', $result->get_error_message()));
      } elseif (!$order->get_meta('_nba_granted_' . $programme_id)) {
        $order->update_meta_data('_nba_granted_' . $programme_id, gmdate('c'));
        $order->add_order_note(sprintf(
          /* translators: %s: programme name */
          __('Courses: access opened for %s.', 'nirog-academy'),
          get_the_title($programme_id)
        ));
      }
    }
    $order->save();
  }

  /**
   * The order's customer account, creating one for guest checkouts.
   */
  public static function ensure_customer(\WC_Order $order): int {
    $user_id = (int) $order->get_customer_id();
    if ($user_id) {
      return $user_id;
    }
    $email = sanitize_email($order->get_billing_email());
    if (!is_email($email)) {
      return 0;
    }
    $existing = get_user_by('email', $email);
    if ($existing) {
      $order->set_customer_id($existing->ID);
      $order->save();
      return $existing->ID;
    }
    $user_id = self::create_user($email, $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_billing_phone());
    if ($user_id) {
      $order->set_customer_id($user_id);
      $order->save();
    }
    return $user_id;
  }

  public static function create_user(string $email, string $first = '', string $last = '', string $phone = ''): int {
    $base = sanitize_user(strstr($email, '@', true) ?: 'participant', true);
    $login = $base;
    $i = 1;
    while (username_exists($login)) {
      $login = $base . (++$i);
    }
    $user_id = wp_insert_user([
      'user_login' => $login,
      'user_email' => $email,
      'user_pass' => wp_generate_password(24, true, true),
      'first_name' => $first,
      'last_name' => $last,
      'display_name' => trim($first . ' ' . $last) ?: $login,
      'role' => get_role('customer') ? 'customer' : 'subscriber',
    ]);
    if (is_wp_error($user_id)) {
      return 0;
    }
    Roles::make_participant($user_id);
    update_user_meta($user_id, 'nba_needs_password', 1);
    if ($phone !== '') {
      update_user_meta($user_id, 'billing_phone', $phone);
    }
    update_user_meta($user_id, 'billing_email', $email);
    update_user_meta($user_id, 'billing_first_name', $first);
    update_user_meta($user_id, 'billing_last_name', $last);
    return (int) $user_id;
  }

  public static function revoke(int $order_id): void {
    $order = wc_get_order($order_id);
    if (!$order) {
      return;
    }
    foreach (nba()->enrollments->for_order($order_id) as $row) {
      if (in_array($row->status, ['active', 'paused', 'pending'], true)) {
        nba()->enrollments->set_status((int) $row->id, 'revoked', 'Order ' . $order->get_status());
        $order->add_order_note(sprintf(
          /* translators: %s: programme name */
          __('Courses: access closed for %s.', 'nirog-academy'),
          get_the_title((int) $row->programme_id)
        ));
      }
    }
    // Renewals extend an earlier enrollment instead of creating one: flag those.
    foreach (array_map('intval', (array) $order->get_meta('_nba_enrollment_ids')) as $enrollment_id) {
      $row = nba()->enrollments->get($enrollment_id);
      if ($row && (int) $row->order_id !== $order_id) {
        nba()->enrollments->flag_review($enrollment_id, sprintf('Order #%d (an extension) was %s. Check the access end date.', $order_id, $order->get_status()));
      }
    }
  }

  public static function partial_refund(int $order_id, int $refund_id): void {
    foreach (array_map('intval', (array) (wc_get_order($order_id) ?: null)?->get_meta('_nba_enrollment_ids')) as $enrollment_id) {
      nba()->enrollments->flag_review($enrollment_id, sprintf('Partial refund #%d on order #%d. Decide whether access should change.', $refund_id, $order_id));
      $row = nba()->enrollments->get($enrollment_id);
      nba()->audit->log('enrollment.partial_refund', $row ? (int) $row->user_id : 0, 'enrollment', $enrollment_id, ['order_id' => $order_id, 'refund_id' => $refund_id]);
    }
  }

  // ------------------------------------------------------------ payment offers

  /**
   * Create a pending order for one person and email them the pay link.
   *
   * @return array{order_id:int, pay_url:string}|\WP_Error
   */
  public static function create_offer(string $email, string $first, string $last, string $phone, int $product_id, ?float $price = null, string $note = ''): array|\WP_Error {
    $email = sanitize_email($email);
    if (!is_email($email)) {
      return new \WP_Error('nba_email', __('Enter a valid email address.', 'nirog-academy'));
    }
    $product = wc_get_product($product_id);
    if (!$product) {
      return new \WP_Error('nba_product', __('Choose a programme product.', 'nirog-academy'));
    }
    if (!self::programmes_for_product($product_id)) {
      return new \WP_Error('nba_product', __('That product is not linked to a programme. Open the product and tick a programme on its Course tab.', 'nirog-academy'));
    }
    $user = get_user_by('email', $email);
    $user_id = $user ? $user->ID : self::create_user($email, $first, $last, $phone);
    if (!$user_id) {
      return new \WP_Error('nba_user', __('Could not create the participant account.', 'nirog-academy'));
    }

    $order = wc_create_order(['customer_id' => $user_id, 'created_via' => 'nirog-academy-offer']);
    if (is_wp_error($order)) {
      return $order;
    }
    $args = [];
    if ($price !== null && $price >= 0) {
      $args = ['subtotal' => $price, 'total' => $price];
    }
    $order->add_product($product, 1, $args);
    $order->set_billing_email($email);
    $order->set_billing_first_name($first ?: (string) get_user_meta($user_id, 'first_name', true));
    $order->set_billing_last_name($last ?: (string) get_user_meta($user_id, 'last_name', true));
    if ($phone !== '') {
      $order->set_billing_phone($phone);
    }
    $order->calculate_totals();
    $order->set_status('pending');
    if ($note !== '') {
      $order->add_order_note($note, false, true);
    }
    $order->save();

    // WooCommerce's "Order details / pay for this order" email includes the pay link.
    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();
    if (isset($emails['WC_Email_Customer_Invoice'])) {
      $emails['WC_Email_Customer_Invoice']->trigger($order->get_id(), $order);
    }
    nba()->audit->log('offer.created', $user_id, 'order', $order->get_id(), ['product_id' => $product_id, 'price' => $price]);
    return ['order_id' => $order->get_id(), 'pay_url' => $order->get_checkout_payment_url()];
  }

  // ------------------------------------------------------------ account links

  public static function account_menu(array $items): array {
    $user_id = get_current_user_id();
    if (Router::is_locked_for($user_id)) {
      return $items;
    }
    if (!$user_id || (!nba()->enrollments->for_user($user_id) && !Roles::is_staff($user_id))) {
      return $items;
    }
    $out = [];
    foreach ($items as $key => $label) {
      $out[$key] = $label;
      if ($key === 'dashboard') {
        $out['nba-academy'] = __('My programmes', 'nirog-academy');
      }
    }
    return isset($out['nba-academy']) ? $out : ['nba-academy' => __('My programmes', 'nirog-academy')] + $out;
  }

  public static function account_menu_url(string $url, string $endpoint): string {
    return $endpoint === 'nba-academy' ? Router::url() : $url;
  }

  public static function thank_you(int $order_id): void {
    $order = wc_get_order($order_id);
    if (!$order || !self::programmes_in_order($order)) {
      return;
    }
    if (Router::is_locked_for(get_current_user_id())) {
      echo '<section class="nba-thankyou" style="margin:24px 0;padding:24px;border:1px solid #d8d0c0;border-radius:8px;background:#f8f6ef"><p>' . esc_html__('Thank you. Your place is confirmed. We will email you your login details when the programme opens.', 'nirog-academy') . '</p></section>';
      return;
    }
    echo '<section class="nba-thankyou" style="margin:24px 0;padding:24px;border:1px solid #d8d0c0;border-radius:8px;background:#f8f6ef">';
    echo '<h2 style="margin-top:0">' . esc_html__('Your programme is ready', 'nirog-academy') . '</h2>';
    if ($order->is_paid()) {
      echo '<p>' . esc_html__('We have emailed you the next steps. If you are new, the email has a button to choose your password.', 'nirog-academy') . '</p>';
      echo '<p><a class="button" href="' . esc_url(Router::url()) . '">' . esc_html__('Open my course', 'nirog-academy') . '</a></p>';
    } else {
      echo '<p>' . esc_html__('Your access opens as soon as the payment is confirmed. We will email you.', 'nirog-academy') . '</p>';
    }
    echo '</section>';
  }
}
