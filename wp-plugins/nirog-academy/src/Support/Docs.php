<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * Reads the Markdown guides in docs/ and renders them as HTML for the Help
 * Center (participants) and Courses → Help (staff).
 *
 * Each article starts with front matter:
 *   ---
 *   id: participant-your-device
 *   title: Using Nirog Bhumi Courses on your device
 *   audience: participant
 *   order: 20
 *   ---
 *
 * Hindi translations sit next to the English file with a .hi.md suffix and
 * are used when the participant has chosen Hindi.
 */
final class Docs {
  /** @return array<string, array{id:string, title:string, summary:string, path:string, order:int}> */
  public static function participant(): array {
    return self::collect('participant');
  }

  /** @return array<string, array{id:string, title:string, summary:string, path:string, order:int}> */
  public static function admin(): array {
    return self::collect('admin');
  }

  private static function collect(string $audience): array {
    $dir = NBA_DIR . 'docs/' . $audience;
    $out = [];
    $hindi = I18n::current() === 'hi_IN';
    foreach (glob($dir . '/*.md') ?: [] as $path) {
      if (str_ends_with($path, '.hi.md')) {
        continue;
      }
      if ($hindi && is_readable(substr($path, 0, -3) . '.hi.md')) {
        $path = substr($path, 0, -3) . '.hi.md';
      }
      $meta = self::front_matter((string) file_get_contents($path));
      $slug = sanitize_title(basename(str_replace('.hi.md', '.md', $path), '.md'));
      $out[$slug] = [
        'id' => $meta['id'] ?? $slug,
        'title' => $meta['title'] ?? ucwords(str_replace('-', ' ', $slug)),
        'summary' => $meta['summary'] ?? '',
        'path' => $path,
        'order' => (int) ($meta['order'] ?? 100),
        'slug' => $slug,
      ];
    }
    uasort($out, static fn($a, $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);
    return $out;
  }

  /** @return array<string, string> */
  public static function front_matter(string $text): array {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $text, $m)) {
      return [];
    }
    $meta = [];
    foreach (explode("\n", $m[1]) as $line) {
      if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
        $meta[$kv[1]] = trim($kv[2], " \"'");
      }
    }
    return $meta;
  }

  public static function render(string $path, ?callable $link_resolver = null): string {
    $text = (string) file_get_contents($path);
    $text = (string) preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $text);
    return Markdown::to_html($text, $link_resolver);
  }
}
