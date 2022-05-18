<?php

declare(strict_types=1);

namespace MongoExtractor;

use MongoDB\BSON;

class ExportHelper
{

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
        return preg_replace_callback(
            '~{"\$date":(?>\s)*("(?>(?>\\\")|[^"])*")}~',
            function (array $m) use ($isoDate): string {
                return $isoDate ? '"ISODate(' . addslashes($m[1]) .')"' : $m[1];
            },
            $input
        );
    }

    public static function convertObjectIdToString(string $input): string
    {
        return preg_replace_callback(
            '~{"\$oid":(?>\s)*("(?>(?>\\\")|[^"])*")}~',
            function (array $m): string {
                return '"ObjectId(' . addslashes($m[1]) .')"';
            },
            $input
        );
    }

    public static function convertStringIdToObjectId(string $input): string
    {
        return preg_replace_callback(
            '/"_id": (ObjectId\("([^"]*)"\))/',
            static function (array $m): string {
                return str_replace($m[1], '{"$oid": "' . $m[2] . '"}', $m[0]);
            },
            $input
        );

    }

    public static function fixIsoDateInGteQuery(string $input): string
    {
        return preg_replace_callback(
            '~"\$gte":"ISODate\((\\\"(?>(?>\\\")|[^"])*\\\")\)"~',
            function (array $m): string {
                return '"$gte":{"$date": ' . stripslashes($m[1]) . '}';
            },
            $input
        );
    }

    public static function fixObjectIdInGteQuery(string $input): string
    {
        return preg_replace_callback(
            '~"\$gte":"ObjectId\((\\\"(?>(?>\\\")|[^"])*\\\")\)"~',
            function (array $m): string {
                return '"$gte":{"$oid": ' . stripslashes($m[1]) . '}';
            },
            $input
        );
    }

    public static function addQuotesToJsonKeys(string $input): string
    {
        return preg_replace('/([{,])(\s*)([A-Za-z\d_\-]+?)\s*:/', '$1"$3":', $input);
    }
}
