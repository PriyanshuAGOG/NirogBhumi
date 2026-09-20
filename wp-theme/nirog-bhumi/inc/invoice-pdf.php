<?php
/**
 * Server-generated consultation invoices for Nirog Bhumi.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Format a date/time string that is already stored as a local wall-clock
 * value in the site's own timezone (e.g. slot_date, slot_time, or anything
 * written with current_time('mysql')) for display, in that same timezone.
 *
 * The bug this avoids: strtotime($string) parses using PHP's SERVER default
 * timezone (often UTC on most hosts), while wp_date() then formats using the
 * WordPress site timezone. If those two differ, the combination silently
 * shifts the displayed time - a stored "14:30" (meant as 2:30 PM local time)
 * can render as a completely different hour. Parsing with wp_timezone() from
 * the start, the same timezone used everywhere else this data is written and
 * read, keeps the round trip correct regardless of the PHP server's default
 * timezone. Defined here because inc/invoice-pdf.php is the first file
 * required by functions.php, so this helper is available everywhere.
 */
function nirog_bhumi_local_date($format, $date_string) {
  $date_string = trim((string) $date_string);
  if ($date_string === '') {
    return '';
  }
  $dt = date_create($date_string, wp_timezone());
  if (!$dt) {
    return '';
  }
  return wp_date($format, $dt->getTimestamp(), wp_timezone());
}

function nirog_bhumi_invoice_ascii($value) {
  $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
  $value = str_replace(['₹', '–', '—', '•', '·'], ['Rs.', '-', '-', '-', '-'], $value);
  if (function_exists('iconv')) {
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) $value = $converted;
  }
  return preg_replace('/[^\x20-\x7E]/', '', $value);
}

function nirog_bhumi_pdf_escape($value) {
  return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], nirog_bhumi_invoice_ascii($value));
}

function nirog_bhumi_pdf_text($x, $top, $size, $text, $font = 'F1', $colour = '0.094 0.133 0.098') {
  $y = 842 - $top;
  return sprintf("BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $font, $size, $colour, $x, $y, nirog_bhumi_pdf_escape($text));
}

function nirog_bhumi_pdf_line($x1, $top1, $x2, $top2, $width = 1, $colour = '0.188 0.286 0.212') {
  return sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $colour, $width, $x1, 842 - $top1, $x2, 842 - $top2);
}

function nirog_bhumi_pdf_rect($x, $top, $width, $height, $fill = '1 1 1', $stroke = '0.847 0.816 0.753') {
  $y = 842 - $top - $height;
  return sprintf("%s rg %s RG %.2F %.2F %.2F %.2F re B\n", $fill, $stroke, $x, $y, $width, $height);
}

function nirog_bhumi_pdf_wrap($text, $max_chars) {
  $words = preg_split('/\s+/', trim(nirog_bhumi_invoice_ascii($text)));
  $lines = [];
  $line = '';
  foreach ($words as $word) {
    $candidate = $line === '' ? $word : $line . ' ' . $word;
    if (strlen($candidate) > $max_chars && $line !== '') {
      $lines[] = $line;
      $line = $word;
    } else {
      $line = $candidate;
    }
  }
  if ($line !== '') $lines[] = $line;
  return $lines ?: [''];
}

