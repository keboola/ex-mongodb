<?php

declare(strict_types=1);

namespace MongoExtractor;

/**
 * Unwraps Extended JSON wrappers for numeric BSON types into plain scalars.
 *
 * mongoexport runs in relaxed Extended JSON mode, which represents int32/int64/double as plain
 * JSON numbers - but Decimal128 stays wrapped as {"$numberDecimal":"150.00"} (JSON has no decimal
 * type, so the spec cannot do otherwise), and so does a double that is NaN or Infinity. A field
 * holding such a value therefore arrives as an object, and csvmap refuses to write an object into
 * a column ("Cannot write data into column"). That is fatal for collections where the same field
 * is a plain number in some documents and a Decimal128 in others - no single mapping key can match
 * both shapes.
 *
 * Unwrapping the value here makes the data shape uniform, which lets a plain mapping key
 * ("amount") work for both. It is the value-side counterpart of the mapping-key stripping in
 * ExportHelper::removeTypesInMappingKeys(); see ExportHelper::UNWRAPPED_VALUE_TYPES for why the
 * two sides do not cover exactly the same set of wrappers.
 *
 * Values are unwrapped to a string, never to a float: Decimal128 exists precisely to hold values
 * a float cannot represent, and "150.00" must not become "150". Everything is written to CSV as a
 * string anyway.
 */
final class ExtendedJsonNormalizer implements DataNormalizer
{
    /**
     * @inheritDoc
     */
    public function normalize(array &$data): void
    {
        // Only the children are ever replaced - a document root is always a document, never a
        // scalar wrapper, so the shape of $data itself never changes.
        foreach ($data as &$item) {
            if (is_object($item)) {
                foreach (get_object_vars($item) as $key => $child) {
                    $item->{$key} = self::unwrap($child);
                }
                continue;
            }

            foreach ($item as $key => $child) {
                $item[$key] = self::unwrap($child);
            }
        }
    }

    private static function unwrap(mixed $value): mixed
    {
        if (is_object($value)) {
            $vars = get_object_vars($value);
            $scalar = self::unwrapScalar($vars);
            if ($scalar !== null) {
                return $scalar;
            }

            foreach ($vars as $key => $child) {
                $value->{$key} = self::unwrap($child);
            }

            return $value;
        }

        if (is_array($value)) {
            $scalar = self::unwrapScalar($value);
            if ($scalar !== null) {
                return $scalar;
            }

            foreach ($value as $key => $child) {
                $value[$key] = self::unwrap($child);
            }

            return $value;
        }

        return $value;
    }

    /**
     * Returns the plain value of an Extended JSON numeric wrapper, or null when $vars is not one.
     *
     * The single-key check matters: {"$numberDecimal": "150.00"} is a wrapper, but an object that
     * merely happens to also carry a "$numberDecimal" key alongside other fields is user data and
     * must be left alone.
     *
     * @param array<mixed, mixed> $vars
     */
    private static function unwrapScalar(array $vars): ?string
    {
        if (count($vars) !== 1) {
            return null;
        }

        $key = array_key_first($vars);
        if (!is_string($key) || !str_starts_with($key, '$')) {
            return null;
        }

        if (!in_array(substr($key, 1), ExportHelper::UNWRAPPED_VALUE_TYPES, true)) {
            return null;
        }

        $value = $vars[$key];
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        return (string) $value;
    }
}
