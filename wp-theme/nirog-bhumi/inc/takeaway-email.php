<?php
/**
 * Post-consultation takeaway email workflow.
 *
 * After a consultation's scheduled end time has passed (end time + a configurable
 * delay), the paying customer is emailed a takeaway booklet link and a feedback
 * form link. The design is intentionally lean: the appointment start time lives
 * on the consultation entry (slot_date / slot_time), the schedule is mirrored onto
 * the linked WooCommerce order, and a single recurring WP-Cron sweep sends each
 * due email exactly once.
 *
 * Order meta written:
 *   consultation_start_time          (Y-m-d H:i:s, site timezone)
 *   consultation_end_time            (Y-m-d H:i:s)
 *   takeaway_email_scheduled_time    (Y-m-d H:i:s = end + delay)
 *   takeaway_email_sent_status       pending | sent | skipped
 *   takeaway_email_sent_at           (mysql datetime)
 */

if (!defined('ABSPATH')) {
  exit;
}

/** Editable values, all from Settings -> Nirog Bhumi Setup. */
function nirog_bhumi_takeaway_settings() {
  $s = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];
  return [
    'booklet_url'  => (string) ($s['takeaway_booklet_url'] ?? ''),
    'feedback_url' => (string) ($s['takeaway_feedback_url'] ?? home_url('/consultation-feedback/')),
    'duration'     => max(1, (int) ($s['consultation_duration_minutes'] ?? 30)),
    'delay'        => max(0, (int) ($s['takeaway_email_delay_minutes'] ?? 10)),
  ];
}

/** Most recent WooCommerce order linked to a consultation entry, or null. */
function nirog_bhumi_order_for_consultation_entry($entry_id) {
  if (!function_exists('wc_get_orders') || !$entry_id) {
    return null;
  }
  $orders = wc_get_orders([
    'limit'      => 1,
    'orderby'    => 'date',
    'order'      => 'DESC',
    'return'     => 'objects',
    'meta_query' => [[
      'key'   => '_nb_consultation_entry_id',
      'value' => (string) (int) $entry_id,
    ]],
  ]);
  return $orders ? $orders[0] : null;
}

/** Convert a stored local datetime string to a unix timestamp. */
function nirog_bhumi_local_to_timestamp($local) {
  if (!$local) {
    return 0;
  }
  try {
    return (new DateTime($local, wp_timezone()))->getTimestamp();
  } catch (Exception $e) {
    return 0;
  }
}

/**
 * Compute and store the takeaway schedule on the order from the consultation
 * entry's slot date/time. Called whenever the appointment is saved.
 */
function nirog_bhumi_sync_takeaway_schedule_from_entry($entry_id) {
  $order = nirog_bhumi_order_for_consultation_entry($entry_id);
  if (!$order) {
    return;
  }
  $slot_date = (string) get_post_meta($entry_id, 'slot_date', true);
  $slot_time = (string) get_post_meta($entry_id, 'slot_time', true);
  if (!$slot_date || !$slot_time) {
    return;
  }
  $cfg = nirog_bhumi_takeaway_settings();
  $start = DateTime::createFromFormat('Y-m-d H:i', $slot_date . ' ' . substr($slot_time, 0, 5), wp_timezone());
  if (!$start) {
    return;
  }
  $end  = (clone $start)->modify('+' . $cfg['duration'] . ' minutes');
  $send = (clone $end)->modify('+' . $cfg['delay'] . ' minutes');

  $order->update_meta_data('consultation_start_time', $start->format('Y-m-d H:i:s'));
  $order->update_meta_data('consultation_end_time', $end->format('Y-m-d H:i:s'));
  $order->update_meta_data('takeaway_email_scheduled_time', $send->format('Y-m-d H:i:s'));
  if ($order->get_meta('takeaway_email_sent_status') !== 'sent') {
    $order->update_meta_data('takeaway_email_sent_status', 'pending');
  }
  $order->save();

  // Best-effort: also fire the sweep right at the scheduled moment for promptness.
  // The sweep is idempotent, so this only complements the recurring schedule.
  if (!wp_next_scheduled('nirog_bhumi_takeaway_sweep_once') && $send->getTimestamp() > time()) {
    wp_schedule_single_event($send->getTimestamp() + 5, 'nirog_bhumi_takeaway_sweep');
  }
}

/** True if the order is paid and not in a terminal/negative state. */
function nirog_bhumi_order_is_sendable($order) {
  if (!$order) {
    return false;
  }
  if (in_array($order->get_status(), ['cancelled', 'refunded', 'failed', 'pending'], true)) {
    return false;
  }
  return $order->is_paid();
}

