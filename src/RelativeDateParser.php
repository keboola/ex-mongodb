<?php

declare(strict_types=1);

namespace MongoExtractor;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Keboola\Component\UserException;

class RelativeDateParser
{
    // Pattern matches "{{now}}" or "{{now-Nd/w/m/y}}" including surrounding quotes
    private const PLACEHOLDER_PATTERN = '/"?\{\{(now(?:-(\d+)([dwmy]))?)?\}\}"?/i';

    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function parse(string $query): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn(array $matches): string => $this->replacePlaceholder($matches),
            $query,
        );
    }

    public function hasPlaceholders(string $query): bool
    {
        return (bool) preg_match(self::PLACEHOLDER_PATTERN, $query);
    }

    /**
     * @param array<int, string|null> $matches
     * @throws UserException
     */
    private function replacePlaceholder(array $matches): string
    {
        $fullMatch = $matches[0];
        $expression = $matches[1] ?? null;

        if ($expression === null || $expression === '') {
            throw new UserException(sprintf('Invalid relative date placeholder: %s', $fullMatch));
        }

        if (strtolower($expression) === 'now') {
            return $this->formatAsMongoDate($this->now);
        }

        $amount = isset($matches[2]) ? (int) $matches[2] : null;
        $unit = isset($matches[3]) ? strtolower($matches[3]) : null;

        if ($amount === null || $unit === null) {
            throw new UserException(sprintf('Invalid relative date placeholder: %s', $fullMatch));
        }

        $date = $this->subtractFromNow($amount, $unit);
        return $this->formatAsMongoDate($date);
    }

    private function subtractFromNow(int $amount, string $unit): DateTimeImmutable
    {
        $intervalSpec = match ($unit) {
            'd' => "P{$amount}D",
            'w' => 'P' . ($amount * 7) . 'D',
            'm' => "P{$amount}M",
            'y' => "P{$amount}Y",
            default => throw new UserException(sprintf('Invalid time unit: %s', $unit)),
        };

        return $this->now->sub(new DateInterval($intervalSpec));
    }

    private function formatAsMongoDate(DateTimeImmutable $date): string
    {
        return sprintf('{"$date": "%s"}', $date->format(DateTimeInterface::ATOM));
    }
}
