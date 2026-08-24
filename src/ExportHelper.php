<?php

declare(strict_types=1);

namespace MongoExtractor;

use RuntimeException;

class ExportHelper
{

    private const PREG_REPLACE_ERROR_MSG = 'preg_replace in function \"%s\" failed.';

    /**
     * Extended JSON wrappers for numeric BSON types that are stripped from mapping keys.
     *
     * The UI builds mapping keys from a canonical-looking sample ("amount.$numberLong"), but
     * mongoexport runs in relaxed mode and delivers these as plain JSON numbers, so the suffix
     * has to go or the key stops matching the data.
     *
     * Non-numeric wrappers ($oid, $date, $binary, $timestamp, ...) are deliberately NOT listed
     * here - mapping them via their full path (e.g. "_id.$oid", "date.$date") is the documented
     * and supported way to map them.
     */
    public const UNWRAPPED_NUMERIC_TYPES = ['numberDouble', 'numberInt', 'numberLong', 'numberDecimal'];

    /**
     * Extended JSON wrappers unwrapped on the document-value side by ExtendedJsonNormalizer.
     *
     * A subset of self::UNWRAPPED_NUMERIC_TYPES: only these two survive relaxed mode as a wrapper
     * around a value the mapping expects to be a scalar - Decimal128 always (JSON has no decimal
     * type), and a double when it is NaN or Infinity.
     *
     * $numberInt and $numberLong are intentionally absent. Relaxed mode never emits them
     * standalone; the only place they appear is nested inside {"$date":{"$numberLong":"..."}} for
     * dates outside the relaxed range, and DateNormalizer has to see that wrapper to read the
     * value as epoch milliseconds. Unwrapping it here would feed DateNormalizer a bare numeric
     * string and break every pre-1970 date.
     */
    public const UNWRAPPED_VALUE_TYPES = ['numberDouble', 'numberDecimal'];

    public static function convertSpecialColumnsToString(string $input): string
    {
        $input = self::convertDatesToString($input, true);

        $input = self::convertObjectIdToString($input);
        return $input;
    }

    public static function fixSpecialColumnsInGteQuery(string $input): string
    {
        $input = self::fixIsoDateInGteQuery($input);

        $input = self::fixObjectIdInGteQuery($input);
        return $input;
    }

    /**
     * Date fields in MongoDB export output, eg. {"$date":"2016-05-18T16:00:00Z"}
     * are converted to string with surrounding slashes (so JSON is still valid).
     * ISODate prefix is optional.
     */
    public static function convertDatesToString(string $input, bool $isoDate = false): string
    {
        $output = preg_replace_callback(
            '~{"\$date":(?>\s)*("(?>(?>\\\")|[^"])*")}~',
            function (array $m) use ($isoDate): string {
                return $isoDate ? '"ISODate(' . addslashes($m[1]) .')"' : $m[1];
            },
            $input,
        );

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    public static function convertObjectIdToString(string $input): string
    {
        $output = preg_replace_callback(
            '~{"\$oid":(?>\s)*("(?>(?>\\\")|[^"])*")}~',
            function (array $m): string {
                return '"ObjectId(' . addslashes($m[1]) .')"';
            },
            $input,
        );

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    public static function convertStringIdToObjectId(string $input): string
    {
        $output = preg_replace_callback(
            '/"_id": (ObjectId\("([^"]*)"\))/',
            static function (array $m): string {
                return str_replace($m[1], '{"$oid": "' . $m[2] . '"}', $m[0]);
            },
            $input,
        );

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    public static function fixIsoDateInGteQuery(string $input): string
    {
        $output = preg_replace_callback(
            '~"\$gte":"ISODate\((\\\"(?>(?>\\\")|[^"])*\\\")\)"~',
            function (array $m): string {
                return '"$gte":{"$date": ' . stripslashes($m[1]) . '}';
            },
            $input,
        );

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    public static function fixObjectIdInGteQuery(string $input): string
    {
        $output = preg_replace_callback(
            '~"\$gte":"ObjectId\((\\\"(?>(?>\\\")|[^"])*\\\")\)"~',
            function (array $m): string {
                return '"$gte":{"$oid": ' . stripslashes($m[1]) . '}';
            },
            $input,
        );

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    public static function addQuotesToJsonKeys(string $input): string
    {
        $output = preg_replace('/([{,])(\s*)([A-Za-z\d_\-]+?)\s*:/', '$1"$3":', $input);

        if ($output === null) {
            throw new RuntimeException(sprintf(self::PREG_REPLACE_ERROR_MSG, __FUNCTION__));
        }

        return $output;
    }

    /**
     * @param array<mixed, mixed> $mapping
     */
    public static function removeTypesInMappingKeys(array &$mapping): void
    {
        $pattern = '/(\.\$(' . implode('|', self::UNWRAPPED_NUMERIC_TYPES) . '))$/';
        $final = [];
        foreach ($mapping as $k => &$v) {
            if (is_string($k)) {
                $k = preg_replace($pattern, '', $k);
            }
            if (is_array($v)) {
                self::removeTypesInMappingKeys($v);
            }
            $final[$k] = $v;
        }
        $mapping = $final;
    }
}
