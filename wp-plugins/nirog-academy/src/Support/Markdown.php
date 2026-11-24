<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * A small Markdown renderer for our own documentation (headings, paragraphs,
 * lists, tables, code, quotes, bold, italic, links). Output is passed through
 * wp_kses_post().
 */
final class Markdown {
  /** @var callable|null Maps a relative doc link (e.g. "your-device.md") to a URL. */
  private static $resolver = null;

  public static function to_html(string $md, ?callable $link_resolver = null): string {
    self::$resolver = $link_resolver;
    $lines = preg_split('/\r?\n/', $md) ?: [];
    $html = [];
    $para = [];
    $list = null;   // 'ul' | 'ol'
    $in_code = false;
    $code = [];
    $table = [];

    $flush_para = static function () use (&$para, &$html): void {
      if ($para) {
        $html[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
        $para = [];
      }
    };
    $close_list = static function () use (&$list, &$html): void {
      if ($list) {
        $html[] = '</' . $list . '>';
        $list = null;
      }
    };
    $flush_table = static function () use (&$table, &$html): void {
      if (!$table) {
        return;
      }
      $rows = array_values(array_filter($table, static fn($r) => !preg_match('/^\|?\s*:?-{2,}/', $r)));
      $out = '<div class="nba-table-wrap"><table>';
      foreach ($rows as $i => $row) {
        $cells = array_map('trim', explode('|', trim(trim($row), '|')));
        $tag = $i === 0 ? 'th' : 'td';
        $out .= '<tr>' . implode('', array_map(static fn($c) => "<$tag>" . self::inline($c) . "</$tag>", $cells)) . '</tr>';
      }
      $html[] = $out . '</table></div>';
      $table = [];
    };

    foreach ($lines as $line) {
      if (str_starts_with(trim($line), '```')) {
        if ($in_code) {
          $html[] = '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>';
          $code = [];
          $in_code = false;
        } else {
          $flush_para();
          $close_list();
          $flush_table();
          $in_code = true;
        }
        continue;
      }
      if ($in_code) {
        $code[] = $line;
        continue;
      }
      $trim = trim($line);
      if ($trim === '') {
        $flush_para();
        $close_list();
        $flush_table();
        continue;
      }
      if (str_starts_with($trim, '|')) {
        $flush_para();
        $close_list();
        $table[] = $trim;
        continue;
      }
      $flush_table();
      if (preg_match('/^(#{1,4})\s+(.*)$/', $trim, $m)) {
        $flush_para();
        $close_list();
        $level = min(4, strlen($m[1]) + 1); // # becomes h2: the page already has an h1.
        $id = sanitize_title($m[2]);
        $html[] = "<h$level id=\"" . esc_attr($id) . "\">" . self::inline($m[2]) . "</h$level>";
        continue;
      }
      if (preg_match('/^>\s?(.*)$/', $trim, $m)) {
        $flush_para();
        $close_list();
        $html[] = '<blockquote><p>' . self::inline($m[1]) . '</p></blockquote>';
        continue;
      }
      if (preg_match('/^[-*]\s+(.*)$/', $trim, $m) || preg_match('/^\d+[.)]\s+(.*)$/', $trim, $m2)) {
        $flush_para();
        $type = isset($m[1]) && preg_match('/^[-*]\s/', $trim) ? 'ul' : 'ol';
        $item = $type === 'ul' ? $m[1] : ($m2[1] ?? '');
        if ($list !== $type) {
          $close_list();
          $html[] = '<' . $type . '>';
          $list = $type;
        }
        $html[] = '<li>' . self::inline($item) . '</li>';
        continue;
      }
      if (preg_match('/^-{3,}$/', $trim)) {
        $flush_para();
        $close_list();
        $html[] = '<hr>';
        continue;
      }
      $close_list();
      $para[] = $trim;
    }
    $flush_para();
    $close_list();
    $flush_table();
    if ($in_code) {
      $html[] = '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>';
    }
    return wp_kses_post(implode("\n", $html));
  }

  private static function inline(string $text): string {
    $text = esc_html($text);
    $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = (string) preg_replace('/(?<![*\w])\*([^*]+)\*(?![*\w])/', '<em>$1</em>', $text);
    $text = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($m) {
      $url = html_entity_decode($m[2]);
      if (!preg_match('~^(https?://|/|#|mailto:)~', $url)) {
        // Relative links between docs: point to the Help Center article.
        $slug = sanitize_title(basename(strtok($url, '#'), '.md'));
        $url = self::$resolver ? (string) call_user_func(self::$resolver, $slug) : \NirogBhumi\Academy\Frontend\Router::url('help/' . $slug);
      }
      return '<a href="' . esc_url($url) . '">' . $m[1] . '</a>';
    }, $text);
    return $text;
  }
}
