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

/** Send the takeaway email for one order. Guards against double sending. */
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
  $to = $order->get_billing_email();
  if (!$to) {
    return false;
  }
  $cfg = nirog_bhumi_takeaway_settings();
  $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
  if (!$name) {
    $name = 'there';
  }
  $booklet  = $cfg['booklet_url'] ?: home_url('/');
  $feedback = add_query_arg('o', $order->get_id(), $cfg['feedback_url'] ?: home_url('/consultation-feedback/'));

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

  $sent = wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
  if ($sent) {
    $order->update_meta_data('takeaway_email_sent_status', 'sent');
    $order->update_meta_data('takeaway_email_sent_at', current_time('mysql'));
    $order->save();
  }
  return (bool) $sent;
}

/** Recurring sweep: send any due, paid, not-yet-sent takeaway emails. */
function nirog_bhumi_takeaway_sweep() {
  if (!function_exists('wc_get_orders')) {
    return;
  }
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
  $now = time();
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
add_action('nirog_bhumi_takeaway_sweep', 'nirog_bhumi_takeaway_sweep');

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