function nirog_bhumi_number_under_thousand($number) {
  $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
  $number = (int) $number;
  $parts = [];
  if ($number >= 100) {
    $parts[] = $ones[(int) floor($number / 100)] . ' Hundred';
    $number %= 100;
  }
  if ($number >= 20) {
    $parts[] = $tens[(int) floor($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
  } elseif ($number > 0) {
    $parts[] = $ones[$number];
  }
  return implode(' ', $parts);
}

function nirog_bhumi_amount_in_words($amount) {
  $rupees = (int) floor($amount);
  $paise = (int) round(($amount - $rupees) * 100);
  if ($rupees === 0) return 'Indian Rupees Zero Only';
  $parts = [];
  $groups = [10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'];
  foreach ($groups as $value => $label) {
    if ($rupees >= $value) {
      $count = (int) floor($rupees / $value);
      $parts[] = nirog_bhumi_number_under_thousand($count) . ' ' . $label;
      $rupees %= $value;
    }
  }
  if ($rupees) $parts[] = nirog_bhumi_number_under_thousand($rupees);
  $words = 'Indian Rupees ' . implode(' ', $parts);
  if ($paise) $words .= ' and ' . nirog_bhumi_number_under_thousand($paise) . ' Paise';
  return $words . ' Only';
}

/**
 * WooCommerce stores an Indian order's state as the 2-letter ISO-3166-2
 * code (e.g. "RJ", "MH", "DL") from its own country/state list, not the
 * 2-digit numeric GST state code an invoice needs. This is the fixed,
 * publicly published CBIC state-code list, not a business judgement call.
 */
function nirog_bhumi_india_gst_state_codes() {
  return [
    'JK' => '01', 'HP' => '02', 'PB' => '03', 'CH' => '04', 'UT' => '05',
    'HR' => '06', 'DL' => '07', 'RJ' => '08', 'UP' => '09', 'BR' => '10',
    'SK' => '11', 'AR' => '12', 'NL' => '13', 'MN' => '14', 'MZ' => '15',
    'TR' => '16', 'ML' => '17', 'AS' => '18', 'WB' => '19', 'JH' => '20',
    'OR' => '21', 'CT' => '22', 'MP' => '23', 'GJ' => '24', 'DN' => '26',
    'DD' => '26', 'MH' => '27', 'KA' => '29', 'GA' => '30', 'LD' => '31',
    'KL' => '32', 'TN' => '33', 'PY' => '34', 'AN' => '35', 'TG' => '36',
    'AP' => '37',
  ];
}

/**
 * Add N business days (Mon-Fri) to a timestamp - used for the estimated
 * delivery date on an order invoice.
 */
function nirog_bhumi_add_business_days($timestamp, $days) {
  $date = new DateTime('@' . $timestamp);
  $date->setTimezone(wp_timezone());
  $added = 0;
  while ($added < $days) {
    $date->modify('+1 day');
    if ((int) $date->format('N') < 6) {
      $added++;
    }
  }
  return $date->getTimestamp();
}

function nirog_bhumi_consultation_invoice_data($post_id) {
  $settings = nirog_bhumi_get_settings();
  $base = 500.00;
  $rate = max(0, min(100, (float) ($settings['invoice_gst_rate'] ?? 18)));
  $tax = round($base * $rate / 100, 2);
  $state_code = preg_replace('/\D/', '', (string) get_post_meta($post_id, 'billing_state_code', true));
  $supplier_code = preg_replace('/\D/', '', (string) ($settings['invoice_state_code'] ?? '08'));
  $country = get_post_meta($post_id, 'billing_country', true) ?: 'India';
  $intra = strcasecmp($country, 'India') === 0 && str_pad($state_code ?: $supplier_code, 2, '0', STR_PAD_LEFT) === str_pad($supplier_code, 2, '0', STR_PAD_LEFT);
  $cgst = $intra ? round($tax / 2, 2) : 0;
  $sgst = $intra ? $tax - $cgst : 0;
  $igst = $intra ? 0 : $tax;
  $verified = get_post_meta($post_id, 'payment_verified_at', true);
  $invoice_date = $verified ? nirog_bhumi_local_date('d M Y', $verified) : wp_date('d M Y');
  return [
    'invoice_number' => get_post_meta($post_id, 'invoice_number', true),
    'invoice_date' => $invoice_date,
    'type' => $intra ? 'Intra-state - CGST + SGST' : 'Inter-state - IGST',
    'name' => get_post_meta($post_id, 'name', true),
    'email' => get_post_meta($post_id, 'email', true),
    'phone' => trim(get_post_meta($post_id, 'country_code', true) . ' ' . get_post_meta($post_id, 'phone', true)),
    'address' => get_post_meta($post_id, 'billing_address', true),
    'city' => get_post_meta($post_id, 'billing_city', true),
    'state' => get_post_meta($post_id, 'billing_state', true) ?: ($settings['invoice_state'] ?? 'Rajasthan'),
    'state_code' => str_pad($state_code ?: $supplier_code, 2, '0', STR_PAD_LEFT),
    'postcode' => get_post_meta($post_id, 'billing_postcode', true),
    'country' => $country,
    'customer_gstin' => get_post_meta($post_id, 'customer_gstin', true),
    'legal_name' => $settings['invoice_legal_name'],
    'business_address' => $settings['invoice_address'],
    'business_gstin' => $settings['invoice_gstin'],
    'business_cin' => $settings['invoice_cin'] ?? '',
    'business_email' => $settings['invoice_email'],
    'business_phone' => $settings['invoice_phone'],
    'business_state' => $settings['invoice_state'] ?? 'Rajasthan',
    'business_state_code' => $supplier_code,
    'sac' => $settings['invoice_sac'] ?: '999319',
    'rate' => $rate,
    'base' => $base,
    'cgst' => $cgst,
    'sgst' => $sgst,
    'igst' => $igst,
    'tax' => $tax,
    'total' => $base + $tax,
    'intra' => $intra,
  ];
}

function nirog_bhumi_render_invoice_pdf($data) {
  $green = '0.188 0.286 0.212';
  $ink = '0.094 0.133 0.098';
  $muted = '0.38 0.43 0.38';
  $paper = '0.98 0.972 0.94';
  $logo_path = get_template_directory() . '/assets/img/invoice-logo.jpg';
  $logo_bytes = is_readable($logo_path) ? file_get_contents($logo_path) : '';
  $logo_size = $logo_bytes ? getimagesize($logo_path) : false;
  $ops = "1 1 1 rg 0 0 595 842 re f\n";
  $ops .= nirog_bhumi_pdf_line(38, 36, 557, 36, 3, $green);
  if ($logo_bytes && $logo_size) {
    $logo_width = 174;
    $logo_height = $logo_width * ($logo_size[1] / $logo_size[0]);
    $logo_y = 842 - 49 - $logo_height;
    $ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $logo_width, $logo_height, 40, $logo_y);
  } else {
    $ops .= nirog_bhumi_pdf_text(40, 70, 23, 'NIROG BHUMI', 'F3', $green);
  }
  $ops .= nirog_bhumi_pdf_text(410, 68, 21, 'TAX INVOICE', 'F3', $ink);
  $ops .= nirog_bhumi_pdf_line(38, 112, 557, 112, .6, '0.82 0.79 0.72');

  $ops .= nirog_bhumi_pdf_text(40, 132, 7.5, 'REGISTERED OFFICE', 'F2', $muted);
  $top = 148;
  $ops .= nirog_bhumi_pdf_text(40, $top, 9, $data['legal_name'], 'F2', $ink);
  $top += 13;
  foreach (nirog_bhumi_pdf_wrap($data['business_address'], 76) as $line) {
    $ops .= nirog_bhumi_pdf_text(40, $top, 8.5, $line, 'F1', $ink);
    $top += 12;
  }
  $ops .= nirog_bhumi_pdf_text(40, $top + 1, 8.5, $data['business_email'] ?: 'priyanshu@nirogbhumi.com', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(405, 132, 8, 'GSTIN', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 132, 8.5, $data['business_gstin'], 'F2', $ink);
  if ($data['business_cin']) {
    $ops .= nirog_bhumi_pdf_text(405, 148, 8, 'CIN', 'F2', $muted);
    $ops .= nirog_bhumi_pdf_text(462, 148, 8.5, $data['business_cin'], 'F1', $ink);
  }
  $ops .= nirog_bhumi_pdf_text(405, 164, 8, 'STATE', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 164, 8.5, $data['business_state'] . ' (' . $data['business_state_code'] . ')', 'F1', $ink);

  $location_lines = [];
  foreach (nirog_bhumi_pdf_wrap($data['address'], 49) as $line) {
    if ($line !== '') $location_lines[] = $line;
  }
  $city_line = trim(trim($data['city'] . ', ' . $data['state'] . ' - ' . $data['postcode']), ', -');
  foreach (nirog_bhumi_pdf_wrap($city_line, 49) as $line) {
    if ($line !== '') $location_lines[] = $line;
  }
  $location_lines = array_slice($location_lines, 0, 2);
  $bill_detail = $location_lines;
  if ($data['phone'] !== '') $bill_detail[] = 'Phone: ' . $data['phone'];
  if ($data['email'] !== '') $bill_detail[] = 'Email: ' . $data['email'];
  $bill_detail[] = 'GSTIN: ' . $data['customer_gstin'];

  $bill_box_top = 194;
  $bill_rows_top = 248;
  $bill_step = 12;
  $bill_box_height = ($bill_rows_top - $bill_box_top) + (count($bill_detail) * $bill_step) + 8;
  $ops .= nirog_bhumi_pdf_rect(38, $bill_box_top, 326, $bill_box_height, $paper);
  $ops .= nirog_bhumi_pdf_text(52, 214, 7.5, 'BILLED TO', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(52, 232, 10, $data['name'], 'F2', $ink);
  $bill_top = $bill_rows_top;
  foreach ($bill_detail as $line) {
    $ops .= nirog_bhumi_pdf_text(52, $bill_top, 8.5, $line, 'F1', $ink);
    $bill_top += $bill_step;
  }
  $ops .= nirog_bhumi_pdf_text(190, 214, 7.5, 'PLACE OF SUPPLY', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(190, 232, 9, $data['state'] . ' (' . $data['state_code'] . ')', 'F2', $ink);

  $ops .= nirog_bhumi_pdf_text(390, 208, 8, 'Invoice No.', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 208, 9, $data['invoice_number'], 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 228, 8, 'Invoice Date', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 228, 9, $data['invoice_date'], 'F1', $ink);

  $ops .= nirog_bhumi_pdf_rect(38, 330, 519, 30, '0.933 0.91 0.85');
  $headers = [[46, '#'], [70, 'DESCRIPTION'], [310, 'HSN/SAC'], [374, 'QTY'], [420, 'RATE'], [486, 'AMOUNT']];
  foreach ($headers as [$x, $label]) $ops .= nirog_bhumi_pdf_text($x, 349, 7.5, $label, 'F2', $green);
  $ops .= nirog_bhumi_pdf_text(47, 386, 9, '1', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(70, 382, 10, '30-minute consultation with Gautam Khandelwal', 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(70, 397, 8, 'Consultation for Diabetes reversal', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(310, 386, 9, $data['sac'], 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(380, 386, 9, '1', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(420, 386, 9, number_format($data['base'], 2), 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(486, 386, 9, number_format($data['base'], 2), 'F2', $ink);
  $ops .= nirog_bhumi_pdf_line(38, 414, 557, 414, .5, '0.88 0.86 0.81');

  $ops .= nirog_bhumi_pdf_text(42, 445, 8, 'TAX SUMMARY', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(42, 466, 8.5, number_format($data['rate'], 2) . '%', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(100, 466, 8.5, 'Taxable: Rs. ' . number_format($data['base'], 2), 'F1', $ink);
  if ($data['intra']) {
    $ops .= nirog_bhumi_pdf_text(100, 484, 8.5, 'CGST: Rs. ' . number_format($data['cgst'], 2), 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(220, 484, 8.5, 'SGST: Rs. ' . number_format($data['sgst'], 2), 'F1', $ink);
  } else {
    $ops .= nirog_bhumi_pdf_text(100, 484, 8.5, 'IGST: Rs. ' . number_format($data['igst'], 2), 'F1', $ink);
  }

  $ops .= nirog_bhumi_pdf_text(380, 448, 9, 'Taxable Value', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(494, 448, 9, 'Rs. ' . number_format($data['base'], 2), 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(380, 468, 9, $data['intra'] ? 'CGST + SGST' : 'IGST', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(494, 468, 9, 'Rs. ' . number_format($data['tax'], 2), 'F2', $ink);
  $ops .= nirog_bhumi_pdf_rect(368, 492, 189, 42, $green, $green);
  $ops .= nirog_bhumi_pdf_text(382, 518, 10, 'TOTAL PAID', 'F2', '1 1 1');
  $ops .= nirog_bhumi_pdf_text(476, 518, 13, 'Rs. ' . number_format($data['total'], 2), 'F2', '1 1 1');

  $ops .= nirog_bhumi_pdf_rect(38, 560, 519, 42, '0.933 0.91 0.85', '0.933 0.91 0.85');
  $ops .= nirog_bhumi_pdf_text(52, 585, 7.5, 'AMOUNT IN WORDS', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(150, 585, 9, nirog_bhumi_amount_in_words($data['total']), 'F3', $ink);

  $ops .= nirog_bhumi_pdf_line(38, 650, 557, 650, .6, '0.82 0.79 0.72');
  $ops .= nirog_bhumi_pdf_text(40, 674, 8, 'DECLARATION', 'F2', $muted);
  foreach (nirog_bhumi_pdf_wrap('We declare that this invoice shows the actual price of the service described and that all particulars are true and correct.', 92) as $index => $line) {
    $ops .= nirog_bhumi_pdf_text(40, 692 + ($index * 12), 8, $line, 'F1', $muted);
  }
  $ops .= nirog_bhumi_pdf_text(370, 674, 9, 'For Nirog Bhumi Pvt. Ltd.', 'F2', $green);
  $ops .= nirog_bhumi_pdf_text(370, 706, 8, 'Authorised signatory', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(272, 780, 8, 'nirogbhumi.com', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(223, 794, 8, 'This is a computer-generated invoice.', 'F1', $muted);

  $objects = [];
  $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
  $image_resource = ($logo_bytes && $logo_size) ? ' /XObject << /Im1 8 0 R >>' : '';
  $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R >>' . $image_resource . ' >> /Contents 4 0 R >>';
  $objects[4] = '<< /Length ' . strlen($ops) . ">>\nstream\n" . $ops . "endstream";
  $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
  $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
  $objects[7] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>';
  if ($logo_bytes && $logo_size) {
    $objects[8] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $logo_size[0] . ' /Height ' . (int) $logo_size[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo_bytes) . ">>\nstream\n" . $logo_bytes . "\nendstream";
  }
  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $number => $object) {
    $offsets[$number] = strlen($pdf);
    $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
  }
  $xref = strlen($pdf);
  $object_count = count($objects);
  $pdf .= "xref\n0 " . ($object_count + 1) . "\n0000000000 65535 f \n";
  for ($i = 1; $i <= $object_count; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
  $pdf .= "trailer\n<< /Size " . ($object_count + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
  return $pdf;
}

/**
 * Gather everything a store order's tax invoice needs: line items with
 * their HSN/GST (stamped onto each order line at checkout - see
 * nirog_bhumi_store_order_line_tax_meta() in inc/store.php), the shipping
 * fee, CGST/SGST vs IGST based on the buyer's billing state against the
 * business's, and a 14-working-day delivery estimate from the payment
 * date. Every amount on the store's product pages is GST-inclusive (see
 * nirog_bhumi_store_price_plus_gst_html()), so the taxable value here is
 * always backed out of the line total rather than added on top of it.
 */
function nirog_bhumi_order_invoice_data($order_id) {
  if (!function_exists('wc_get_order')) {
    return null;
  }
  $order = wc_get_order($order_id);
  if (!$order) {
    return null;
  }
  $settings = nirog_bhumi_get_settings();
  $store_settings = function_exists('nirog_bhumi_get_store_settings') ? nirog_bhumi_get_store_settings() : [];
  $supplier_code = preg_replace('/\D/', '', (string) ($settings['invoice_state_code'] ?? '08'));
  $default_rate = isset($store_settings['goods_gst_rate']) && '' !== $store_settings['goods_gst_rate']
    ? (float) $store_settings['goods_gst_rate']
    : 0.0;

  $country = $order->get_billing_country() ?: 'IN';
  $wc_state = strtoupper((string) $order->get_billing_state());
  $state_codes = nirog_bhumi_india_gst_state_codes();
  $state_code = $state_codes[$wc_state] ?? $supplier_code;
  $intra = in_array(strtoupper($country), ['IN', 'INDIA'], true)
    && str_pad($state_code, 2, '0', STR_PAD_LEFT) === str_pad($supplier_code, 2, '0', STR_PAD_LEFT);

  $state_name = $wc_state;
  if (function_exists('WC') && WC()->countries) {
    $states = WC()->countries->get_states($country);
    if (isset($states[$wc_state])) {
      $state_name = $states[$wc_state];
    }
  }

  $items = [];
  $rate_groups = [];
  $row_number = 0;

  $add_row = function ($name, $hsn, $qty, $line_total, $rate) use (&$items, &$rate_groups, &$row_number) {
    $row_number++;
    $rate = (float) $rate;
    $taxable = $rate > 0 ? round($line_total / (1 + $rate / 100), 2) : round($line_total, 2);
    $tax = round($line_total - $taxable, 2);
    $items[] = [
      'no' => $row_number,
      'name' => $name,
      'hsn' => $hsn,
      'qty' => $qty,
      'unit_rate' => $qty > 0 ? round($taxable / $qty, 2) : $taxable,
      'taxable' => $taxable,
      'tax' => $tax,
      'total' => round($line_total, 2),
      'gst_rate' => $rate,
    ];
    if (!isset($rate_groups[$rate])) {
      $rate_groups[$rate] = ['taxable' => 0.0, 'tax' => 0.0];
    }
    $rate_groups[$rate]['taxable'] += $taxable;
    $rate_groups[$rate]['tax'] += $tax;
  };

  foreach ($order->get_items() as $item) {
    $hsn = (string) $item->get_meta('_nb_hsn');
    $rate = (string) $item->get_meta('_nb_gst_rate');
    $rate = '' !== $rate ? (float) $rate : $default_rate;
    $add_row($item->get_name(), $hsn, (int) $item->get_quantity(), (float) $item->get_total(), $rate);
  }

  // Shipping is a flat, GST-inclusive charge, not a catalogue product - it
  // is never listed as a numbered line item alongside the goods, and its
  // amount is shown as-is rather than backed out into a taxable value and
  // a tax portion the way each product line is.
  $shipping_total = 0.0;
  foreach ($order->get_fees() as $fee) {
    $shipping_total += (float) $fee->get_total();
  }

  $tax_summary = [];
  foreach ($rate_groups as $rate => $group) {
    $cgst = $intra ? round($group['tax'] / 2, 2) : 0.0;
    $sgst = $intra ? round($group['tax'] - $cgst, 2) : 0.0;
    $igst = $intra ? 0.0 : round($group['tax'], 2);
    $tax_summary[] = [
      'rate' => (float) $rate,
      'taxable' => round($group['taxable'], 2),
      'cgst' => $cgst,
      'sgst' => $sgst,
      'igst' => $igst,
      'tax' => round($group['tax'], 2),
    ];
  }

  $paid_date = $order->get_date_paid();
  $invoice_timestamp = $paid_date ? $paid_date->getTimestamp() : time();
  $delivery_timestamp = nirog_bhumi_add_business_days($invoice_timestamp, 14);

  $address_lines = array_values(array_filter([$order->get_billing_address_1(), $order->get_billing_address_2()]));
  $name = trim($order->get_formatted_billing_full_name());
  if (!$name) {
    $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
  }

  $tax_total = array_sum(array_column($items, 'tax'));

  return [
    'invoice_number' => (string) $order->get_meta('_nb_invoice_number'),
    'invoice_date' => wp_date('d M Y', $invoice_timestamp, wp_timezone()),
    'delivery_estimate' => wp_date('d M Y', $delivery_timestamp, wp_timezone()),
    'order_number' => $order->get_order_number(),
    'name' => $name,
    'email' => $order->get_billing_email(),
    'phone' => $order->get_billing_phone(),
    'address' => implode(', ', $address_lines),
    'city' => $order->get_billing_city(),
    'state' => $state_name ?: ($settings['invoice_state'] ?? 'Rajasthan'),
    'state_code' => str_pad($state_code, 2, '0', STR_PAD_LEFT),
    'postcode' => $order->get_billing_postcode(),
    'country' => $country,
    'customer_gstin' => (string) $order->get_meta('_nb_customer_gstin'),
    'legal_name' => $settings['invoice_legal_name'],
    'business_address' => $settings['invoice_address'],
    'business_gstin' => $settings['invoice_gstin'],
    'business_cin' => $settings['invoice_cin'] ?? '',
    'business_email' => $settings['invoice_email'],
    'business_phone' => $settings['invoice_phone'],
    'business_state' => $settings['invoice_state'] ?? 'Rajasthan',
    'business_state_code' => $supplier_code,
    'items' => $items,
    'tax_summary' => $tax_summary,
    'tax_total' => round($tax_total, 2),
    'shipping_total' => round($shipping_total, 2),
    'total' => (float) $order->get_total(),
    'intra' => $intra,
  ];
}

function nirog_bhumi_render_order_invoice_pdf($data) {
  $green = '0.188 0.286 0.212';
  $ink = '0.094 0.133 0.098';
  $muted = '0.38 0.43 0.38';
  $paper = '0.98 0.972 0.94';
  $logo_path = get_template_directory() . '/assets/img/invoice-logo.jpg';
  $logo_bytes = is_readable($logo_path) ? file_get_contents($logo_path) : '';
  $logo_size = $logo_bytes ? getimagesize($logo_path) : false;
  $ops = "1 1 1 rg 0 0 595 842 re f\n";
  $ops .= nirog_bhumi_pdf_line(38, 36, 557, 36, 3, $green);
  if ($logo_bytes && $logo_size) {
    $logo_width = 174;
    $logo_height = $logo_width * ($logo_size[1] / $logo_size[0]);
    $logo_y = 842 - 49 - $logo_height;
    $ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $logo_width, $logo_height, 40, $logo_y);
  } else {
    $ops .= nirog_bhumi_pdf_text(40, 70, 23, 'NIROG BHUMI', 'F3', $green);
  }
  $ops .= nirog_bhumi_pdf_text(410, 68, 21, 'TAX INVOICE', 'F3', $ink);
  $ops .= nirog_bhumi_pdf_line(38, 112, 557, 112, .6, '0.82 0.79 0.72');

  $ops .= nirog_bhumi_pdf_text(40, 132, 7.5, 'REGISTERED OFFICE', 'F2', $muted);
  $top = 148;
  $ops .= nirog_bhumi_pdf_text(40, $top, 9, $data['legal_name'], 'F2', $ink);
  $top += 13;
  foreach (nirog_bhumi_pdf_wrap($data['business_address'], 76) as $line) {
    $ops .= nirog_bhumi_pdf_text(40, $top, 8.5, $line, 'F1', $ink);
    $top += 12;
  }
  $ops .= nirog_bhumi_pdf_text(40, $top + 1, 8.5, $data['business_email'] ?: 'priyanshu@nirogbhumi.com', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(405, 132, 8, 'GSTIN', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 132, 8.5, $data['business_gstin'], 'F2', $ink);
  if ($data['business_cin']) {
    $ops .= nirog_bhumi_pdf_text(405, 148, 8, 'CIN', 'F2', $muted);
    $ops .= nirog_bhumi_pdf_text(462, 148, 8.5, $data['business_cin'], 'F1', $ink);
  }
  $ops .= nirog_bhumi_pdf_text(405, 164, 8, 'STATE', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 164, 8.5, $data['business_state'] . ' (' . $data['business_state_code'] . ')', 'F1', $ink);

  $location_lines = [];
  foreach (nirog_bhumi_pdf_wrap($data['address'], 49) as $line) {
    if ($line !== '') $location_lines[] = $line;
  }
  $city_line = trim(trim($data['city'] . ', ' . $data['state'] . ' - ' . $data['postcode']), ', -');
  foreach (nirog_bhumi_pdf_wrap($city_line, 49) as $line) {
    if ($line !== '') $location_lines[] = $line;
  }
  $location_lines = array_slice($location_lines, 0, 2);
  $bill_detail = $location_lines;
  if ($data['phone'] !== '') $bill_detail[] = 'Phone: ' . $data['phone'];
  if ($data['email'] !== '') $bill_detail[] = 'Email: ' . $data['email'];
  if ($data['customer_gstin'] !== '') $bill_detail[] = 'GSTIN: ' . $data['customer_gstin'];

  $bill_box_top = 194;
  $bill_rows_top = 248;
  $bill_step = 12;
  $bill_box_height = ($bill_rows_top - $bill_box_top) + (count($bill_detail) * $bill_step) + 8;
  $ops .= nirog_bhumi_pdf_rect(38, $bill_box_top, 326, $bill_box_height, $paper);
  $ops .= nirog_bhumi_pdf_text(52, 214, 7.5, 'BILLED TO', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(52, 232, 10, $data['name'], 'F2', $ink);
  $bill_top = $bill_rows_top;
  foreach ($bill_detail as $line) {
    $ops .= nirog_bhumi_pdf_text(52, $bill_top, 8.5, $line, 'F1', $ink);
    $bill_top += $bill_step;
  }
  $ops .= nirog_bhumi_pdf_text(190, 214, 7.5, 'PLACE OF SUPPLY', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(190, 232, 9, $data['state'] . ' (' . $data['state_code'] . ')', 'F2', $ink);

  $ops .= nirog_bhumi_pdf_text(390, 200, 8, 'Invoice No.', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 200, 9, $data['invoice_number'], 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 216, 8, 'Invoice Date', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 216, 9, $data['invoice_date'], 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 232, 8, 'Order No.', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 232, 9, '#' . $data['order_number'], 'F1', $ink);

  // Line items table - a dynamic number of rows (products plus, when
  // charged, a shipping row), each a single compact line since every
  // product name in the catalogue is short enough not to need wrapping.
  $ops .= nirog_bhumi_pdf_rect(38, 330, 519, 30, '0.933 0.91 0.85');
  $headers = [[46, '#'], [70, 'DESCRIPTION'], [318, 'HSN'], [366, 'QTY'], [400, 'RATE'], [486, 'AMOUNT']];
  foreach ($headers as [$x, $label]) $ops .= nirog_bhumi_pdf_text($x, 349, 7.5, $label, 'F2', $green);

  $row_height = 16;
  $rows_top = 372;
  foreach ($data['items'] as $item) {
    $y = $rows_top + (($item['no'] - 1) * $row_height);
    $ops .= nirog_bhumi_pdf_text(47, $y, 8.5, (string) $item['no'], 'F1', $ink);
    foreach (nirog_bhumi_pdf_wrap($item['name'], 38) as $line_index => $name_line) {
      $ops .= nirog_bhumi_pdf_text(70, $y + ($line_index * 10), 8.5, $name_line, 'F2', $ink);
    }
    $ops .= nirog_bhumi_pdf_text(318, $y, 8, $item['hsn'] ?: '-', 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(370, $y, 8, (string) $item['qty'], 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(400, $y, 8, number_format($item['unit_rate'], 2), 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(486, $y, 8.5, number_format($item['taxable'], 2), 'F2', $ink);
  }

  $item_count = max(1, count($data['items']));
  $divider_top = $rows_top + ($item_count * $row_height) + 10;
  $ops .= nirog_bhumi_pdf_line(38, $divider_top, 557, $divider_top, .5, '0.88 0.86 0.81');

  $ops .= nirog_bhumi_pdf_text(42, $divider_top + 20, 8, 'TAX SUMMARY', 'F2', $muted);
  $summary_y = $divider_top + 38;
  foreach (array_slice($data['tax_summary'], 0, 3) as $group) {
    $ops .= nirog_bhumi_pdf_text(42, $summary_y, 8.5, number_format($group['rate'], 2) . '%', 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(100, $summary_y, 8.5, 'Taxable: Rs. ' . number_format($group['taxable'], 2), 'F1', $ink);
    if ($data['intra']) {
      $ops .= nirog_bhumi_pdf_text(220, $summary_y, 8.5, 'CGST: Rs. ' . number_format($group['cgst'], 2), 'F1', $ink);
      $ops .= nirog_bhumi_pdf_text(340, $summary_y, 8.5, 'SGST: Rs. ' . number_format($group['sgst'], 2), 'F1', $ink);
    } else {
      $ops .= nirog_bhumi_pdf_text(220, $summary_y, 8.5, 'IGST: Rs. ' . number_format($group['igst'], 2), 'F1', $ink);
    }
    $summary_y += 16;
  }

  // Starts only once the left-hand tax-summary rows above are done, so a
  // second (or third) tax-rate group can never run into this block - the
  // two used to sit at fixed offsets from the divider and collided as soon
  // as the left column had more than one line.
  $totals_top = max($divider_top + 24, $summary_y + 8);
  $subtotal = array_sum(array_column($data['items'], 'taxable'));
  $ops .= nirog_bhumi_pdf_text(380, $totals_top, 9, 'Taxable Value', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(494, $totals_top, 9, 'Rs. ' . number_format($subtotal, 2), 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(380, $totals_top + 20, 9, $data['intra'] ? 'CGST + SGST' : 'IGST', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(494, $totals_top + 20, 9, 'Rs. ' . number_format($data['tax_total'], 2), 'F2', $ink);
  // Shipping is a flat, GST-inclusive charge added on top of the goods -
  // shown here as its own line, not folded into the taxable value or the
  // GST breakdown above, since it is not a catalogue product.
  $has_shipping = $data['shipping_total'] > 0;
  if ($has_shipping) {
    $ops .= nirog_bhumi_pdf_text(380, $totals_top + 40, 9, 'Shipping (incl. GST)', 'F1', $ink);
    $ops .= nirog_bhumi_pdf_text(494, $totals_top + 40, 9, 'Rs. ' . number_format($data['shipping_total'], 2), 'F2', $ink);
  }
  $total_box_top = $totals_top + ($has_shipping ? 64 : 44);
  $ops .= nirog_bhumi_pdf_rect(368, $total_box_top, 189, 38, $green, $green);
  $ops .= nirog_bhumi_pdf_text(382, $total_box_top + 24, 10, 'TOTAL PAID', 'F2', '1 1 1');
  $ops .= nirog_bhumi_pdf_text(476, $total_box_top + 24, 13, 'Rs. ' . number_format($data['total'], 2), 'F2', '1 1 1');

  $words_top = $total_box_top + 50;
  $ops .= nirog_bhumi_pdf_rect(38, $words_top, 519, 34, '0.933 0.91 0.85', '0.933 0.91 0.85');
  $ops .= nirog_bhumi_pdf_text(52, $words_top + 20, 7.5, 'AMOUNT IN WORDS', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(150, $words_top + 20, 9, nirog_bhumi_amount_in_words($data['total']), 'F3', $ink);

  $delivery_top = $words_top + 56;
  $ops .= nirog_bhumi_pdf_text(40, $delivery_top, 8.5, 'ESTIMATED DELIVERY', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(150, $delivery_top, 9, 'Within 14 working days of payment - by ' . $data['delivery_estimate'], 'F2', $ink);

  $footer_divider_top = max(650, $delivery_top + 22);
  $ops .= nirog_bhumi_pdf_line(38, $footer_divider_top, 557, $footer_divider_top, .6, '0.82 0.79 0.72');
  $declaration_top = $footer_divider_top + 24;
  $ops .= nirog_bhumi_pdf_text(40, $declaration_top, 8, 'DECLARATION', 'F2', $muted);
  foreach (nirog_bhumi_pdf_wrap('We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.', 92) as $index => $line) {
    $ops .= nirog_bhumi_pdf_text(40, $declaration_top + 18 + ($index * 12), 8, $line, 'F1', $muted);
  }
  $ops .= nirog_bhumi_pdf_text(370, $declaration_top, 9, 'For Nirog Bhumi Pvt. Ltd.', 'F2', $green);
  $ops .= nirog_bhumi_pdf_text(370, $declaration_top + 32, 8, 'Authorised signatory', 'F1', $muted);
  $footer_top = max(780, $declaration_top + 60);
  $ops .= nirog_bhumi_pdf_text(272, $footer_top, 8, 'nirogbhumi.com', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(223, $footer_top + 14, 8, 'This is a computer-generated invoice.', 'F1', $muted);

  $objects = [];
  $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
  $image_resource = ($logo_bytes && $logo_size) ? ' /XObject << /Im1 8 0 R >>' : '';
  $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R >>' . $image_resource . ' >> /Contents 4 0 R >>';
  $objects[4] = '<< /Length ' . strlen($ops) . ">>\nstream\n" . $ops . "endstream";
  $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
  $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
  $objects[7] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>';
  if ($logo_bytes && $logo_size) {
    $objects[8] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $logo_size[0] . ' /Height ' . (int) $logo_size[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo_bytes) . ">>\nstream\n" . $logo_bytes . "\nendstream";
  }
  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $number => $object) {
    $offsets[$number] = strlen($pdf);
    $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
  }
  $xref = strlen($pdf);
  $object_count = count($objects);
  $pdf .= "xref\n0 " . ($object_count + 1) . "\n0000000000 65535 f \n";
  for ($i = 1; $i <= $object_count; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
  $pdf .= "trailer\n<< /Size " . ($object_count + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
  return $pdf;
}

/**
 * True once WooCommerce has actually confirmed payment on the order (not
 * merely created it) - the same is_paid() gate the consultation invoice
 * uses, so an order invoice is never generated for an unpaid or later-
 * cancelled order.
 */
function nirog_bhumi_create_order_invoice_pdf($order_id, $force = false) {
  if (!function_exists('wc_get_order')) {
    return '';
  }
  $order = wc_get_order($order_id);
  if (!$order || !$order->is_paid()) {
    return '';
  }
  $invoice_number = (string) $order->get_meta('_nb_invoice_number');
  if (!$invoice_number) {
    return '';
  }
  $uploads = wp_upload_dir();
  if (!empty($uploads['error'])) {
    return '';
  }
  $directory = trailingslashit($uploads['basedir']) . 'nirog-private-invoices';
  if (!wp_mkdir_p($directory)) {
    return '';
  }
  if (!file_exists($directory . '/index.php')) file_put_contents($directory . '/index.php', "<?php http_response_code(404); exit;\n");
  if (!file_exists($directory . '/.htaccess')) file_put_contents($directory . '/.htaccess', "Deny from all\n");
  $filename = hash('sha256', wp_salt('auth') . '|order|' . $order_id . '|' . $invoice_number) . '.pdf';
  $path = trailingslashit($directory) . $filename;
  if ($force || !file_exists($path)) {
    $data = nirog_bhumi_order_invoice_data($order_id);
    if (!$data) {
      return '';
    }
    $written = file_put_contents($path, nirog_bhumi_render_order_invoice_pdf($data), LOCK_EX);
    if (!$written) {
      return '';
    }
  }
  $order->update_meta_data('_nb_invoice_pdf_file', $filename);
  $order->save();
  return $path;
}

/**
 * Only physical goods get this invoice - a cart made up entirely of the
 * consultation product has nothing to ship and is covered by the existing
 * consultation invoice flow instead.
 */
function nirog_bhumi_order_has_physical_goods($order) {
  $consultation_id = function_exists('nirog_bhumi_consultation_product_id') ? nirog_bhumi_consultation_product_id() : 0;
  foreach ($order->get_items() as $item) {
    if ((int) $item->get_product_id() !== $consultation_id) {
      return true;
    }
  }
  return false;
}

/*
 * Only woocommerce_payment_complete, after the invoice number is assigned
 * (priority 25, later than nirog_bhumi_assign_woocommerce_order_invoice at
 * the default priority) - the same "generated only once payment is
 * genuinely confirmed" rule already applied to the consultation invoice
 * and to invoice numbering itself.
 */
function nirog_bhumi_generate_order_invoice_after_payment($order_id) {
  if (!function_exists('wc_get_order')) {
    return;
  }
  $order = wc_get_order($order_id);
  if (!$order || !$order->is_paid() || !nirog_bhumi_order_has_physical_goods($order)) {
    return;
  }
  nirog_bhumi_create_order_invoice_pdf($order_id);
}
add_action('woocommerce_payment_complete', 'nirog_bhumi_generate_order_invoice_after_payment', 25);

function nirog_bhumi_order_invoice_pdf_url($order_id) {
  $order = wc_get_order($order_id);
  return add_query_arg([
    'action' => 'nirog_download_order_invoice',
    'order' => absint($order_id),
    'key' => $order ? $order->get_order_key() : '',
  ], admin_url('admin-post.php'));
}

function nirog_bhumi_download_order_invoice() {
  $order_id = isset($_GET['order']) ? absint($_GET['order']) : 0;
  $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
  $order = ($order_id && function_exists('wc_get_order')) ? wc_get_order($order_id) : false;
  if (!$order) {
    wp_die(esc_html__('Invoice not found.', 'nirog-bhumi'), '', ['response' => 404]);
  }
  $allowed = current_user_can('manage_woocommerce')
    || (is_user_logged_in() && $order->get_customer_id() && (int) $order->get_customer_id() === get_current_user_id())
    || ($key && hash_equals((string) $order->get_order_key(), $key));
  if (!$allowed || !$order->is_paid()) {
    wp_die(esc_html__('This invoice link is invalid or has expired.', 'nirog-bhumi'), '', ['response' => 403]);
  }
  $path = nirog_bhumi_create_order_invoice_pdf($order_id);
  if (!$path || !is_readable($path)) {
    wp_die(esc_html__('The invoice could not be generated.', 'nirog-bhumi'), '', ['response' => 500]);
  }
  nocache_headers();
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="Nirog-Bhumi-Invoice-' . sanitize_file_name((string) $order->get_meta('_nb_invoice_number')) . '.pdf"');
  header('Content-Length: ' . filesize($path));
  readfile($path);
  exit;
}
add_action('admin_post_nopriv_nirog_download_order_invoice', 'nirog_bhumi_download_order_invoice');
add_action('admin_post_nirog_download_order_invoice', 'nirog_bhumi_download_order_invoice');

/**
 * A "Download tax invoice" link wherever an order's own details are shown
 * to the customer - the thank-you page and My Account > Orders > view
 * order both render through the same order-details template, so one hook
 * covers both. Nothing is shown for an order still awaiting payment.
 */
function nirog_bhumi_order_invoice_download_link($order) {
  if (!$order || !$order->is_paid() || !nirog_bhumi_order_has_physical_goods($order) || !$order->get_meta('_nb_invoice_number')) {
    return;
  }
  printf(
    '<p class="nb-order-invoice-link"><a class="button" href="%s">%s</a></p>',
    esc_url(nirog_bhumi_order_invoice_pdf_url($order->get_id())),
    esc_html__('Download Tax Invoice', 'nirog-bhumi')
  );
}
add_action('woocommerce_order_details_after_order_table', 'nirog_bhumi_order_invoice_download_link');

/**
 * Same link on the admin's own order edit screen, since that screen does
 * not render the customer-facing order-details template above.
 */
function nirog_bhumi_admin_order_invoice_link($order) {
  nirog_bhumi_order_invoice_download_link($order);
}
add_action('woocommerce_admin_order_data_after_order_details', 'nirog_bhumi_admin_order_invoice_link');

/**
 * Same link, in the customer's order confirmation email.
 */
function nirog_bhumi_order_invoice_email_link($order, $sent_to_admin, $plain_text, $email) {
  if ($sent_to_admin || $plain_text || !$order || !$order->is_paid() || !nirog_bhumi_order_has_physical_goods($order) || !$order->get_meta('_nb_invoice_number')) {
    return;
  }
  printf(
    '<p><a href="%s" style="display:inline-block;background:#314936;color:#fff;padding:12px 20px;text-decoration:none;border-radius:24px">%s</a></p>',
    esc_url(nirog_bhumi_order_invoice_pdf_url($order->get_id())),
    esc_html__('Download Tax Invoice', 'nirog-bhumi')
  );
}
add_action('woocommerce_email_after_order_table', 'nirog_bhumi_order_invoice_email_link', 10, 4);

function nirog_bhumi_create_consultation_invoice_pdf($post_id, $force = false) {
  if (!$post_id || get_post_type($post_id) !== 'nb_consultation' || get_post_meta($post_id, 'payment_status', true) !== 'verified') return '';
  $invoice_number = get_post_meta($post_id, 'invoice_number', true);
  if (!$invoice_number) return '';
  $uploads = wp_upload_dir();
  if (!empty($uploads['error'])) return '';
  $directory = trailingslashit($uploads['basedir']) . 'nirog-private-invoices';
  if (!wp_mkdir_p($directory)) return '';
  if (!file_exists($directory . '/index.php')) file_put_contents($directory . '/index.php', "<?php http_response_code(404); exit;\n");
  if (!file_exists($directory . '/.htaccess')) file_put_contents($directory . '/.htaccess', "Deny from all\n");
  $filename = hash('sha256', wp_salt('auth') . '|' . $post_id . '|' . $invoice_number) . '.pdf';
  $path = trailingslashit($directory) . $filename;
  if ($force || !file_exists($path)) {
    $written = file_put_contents($path, nirog_bhumi_render_invoice_pdf(nirog_bhumi_consultation_invoice_data($post_id)), LOCK_EX);
    if (!$written) return '';
  }
  update_post_meta($post_id, '_nb_invoice_pdf_file', $filename);
  return $path;
}

function nirog_bhumi_consultation_pdf_url($post_id) {
  return add_query_arg([
    'action' => 'nirog_download_invoice',
    'entry' => absint($post_id),
    'access' => nirog_bhumi_consultation_status_token($post_id),
  ], admin_url('admin-post.php'));
}

function nirog_bhumi_download_consultation_invoice() {
  $post_id = isset($_GET['entry']) ? absint($_GET['entry']) : 0;
  $token = isset($_GET['access']) ? sanitize_text_field(wp_unslash($_GET['access'])) : '';
  $cookie_entry = function_exists('nirog_bhumi_consultation_cookie_entry') ? nirog_bhumi_consultation_cookie_entry() : 0;
  $allowed = current_user_can('edit_post', $post_id) || ($post_id && $cookie_entry === $post_id) || ($post_id && nirog_bhumi_consultation_status_access($post_id, $token));
  if (!$allowed || get_post_meta($post_id, 'payment_status', true) !== 'verified') wp_die(esc_html__('This invoice link is invalid or has expired.', 'nirog-bhumi'), '', ['response' => 403]);
  $path = nirog_bhumi_create_consultation_invoice_pdf($post_id);
  if (!$path || !is_readable($path)) wp_die(esc_html__('The invoice could not be generated.', 'nirog-bhumi'), '', ['response' => 500]);
  nocache_headers();
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="Nirog-Bhumi-Invoice-' . sanitize_file_name(get_post_meta($post_id, 'invoice_number', true)) . '.pdf"');
  header('Content-Length: ' . filesize($path));
  readfile($path);
  exit;
}
add_action('admin_post_nopriv_nirog_download_invoice', 'nirog_bhumi_download_consultation_invoice');
add_action('admin_post_nirog_download_invoice', 'nirog_bhumi_download_consultation_invoice');
