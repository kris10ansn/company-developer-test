<?php


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


function format_date(string $date, string $format)
{
    $date_object = date_create($date);
    return date_format($date_object, $format);
}