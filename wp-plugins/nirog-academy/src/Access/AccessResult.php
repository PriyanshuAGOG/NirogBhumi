<?php
namespace NirogBhumi\Academy\Access;

defined('ABSPATH') || exit;

/**
 * Outcome of an access check: allowed, or denied with a code and a message
 * the participant can understand.
 */
final class AccessResult {
  private function __construct(
    public readonly bool $allowed,
    public readonly string $code,
    public readonly string $message,
    public readonly int $programme_id = 0,
    public readonly ?object $enrollment = null,
    public readonly array $data = [],
  ) {}

  public static function allow(int $programme_id, ?object $enrollment = null): self {
    return new self(true, 'ok', '', $programme_id, $enrollment);
  }

  public static function deny(string $code, string $message, array $data = []): self {
    return new self(false, $code, $message, (int) ($data['programme_id'] ?? 0), $data['enrollment'] ?? null, $data);
  }
}
