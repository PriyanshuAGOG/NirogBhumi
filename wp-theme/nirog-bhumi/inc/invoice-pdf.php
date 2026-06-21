<?php
/**
 * Server-generated consultation invoices for Nirog Bhumi.
 */

if (!defined('ABSPATH')) {
  exit;
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
  $invoice_date = $verified ? wp_date('d M Y', strtotime($verified)) : wp_date('d M Y');
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
    'customer_gstin' => get_post_meta($post_id, 'customer_gstin', true) ?: 'Unregistered',
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
  $ops .= nirog_bhumi_pdf_text(40, 99, 8, 'Wellness and lifestyle medicine - Diabetes reversal', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(410, 68, 21, 'TAX INVOICE', 'F3', $ink);
  $ops .= nirog_bhumi_pdf_text(425, 91, 8, $data['type'], 'F2', $green);
  $ops .= nirog_bhumi_pdf_line(38, 112, 557, 112, .6, '0.82 0.79 0.72');

  $ops .= nirog_bhumi_pdf_text(40, 132, 7.5, 'REGISTERED OFFICE', 'F2', $muted);
  $top = 148;
  foreach (nirog_bhumi_pdf_wrap($data['business_address'], 76) as $line) {
    $ops .= nirog_bhumi_pdf_text(40, $top, 8.5, $line, 'F1', $ink);
    $top += 12;
  }
  $ops .= nirog_bhumi_pdf_text(40, $top + 2, 8.5, trim($data['business_phone'] . ' - ' . $data['business_email']), 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(405, 132, 8, 'GSTIN', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 132, 8.5, $data['business_gstin'], 'F2', $ink);
  if ($data['business_cin']) {
    $ops .= nirog_bhumi_pdf_text(405, 148, 8, 'CIN', 'F2', $muted);
    $ops .= nirog_bhumi_pdf_text(462, 148, 8.5, $data['business_cin'], 'F1', $ink);
  }
  $ops .= nirog_bhumi_pdf_text(405, 164, 8, 'STATE', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(462, 164, 8.5, $data['business_state'] . ' (' . $data['business_state_code'] . ')', 'F1', $ink);

  $ops .= nirog_bhumi_pdf_rect(38, 194, 326, 112, $paper);
  $ops .= nirog_bhumi_pdf_text(52, 214, 7.5, 'BILLED TO', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(52, 232, 10, $data['name'], 'F2', $ink);
  $bill_lines = array_merge(nirog_bhumi_pdf_wrap($data['address'], 49), nirog_bhumi_pdf_wrap(trim($data['city'] . ', ' . $data['state'] . ' - ' . $data['postcode']), 49));
  $bill_top = 248;
  foreach ($bill_lines as $line) {
    if ($line === '') continue;
    $ops .= nirog_bhumi_pdf_text(52, $bill_top, 8.5, $line, 'F1', $ink);
    $bill_top += 12;
  }
  $ops .= nirog_bhumi_pdf_text(52, 282, 8.5, $data['phone'], 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(190, 214, 7.5, 'PLACE OF SUPPLY', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(190, 232, 9, $data['state'] . ' (' . $data['state_code'] . ')', 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(190, 254, 7.5, 'CUSTOMER GSTIN', 'F2', $muted);
  $ops .= nirog_bhumi_pdf_text(190, 272, 8.5, $data['customer_gstin'], 'F1', $ink);

  $ops .= nirog_bhumi_pdf_text(390, 208, 8, 'Invoice No.', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 208, 9, $data['invoice_number'], 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 228, 8, 'Invoice Date', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 228, 9, $data['invoice_date'], 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 248, 8, 'Payment Terms', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 248, 9, 'Paid', 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(390, 268, 8, 'Reverse Charge', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(470, 268, 9, 'No', 'F1', $ink);

  $ops .= nirog_bhumi_pdf_rect(38, 330, 519, 30, '0.933 0.91 0.85');
  $headers = [[46, '#'], [70, 'DESCRIPTION'], [310, 'HSN/SAC'], [374, 'QTY'], [420, 'RATE'], [486, 'AMOUNT']];
  foreach ($headers as [$x, $label]) $ops .= nirog_bhumi_pdf_text($x, 349, 7.5, $label, 'F2', $green);
  $ops .= nirog_bhumi_pdf_text(47, 386, 9, '1', 'F1', $ink);
  $ops .= nirog_bhumi_pdf_text(70, 382, 10, '30-minute consultation with Gautam Khandelwal', 'F2', $ink);
  $ops .= nirog_bhumi_pdf_text(70, 397, 8, 'Naturopathy and lifestyle consultation', 'F1', $muted);
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
  $ops .= nirog_bhumi_pdf_line(405, 724, 535, 724, .5, '0.65 0.63 0.58');
  $ops .= nirog_bhumi_pdf_text(426, 740, 8, 'Authorised Signatory', 'F1', $muted);
  $ops .= nirog_bhumi_pdf_text(128, 792, 8, 'Computer-generated invoice - nirogbhumi.com', 'F1', $muted);

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