/** Build and send the takeaway email to one recipient. Returns bool. */
function nirog_bhumi_takeaway_email_send($to, $name, $context_id = '') {
  $to = sanitize_email((string) $to);
  if (!$to) {
    return false;
  }
  $cfg = nirog_bhumi_takeaway_settings();
  $name = trim((string) $name) ?: 'there';
  $booklet  = $cfg['booklet_url'] ?: home_url('/');
  $feedback = add_query_arg('o', rawurlencode((string) $context_id), $cfg['feedback_url'] ?: home_url('/consultation-feedback/'));

  $subject = 'Your Nirog Bhumi Consultation Takeaway Kit';
  $body = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#263126;line-height:1.6">'
    . '<p>Hi ' . esc_html($name) . ',</p>'
    . '<p>Thank you for attending your consultation with Nirog Bhumi.</p>'
    . '<p>Here is your free consultation takeaway booklet:<br>'
    . '<a href="' . esc_url($booklet) . '">' . esc_html($booklet) . '</a></p>'
    . '<p>It includes simple lifestyle actions you can start using from today.</p>'
    . '<p>Please also take 1 minute to share your feedback:<br>'
    . '<a href="' . esc_url($feedback) . '">' . esc_html($feedback) . '</a></p>'
    . '<p>Your feedback helps us improve the consultation experience and serve you better.</p>'
    . '<p>Warmly,<br>Team Nirog Bhumi</p>'
    . '</div>';

  return (bool) wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
}

/** Send the takeaway email for one WooCommerce order. Guards against double sending. */
function nirog_bhumi_send_takeaway_email($order) {
  if (is_numeric($order) && function_exists('wc_get_order')) {
    $order = wc_get_order((int) $order);
  }
  if (!$order || $order->get_meta('takeaway_email_sent_status') === 'sent') {
    return false;
  }
  if (!nirog_bhumi_order_is_sendable($order)) {
    return false;
  }
  $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
  $sent = nirog_bhumi_takeaway_email_send($order->get_billing_email(), $name, 'order-' . $order->get_id());
  if ($sent) {
    $order->update_meta_data('takeaway_email_sent_status', 'sent');
    $order->update_meta_data('takeaway_email_sent_at', current_time('mysql'));
    $order->save();
  }
  return $sent;
}

/**
 * Manual / no-gateway flow keyed to the consultation entry (nb_consultation).
 * Used when payments are verified manually and there is no WooCommerce order.
 */

/** Compute and store the takeaway schedule on a verified consultation entry. */
function nirog_bhumi_sync_takeaway_schedule_for_entry($entry_id) {
  if (get_post_type($entry_id) !== 'nb_consultation') {
    return;
  }
  if (get_post_meta($entry_id, 'payment_status', true) !== 'verified') {
    return;
  }
  $slot_date = (string) get_post_meta($entry_id, 'slot_date', true);
  $slot_time = (string) get_post_meta($entry_id, 'slot_time', true);
  if (!$slot_date || !$slot_time) {
    return;
  }
  $cfg = nirog_bhumi_takeaway_settings();
  $start = DateTime::createFromFormat('Y-m-d H:i', $slot_date . ' ' . substr($slot_time, 0, 5), wp_timezone());
  if (!$start) {
    return;
  }
  $end  = (clone $start)->modify('+' . $cfg['duration'] . ' minutes');
  $send = (clone $end)->modify('+' . $cfg['delay'] . ' minutes');
  update_post_meta($entry_id, 'consultation_start_time', $start->format('Y-m-d H:i:s'));
  update_post_meta($entry_id, 'consultation_end_time', $end->format('Y-m-d H:i:s'));
  update_post_meta($entry_id, 'takeaway_email_scheduled_time', $send->format('Y-m-d H:i:s'));
  if (get_post_meta($entry_id, 'takeaway_email_sent_status', true) !== 'sent') {
    update_post_meta($entry_id, 'takeaway_email_sent_status', 'pending');
  }
}

/** Send the takeaway email for one consultation entry. */
function nirog_bhumi_send_takeaway_email_for_entry($entry_id, $force = false) {
  if (get_post_type($entry_id) !== 'nb_consultation') {
    return false;
  }
  if (!$force && get_post_meta($entry_id, 'takeaway_email_sent_status', true) === 'sent') {
    return false;
  }
  if (get_post_meta($entry_id, 'payment_status', true) !== 'verified') {
    return false;
  }
  if (function_exists('nirog_bhumi_is_anonymised_record') && nirog_bhumi_is_anonymised_record($entry_id)) {
    return false;
  }
  $to = get_post_meta($entry_id, 'email', true);
  $name = get_post_meta($entry_id, 'name', true);
  $ref = function_exists('nirog_bhumi_consultation_reference') ? nirog_bhumi_consultation_reference($entry_id) : (string) $entry_id;
  $sent = nirog_bhumi_takeaway_email_send($to, $name, $ref);
  if ($sent) {
    update_post_meta($entry_id, 'takeaway_email_sent_status', 'sent');
    update_post_meta($entry_id, 'takeaway_email_sent_at', current_time('mysql'));
  }
  return $sent;
}

