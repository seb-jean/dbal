<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use function mb_str_split;

/**
 * The replacements of the letters of the Latin-1 Supplement and Latin Extended-A Unicode blocks, and of the Romanian
 * letters with a comma below, by the unaccent extension of PostgreSQL, which removes their diacritics and expands their
 * ligatures the same way in all the supported versions of PostgreSQL.
 *
 * @internal
 */
final class UnaccentRules
{
    /** The letters, by replacement. */
    private const LETTERS = [
        "'n" => 'ŉ',
        'A' => 'ÀÁÂÃÄÅĀĂĄ',
        'AE' => 'Æ',
        'C' => 'ÇĆĈĊČ',
        'D' => 'ÐĎĐ',
        'E' => 'ÈÉÊËĒĔĖĘĚ',
        'G' => 'ĜĞĠĢ',
        'H' => 'ĤĦ',
        'I' => 'ÌÍÎÏĨĪĬĮİ',
        'IJ' => 'Ĳ',
        'J' => 'Ĵ',
        'K' => 'Ķ',
        'L' => 'ĹĻĽĿŁ',
        'N' => 'ÑŃŅŇŊ',
        'O' => 'ÒÓÔÕÖØŌŎŐ',
        'OE' => 'Œ',
        'R' => 'ŔŖŘ',
        'S' => 'ŚŜŞŠȘ',
        'T' => 'ŢŤŦȚ',
        'TH' => 'Þ',
        'U' => 'ÙÚÛÜŨŪŬŮŰŲ',
        'W' => 'Ŵ',
        'Y' => 'ÝŶŸ',
        'Z' => 'ŹŻŽ',
        'a' => 'àáâãäåāăą',
        'ae' => 'æ',
        'c' => 'çćĉċč',
        'd' => 'ðďđ',
        'e' => 'èéêëēĕėęě',
        'g' => 'ĝğġģ',
        'h' => 'ĥħ',
        'i' => 'ìíîïĩīĭįı',
        'ij' => 'ĳ',
        'j' => 'ĵ',
        'k' => 'ķ',
        'l' => 'ĺļľŀł',
        'n' => 'ñńņňŋ',
        'o' => 'òóôõöøōŏő',
        'oe' => 'œ',
        'q' => 'ĸ',
        'r' => 'ŕŗř',
        's' => 'śŝşšſș',
        'ss' => 'ß',
        't' => 'ţťŧț',
        'th' => 'þ',
        'u' => 'ùúûüũūŭůűų',
        'w' => 'ŵ',
        'y' => 'ýÿŷ',
        'z' => 'źżž',
    ];

    /**
     * Returns the replacements of the letters.
     *
     * @return non-empty-array<non-empty-string, non-empty-string>
     */
    public static function getReplacements(): array
    {
        $replacements = [];

        foreach (self::LETTERS as $replacement => $letters) {
            foreach (mb_str_split($letters) as $letter) {
                $replacements[$letter] = $replacement;
            }
        }

        return $replacements;
    }

    private function __construct()
    {
    }
}
