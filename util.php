<?php

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function string_if(bool $bool, string $string): string
{
    return $bool ? $string : "";
}

function selected_if(bool $bool): string
{
    return string_if($bool, "selected");
}

function country_from_code(string $country_code)
{
    $names = json_decode(file_get_contents(__DIR__ . "/country_codes.json"), true);

    if (!$names[$country_code]) {
        return $country_code;
    } else {
        return $names[$country_code];
    }
}


function format_date(string $date, string $format): string
{
    $date_object = date_create($date);
    return date_format($date_object, $format);
}

function associative_map(array $associative_array, callable $callback): array
{
    array_walk($associative_array, function (&$value, $key) use($callback) {
        $value = $callback($key, $value);
    });

    return $associative_array;
}