/** Should Cal.com bookings auto-send without waiting for manual payment verification? */
function nirog_bhumi_cal_autosend_enabled() {
  $s = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];
  return ($s['cal_autosend'] ?? 'yes') === 'yes';
}

/** Low-level: send the takeaway email for a consultation entry and mark it sent. */
function nirog_bhumi_deliver_takeaway_for_entry($entry_id) {
  if (get_post_type($entry_id) !== 'nb_consultation') {
    return false;
  }
  $to = get_post_meta($entry_id, 'email', true);
  $name = get_post_meta($entry_id, 'name', true);
  $ref = function_exists('nirog_bhumi_consultation_reference') ? nirog_bhumi_consultation_reference($entry_id) : (string) $entry_id;
  $sent = nirog_bhumi_takeaway_email_send($to, $name, $ref);
  if ($sent) {
    update_post_meta($entry_id, 'takeaway_email_sent_status', 'sent');
    update_post_meta($entry_id, 'takeaway_email_sent_at', current_time('mysql'));
  }
  return $sent;
}

/** Recurring sweep: send any due, eligible, not-yet-sent takeaway emails. */
function nirog_bhumi_takeaway_sweep() {
  $now = time();

  if (function_exists('wc_get_orders')) {
    $orders = wc_get_orders([
      'limit'      => 25,
      'orderby'    => 'date',
      'order'      => 'ASC',
      'return'     => 'objects',
      'meta_query' => [[
        'key'   => 'takeaway_email_sent_status',
        'value' => 'pending',
      ]],
    ]);
    foreach ($orders as $order) {
      $scheduled = $order->get_meta('takeaway_email_scheduled_time');
      if (!$scheduled || nirog_bhumi_local_to_timestamp($scheduled) > $now) {
        continue; // not due yet
      }
      if (in_array($order->get_status(), ['cancelled', 'refunded', 'failed'], true)) {
        $order->update_meta_data('takeaway_email_sent_status', 'skipped');
        $order->save();
        continue;
      }
      if (!nirog_bhumi_order_is_sendable($order)) {
        continue; // e.g. still awaiting payment; check again next sweep
      }
      nirog_bhumi_send_takeaway_email($order);
    }
  }

  // Manual / no-gateway flow: verified consultation entries with a due schedule.
  $entries = get_posts([
    'post_type'      => 'nb_consultation',
    'post_status'    => ['private', 'publish', 'draft'],
    'posts_per_page' => 25,
    'orderby'        => 'date',
    'order'          => 'ASC',
    'meta_query'     => [[
      'key'   => 'takeaway_email_sent_status',
      'value' => 'pending',
    ]],
  ]);
  foreach ($entries as $entry) {
    $scheduled = get_post_meta($entry->ID, 'takeaway_email_scheduled_time', true);
    if (!$scheduled || nirog_bhumi_local_to_timestamp($scheduled) > $now) {
      continue;
    }
    if (function_exists('nirog_bhumi_is_anonymised_record') && nirog_bhumi_is_anonymised_record($entry->ID)) {
      continue;
    }
    $verified = get_post_meta($entry->ID, 'payment_status', true) === 'verified';
    $cal_auto = get_post_meta($entry->ID, 'takeaway_source', true) === 'cal' && nirog_bhumi_cal_autosend_enabled();
    if (!$verified && !$cal_auto) {
      continue; // awaiting manual verification; check again next sweep
    }
    nirog_bhumi_deliver_takeaway_for_entry($entry->ID);
  }
}
add_action('nirog_bhumi_takeaway_sweep', 'nirog_bhumi_takeaway_sweep');

