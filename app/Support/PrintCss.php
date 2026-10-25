<?php

namespace App\Support;

class PrintCss
{
    /** Encode all characters, including quotes, newlines and closing style tags, as CSS escapes. */
    public static function text(string $text): string
    {
        return '"'.implode('', array_map(fn ($character) => '\\'.dechex(mb_ord($character, 'UTF-8')).' ', mb_str_split($text, 1, 'UTF-8'))).'"';
    }
}
