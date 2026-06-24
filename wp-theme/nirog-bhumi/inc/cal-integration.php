<?php
/**
 * Cal.com booking integration.
 *
 * Cal.com pushes a webhook for each booking to a small REST endpoint here. The
 * endpoint matches the booking to a consultation entry (by attendee email),
 * stores the consultation start/end time and the takeaway-email schedule, and
 * lets the existing 5-minute sweep send the takeaway email automatically once
 * the consultation has ended.
 *
 * Cal.com setup (one time):
 *   1. Cal.com -> Settings -> Developer -> Webhooks -> New Webhook.
 *   2. Subscriber URL:  https://YOURSITE/wp-json/nirog/v1/cal-booking
 *   3. Triggers: Booking Created, Booking Rescheduled, Booking Cancelled.
 *   4. Set a Secret, and paste the same value into
 *      WP Admin -> Settings -> Nirog Bhumi Setup -> "Cal.com webhook secret".
 */

if (!defined('ABSPATH')) {
  exit;
}

/** Public REST endpoint that Cal.com calls. Requests are verified by signature. */
function nirog_bhumi_cal_register_routes() {
  register_rest_route('nirog/v1', '/cal-booking', [
    'methods'             => 'POST',
    'callback'            => 'nirog_bhumi_cal_webhook',
    'permission_callback' => '__return_true',
  ]);
}
add_action('rest_api_init', 'nirog_bhumi_cal_register_routes');

function nirog_bhumi_cal_secret() {
  $s = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];
  return (string) ($s['cal_webhook_secret'] ?? '');
}

/** The Subscriber URL to paste into Cal.com. */
function nirog_bhumi_cal_webhook_url() {
  return rest_url('nirog/v1/cal-booking');
}

/** Latest non-anonymised consultation entry for an email, or 0. */
function nirog_bhumi_cal_find_entry($email) {
  $email = sanitize_email((string) $email);
  if (!$email) {
    return 0;
  }
  $posts = get_posts([
    'post_type'      => 'nb_consultation',
    'post_status'    => ['private', 'publish', 'draft'],
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [['key' => 'email', 'value' => $email]],
  ]);
  foreach ($posts as $p) {
    if (function_exists('nirog_bhumi_is_anonymised_record') && nirog_bhumi_is_anonymised_record($p->ID)) {
      continue;
    }
    return $p->ID;
  }
  return 0;
}

function nirog_bhumi_cal_webhook(WP_REST_Request $request) {
  $body = $request->get_body();

  // Verify Cal.com's HMAC-SHA256 signature when a secret is configured.
  $secret = nirog_bhumi_cal_secret();
  if ($secret) {
    $sig = (string) $request->get_header('x-cal-signature-256');
    $expected = hash_hmac('sha256', $body, $secret);
    if (!$sig || !hash_equals($expected, $sig)) {
      return new WP_REST_Response(['ok' => false, 'error' => 'invalid signature'], 401);
    }
  }

  $data = json_decode($body, true);
  if (!is_array($data)) {
    return new WP_REST_Response(['ok' => false, 'error' => 'bad payload'], 400);
  }
  $event = (string) ($data['triggerEvent'] ?? '');
  $p = (isset($data['payload']) && is_array($data['payload'])) ? $data['payload'] : [];

  $email = '';
  if (!empty($p['attendees'][0]['email'])) {
    $email = $p['attendees'][0]['email'];
  } elseif (!empty($p['responses']['email']['value'])) {
    $email = $p['responses']['email']['value'];
  }

  $entry_id = nirog_bhumi_cal_find_entry($email);
  if (!$entry_id) {
    // No matching consultation form on file. Acknowledge so Cal.com stops retrying.
    return new WP_REST_Response(['ok' => true, 'matched' => false], 200);
  }

  // Booking cancelled -> never send the takeaway email.
  if ($event === 'BOOKING_CANCELLED') {
    if (get_post_meta($entry_id, 'takeaway_email_sent_status', true) !== 'sent') {
      update_post_meta($entry_id, 'takeaway_email_sent_status', 'skipped');
    }
    return new WP_REST_Response(['ok' => true, 'cancelled' => true], 200);
  }

  $start_iso = (string) ($p['startTime'] ?? '');
  $end_iso   = (string) ($p['endTime'] ?? '');
  if (!$start_iso) {
    return new WP_REST_Response(['ok' => false, 'error' => 'no start time'], 400);
  }
  try {
    $tz    = wp_timezone();
    $start = (new DateTime($start_iso))->setTimezone($tz);
    $end   = $end_iso ? (new DateTime($end_iso))->setTimezone($tz) : null;
  } catch (Exception $e) {
    return new WP_REST_Response(['ok' => false, 'error' => 'bad time'], 400);
  }

  $cfg = nirog_bhumi_takeaway_settings();
  if (!$end) {
    $end = (clone $start)->modify('+' . $cfg['duration'] . ' minutes');
  }
  $send = (clone $end)->modify('+' . $cfg['delay'] . ' minutes');

  update_post_meta($entry_id, 'slot_date', $start->format('Y-m-d'));
  update_post_meta($entry_id, 'slot_time', $start->format('H:i'));
  update_post_meta($entry_id, 'consultation_start_time', $start->format('Y-m-d H:i:s'));
  update_post_meta($entry_id, 'consultation_end_time', $end->format('Y-m-d H:i:s'));
  update_post_meta($entry_id, 'takeaway_email_scheduled_time', $send->format('Y-m-d H:i:s'));
  update_post_meta($entry_id, 'takeaway_source', 'cal');
  if (!empty($p['uid'])) {
    update_post_meta($entry_id, 'cal_booking_uid', sanitize_text_field($p['uid']));
  }
  // (Re)arm the email unless it has already gone out.
  if (get_post_meta($entry_id, 'takeaway_email_sent_status', true) !== 'sent') {
    update_post_meta($entry_id, 'takeaway_email_sent_status', 'pending');
  }

  return new WP_REST_Response([
    'ok'        => true,
    'entry'     => $entry_id,
    'scheduled' => $send->format('Y-m-d H:i:s'),
  ], 200);
}