/** Admin "Send takeaway email now" action for a consultation entry. */
function nirog_bhumi_manual_send_takeaway() {
  $entry_id = isset($_GET['entry']) ? absint($_GET['entry']) : 0;
  if (!$entry_id || get_post_type($entry_id) !== 'nb_consultation' || !current_user_can('edit_post', $entry_id)) {
    wp_die(esc_html__('You are not allowed to send this email.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_send_takeaway_' . $entry_id);
  $redirect = admin_url('post.php?post=' . $entry_id . '&action=edit');

  if (get_post_meta($entry_id, 'payment_status', true) !== 'verified') {
    wp_safe_redirect(add_query_arg('nb_takeaway', 'unverified', $redirect));
    exit;
  }
  $to = sanitize_email((string) get_post_meta($entry_id, 'email', true));
  if (!$to) {
    wp_safe_redirect(add_query_arg('nb_takeaway', 'noemail', $redirect));
    exit;
  }
  // Explicit admin action: send directly, bypassing the automated-sweep guards
  // (e.g. anonymised-record / already-sent), so testing and resends always work.
  delete_option('nirog_bhumi_last_mail_error');
  $sent = nirog_bhumi_deliver_takeaway_for_entry($entry_id);
  wp_safe_redirect(add_query_arg('nb_takeaway', $sent ? 'sent' : 'mailfail', $redirect));
  exit;
}
add_action('admin_post_nirog_send_takeaway', 'nirog_bhumi_manual_send_takeaway');

/** Admin notice for the manual takeaway send result. */
function nirog_bhumi_takeaway_admin_notice() {
  if (!current_user_can('edit_posts') || !isset($_GET['nb_takeaway'])) {
    return;
  }
  $code = sanitize_key(wp_unslash($_GET['nb_takeaway']));
  if ($code === 'sent') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Takeaway email sent.', 'nirog-bhumi') . '</p></div>';
    return;
  }
  if ($code === 'unverified') {
    $message = __('Mark the payment status as Verified and click Update before sending the takeaway email.', 'nirog-bhumi');
  } elseif ($code === 'noemail') {
    $message = __('No customer email is stored on this consultation, so the takeaway email cannot be sent.', 'nirog-bhumi');
  } else {
    $mail_error = (string) get_option('nirog_bhumi_last_mail_error');
    $message = $mail_error
      ? sprintf(__('WordPress could not send the email: %s. Configure an authenticated SMTP plugin and try again.', 'nirog-bhumi'), $mail_error)
      : __('WordPress could not send the email. Your server is likely not configured to send mail - install and configure an SMTP plugin (the same applies to invoice emails).', 'nirog-bhumi');
  }
  echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'nirog_bhumi_takeaway_admin_notice');

/** Register a 5-minute cron interval. */
function nirog_bhumi_takeaway_cron_schedule($schedules) {
  if (!isset($schedules['nirog_bhumi_five_min'])) {
    $schedules['nirog_bhumi_five_min'] = ['interval' => 300, 'display' => __('Every 5 minutes', 'nirog-bhumi')];
  }
  return $schedules;
}
add_filter('cron_schedules', 'nirog_bhumi_takeaway_cron_schedule');

/** Ensure the recurring sweep is scheduled. */
function nirog_bhumi_takeaway_schedule_cron() {
  if (!wp_next_scheduled('nirog_bhumi_takeaway_sweep')) {
    wp_schedule_event(time() + 300, 'nirog_bhumi_five_min', 'nirog_bhumi_takeaway_sweep');
  }
}
add_action('init', 'nirog_bhumi_takeaway_schedule_cron', 40);

/** Stop trying once an order is cancelled / refunded / failed. */
function nirog_bhumi_takeaway_mark_skipped($order_id) {
  if (!function_exists('wc_get_order')) {
    return;
  }
  $order = wc_get_order($order_id);
  if (!$order || $order->get_meta('takeaway_email_sent_status') === 'sent') {
    return;
  }
  if ($order->get_meta('takeaway_email_scheduled_time') || $order->get_meta('takeaway_email_sent_status') === 'pending') {
    $order->update_meta_data('takeaway_email_sent_status', 'skipped');
    $order->save();
  }
}
add_action('woocommerce_order_status_cancelled', 'nirog_bhumi_takeaway_mark_skipped');
add_action('woocommerce_order_status_refunded', 'nirog_bhumi_takeaway_mark_skipped');
add_action('woocommerce_order_status_failed', 'nirog_bhumi_takeaway_mark_skipped');

/** Show the takeaway email status on the WooCommerce order screen. */
function nirog_bhumi_takeaway_order_admin_note($order) {
  $status = $order->get_meta('takeaway_email_sent_status');
  if (!$status) {
    return;
  }
  $scheduled = $order->get_meta('takeaway_email_scheduled_time');
  $sent_at   = $order->get_meta('takeaway_email_sent_at');
  echo '<p class="form-field form-field-wide"><strong>' . esc_html__('Takeaway email', 'nirog-bhumi') . ':</strong> ' . esc_html(ucfirst($status));
  if ($scheduled) {
    echo '<br><span class="description">' . esc_html__('Scheduled for', 'nirog-bhumi') . ': ' . esc_html($scheduled) . '</span>';
  }
  if ($sent_at) {
    echo '<br><span class="description">' . esc_html__('Sent at', 'nirog-bhumi') . ': ' . esc_html($sent_at) . '</span>';
  }
  echo '</p>';
}
add_action('woocommerce_admin_order_data_after_order_details', 'nirog_bhumi_takeaway_order_admin_note');
