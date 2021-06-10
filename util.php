<?php

use Pims\Api\Client;

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

function country_from_code(string $country_code): string
{
    // http://country.io/names.json
    $names = json_decode(file_get_contents(__DIR__ . "/country_codes.json"), true);

    if (!$names[$country_code]) {
        return $country_code;
    } else {
        return $names[$country_code];
    }
}


function format_datestring(string $date, string $format): string
{
    $date_object = date_create($date);
    return date_format($date_object, $format);
}

function associative_map(array $associative_array, callable $callback): array
{
    array_walk($associative_array, function (&$value, $key) use ($callback) {
        $value = $callback($key, $value);
    });

    return $associative_array;
}

function try_catch(callable $callback, callable $fallback): Closure
{
    return function() use ($callback, $fallback) {
        try {
            return $callback();
        } catch (Exception $e) {
            return $fallback($e);
        }
    };
}

function was_saved(int $event_id): bool
{
    return is_user_logged_in() && ($_POST[EVENT_SAVE_KEY] ?? NULL) == $event_id;
}

function was_unsaved(int $event_id): bool
{
    return is_user_logged_in() && ($_POST[EVENT_UNSAVE_KEY] ?? NULL) == $event_id;
}

function event_is_saved(int $event_id, int $user_id): bool
{
    $saved_posts = get_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY);
    return in_array($event_id, $saved_posts);
}

function save_event(int $user_id, int $save_id): bool
{
    if (!event_is_saved($save_id, $user_id)) {
        if (add_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY, $save_id))
            return true;
    }
    return false;
}

function unsave_event(int $user_id, int $save_id): bool
{
    if (event_is_saved($save_id, $user_id)) {
        return delete_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY, $save_id);
    }
    return false;
}

function nav_link(string $dir, int $page): string
{
    return add_query_arg([
        EVENT_PAGE_KEY => $page + ($dir === Client::PAGINATION_NEXT ? 1 : -1)
    ], $_SERVER['REQUEST_URI']);
}

function nav_button(string $dir, string $href): string
{
    $align = $dir === Client::PAGINATION_NEXT ? 'alignright' : 'alignleft';
    $button_text = $dir === Client::PAGINATION_NEXT ? 'Next' : 'Previous';

    return "
        <div class='nav-$dir $align'>
            <a href='$href'>
                <button>$button_text</button>
            </a>
        </div>
    ";
}