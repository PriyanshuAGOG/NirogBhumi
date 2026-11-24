<?php
/**
 * Branded HTML email. Inline styles only: email apps ignore stylesheets.
 *
 * @var string   $name
 * @var string   $heading
 * @var string[] $paragraphs
 * @var string   $button_label
 * @var string   $button_url
 * @var string   $support
 * @var string   $area
 */
defined('ABSPATH') || exit;

$logo = \NirogBhumi\Academy\Frontend\View::logo_url();
?><!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html($heading); ?></title></head>
<body style="margin:0;padding:0;background:#e8dec9;font-family:Inter,'Segoe UI',Arial,sans-serif;color:#304535;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e8dec9;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#f8f6ef;border:1px solid #d8d0c0;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
      <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($area); ?>" width="44" height="44" style="display:block;margin-bottom:16px;border:0;"><?php endif; ?>
      <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7a6e;"><?php echo esc_html($area); ?></p>
      <h1 style="margin:0 0 18px;font-family:Georgia,'Times New Roman',serif;font-weight:normal;font-size:26px;line-height:1.25;color:#304535;"><?php echo esc_html($heading); ?></h1>
      <p style="margin:0 0 14px;font-size:16px;line-height:1.6;"><?php echo esc_html(sprintf(
        /* translators: %s: name */
        __('Namaste %s,', 'nirog-academy'),
        $name
      )); ?></p>
      <?php foreach ($paragraphs as $p) : ?>
        <p style="margin:0 0 14px;font-size:16px;line-height:1.6;"><?php echo esc_html($p); ?></p>
      <?php endforeach; ?>
      <?php if ($button_label && $button_url) : ?>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 8px;"><tr><td style="background:#314936;border-radius:999px;">
          <a href="<?php echo esc_url($button_url); ?>" style="display:inline-block;padding:13px 26px;color:#f8f6ef;text-decoration:none;font-size:16px;font-weight:600;"><?php echo esc_html($button_label); ?></a>
        </td></tr></table>
        <p style="margin:10px 0 0;font-size:12px;line-height:1.5;color:#6b7a6e;word-break:break-all;"><?php esc_html_e('If the button does not work, copy this link into your browser:', 'nirog-academy'); ?><br><?php echo esc_html($button_url); ?></p>
      <?php endif; ?>
    </td></tr>
    <tr><td style="padding:18px 32px 26px;border-top:1px solid #d8d0c0;">
      <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7a6e;"><?php echo esc_html(sprintf(
        /* translators: %s: support email */
        __('Questions? Reply to this email or write to %s.', 'nirog-academy'),
        $support
      )); ?></p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
