<?php
/*
 * Plugin Name: [company name] Developer Test
 * Plugin URI: http://maksimer.no
 * Description: [company name] Developer Test shortcode plugin
 * Version: 0.0.1
 * Author: Kristian Silli Nessa
 * Author URI: http://github.com/kris10ansn
*/

require __DIR__ . "/vendor/autoload.php";
require "util.php";
require "styles.php";

use Jsor\HalClient\HalResource;
use Pims\Api\Client;
use Pims\Api\Endpoint;
use Pims\Api\Exception\ClientException;

const USERMETA_SAVED_EVENTS_KEY = "saved_events";

const EVENT_PAGE_KEY = "events_page";
const EVENT_PAGE_SIZE_KEY = "events_page_size";
const EVENT_SORT_KEY = "events_sort";
const EVENT_SORT_ORDER_KEY = "events_sort_order";
const EVENT_SAVE_KEY = "save_event";
const EVENT_UNSAVE_KEY = "unsave_event";
const EVENT_DATE_FROM_KEY = "events_date_from";
const EVENT_DATE_TO_KEY = "events_date_to";

function query_vars_filter($vars)
{
    $vars[] = EVENT_PAGE_KEY;
    $vars[] = EVENT_PAGE_SIZE_KEY;
    $vars[] = EVENT_SORT_KEY;
    $vars[] = EVENT_SORT_ORDER_KEY;
    $vars[] = EVENT_SAVE_KEY;
    $vars[] = EVENT_UNSAVE_KEY;
    $vars[] = EVENT_DATE_FROM_KEY;
    $vars[] = EVENT_DATE_TO_KEY;
    return $vars;
}

function was_saved($event_id): bool
{
    return is_user_logged_in() && ($_POST[EVENT_SAVE_KEY] ?? NULL) == $event_id;
}

function was_unsaved($event_id): bool
{
    return is_user_logged_in() && ($_POST[EVENT_UNSAVE_KEY] ?? NULL) == $event_id;
}

function event_is_saved($event_id, int $user_id): bool
{
    $saved_posts = get_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY);
    return in_array($event_id, $saved_posts);
}

function save_event(int $user_id, $save_id): bool
{
    if (!event_is_saved($save_id, $user_id)) {
        if (add_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY, intval($save_id)))
            return true;
    }
    return false;
}

function unsave_event(int $user_id, $save_id): bool
{
    if (event_is_saved($save_id, $user_id)) {
        return delete_user_meta($user_id, USERMETA_SAVED_EVENTS_KEY, intval($save_id));
    }
    return false;
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

function nav_link(string $dir, int $page): string
{
    return add_query_arg([
        EVENT_PAGE_KEY => $page + ($dir === Client::PAGINATION_NEXT ? 1 : -1)
    ], $_SERVER['REQUEST_URI']);
}

function display_event(HalResource $event): string
{
    $id = $event->getProperty('id');

    $datetime = $event->getProperty('datetime');
    $datetime_date = format_datestring($datetime, "F jS Y");
    $datetime_time = format_datestring($datetime, "H:i");

    $venue = $event->getFirstResource('venue');
    $country = country_from_code($venue->getProperty('country_code'));

    $price = "{$event->getProperty('costing_capacity')} {$event->getProperty('currency')}";
    $price_information = "<b>$price</b>";

    if ($event->getProperty('sold_out_date')) {
        $sold_out_date = format_datestring($event->getProperty('sold_out_date'), 'F jS Y');
        $price_information = "<b><s>$price</s> <span class='red'>SOLD OUT ($sold_out_date)</span></b>";
    }

    $save_button = "";

    if (is_user_logged_in()) {
        $save_button = event_is_saved($id, get_current_user_id()) ?
            "<input type='number' hidden name='" . EVENT_UNSAVE_KEY . "' value='$id'>
             <input type='submit' value='❌'>"
            :
            "<input type='number' hidden name='" . EVENT_SAVE_KEY . "' value='$id'>
             <input type='submit' value='💾' >";
    }

    return "
        <div class='event " . string_if(was_saved($id), "saved") . ' ' . string_if(was_unsaved($id), "unsaved") . "' id='$id'>
            <div class='top'>
                <h3>{$event->getProperty('label')}</h3>
                <form action='#$id' method='POST'>
                    $save_button
                </form>
            </div>
            
            <div class='info'>
                <p>{$venue->getProperty('label')} <i>({$venue->getProperty('city')}, $country)</i></p>
                <p>$datetime_date</p>
            </div>
            
            <p><i>Time:</i> $datetime_time</p>
            <p>$price_information</p>
        </div>
    ";
}

/**
 * @throws ClientException
 */
function events_shortcode(): string
{
    // Public sandbox credentials (https://api.pims.io/)
    $client = new Client(
        'https://sandbox.pims.io/api',
        'sandbox',
        'c5jI1ABi8d0x87oWfVzvXALqkf0hToGq',
        'en',
        'v1'
    );

    $page = intval(get_query_var(EVENT_PAGE_KEY, 1));
    $page_size = intval(get_query_var(EVENT_PAGE_SIZE_KEY, 25));
    $order = get_query_var(EVENT_SORT_ORDER_KEY, '');
    $sort = get_query_var(EVENT_SORT_KEY, 'label');
    $date_from = get_query_var(EVENT_DATE_FROM_KEY, '');
    $date_to = get_query_var(EVENT_DATE_TO_KEY, '');

    $sort_options = [
        'label' => 'Label',
        'datetime' => 'Date',
        'venue_label' => 'Venue name',
        'venue_city' => 'Venue city',
        'venue_country' => 'Venue country'
    ];

    // Validation of input
    if (!in_array($sort, array_keys($sort_options)))
        $sort = 'label';
    if (!in_array($order, ['', '-']))
        $order = '';
    if (format_datestring($date_from, 'Y-m-d') !== $date_from)
        $date_from = '';
    if (format_datestring($date_to, 'Y-m-d') !== $date_to)
        $date_to = '';
    //

    $events_response = $client->getAll(Endpoint::EVENTS, [
        'page' => $page,
        'sort' => $order . $sort,
        'page_size' => $page_size,
        'from_datetime' => string_if(!empty($date_from), format_datestring($date_from, 'Y-m-d?H:i:s')),
        'to_datetime' => string_if(!empty($date_to), format_datestring($date_to, 'Y-m-d?H:i:s')),
        'expand' => '_embedded{venue}'
    ]);

    ob_start();

    echo styles();
    echo '<div id="events">';

    if (isset($_POST[EVENT_SAVE_KEY]) && is_user_logged_in())
        save_event(get_current_user_id(), $_POST[EVENT_SAVE_KEY] ?? NULL);
    if (isset($_POST[EVENT_UNSAVE_KEY]) && is_user_logged_in())
        unsave_event(get_current_user_id(), $_POST[EVENT_UNSAVE_KEY] ?? NULL);

    $option = fn (string $value, string $label, string $current_option)
                => "<option value='$value' " . selected_if($current_option === $value)  . " >$label</option>";

    echo "
        <details>
            <summary>Search parameters</summary>
            
            <form method='GET' action='#'>
                <label for='sort'>Sorting</label>
                <select name='" . EVENT_SORT_KEY . "' id='sort'>
                    <option selected disabled hidden>Sort by...</option>"
                    . implode(' ', associative_map($sort_options, fn ($value, $label) => $option($value, $label, $sort))) . "
                </select>
                <select name='" . EVENT_SORT_ORDER_KEY . "'>"
                    . $option('', 'Ascending', $order)
                    . $option('-', 'Descending', $order) . "
                </select>
                <label for='page_size'>Page size</label>
                <input type='number' min='1' name='" . EVENT_PAGE_SIZE_KEY . "' id='page_size' value='$page_size'>
                
                <label>From date to date:</label>
                <div class='dates'>
                    <input type='date' name='" . EVENT_DATE_FROM_KEY . "' value='$date_from'>
                    <input type='date' name='" . EVENT_DATE_TO_KEY . "' value='$date_to'>
                </div>
                
                <!-- Set the page to 1 when updating sorting parameters -->
                <input type='number' name='" . EVENT_PAGE_KEY . "' value='1' hidden>
                <button type='submit'>Go</button>
            </form>
        </details>
        ";

    echo implode(
        ' ',
        array_map(
            fn ($event) => '<hr>' . display_event($event),
            $events_response->getResource('events')
        )
    );
    echo '<hr>';

    // The constant Client::PAGINATION_PREVIOUS is set to 'previous'
    // (probably a bug, opened a pull request https://github.com/pimssas/pims-api-client-php/pull/49)
    if ($events_response->hasLink('prev'))
        echo nav_button('prev', nav_link('prev', $page));

    if ($events_response->hasLink(Client::PAGINATION_NEXT))
        echo nav_button(Client::PAGINATION_NEXT, nav_link(Client::PAGINATION_NEXT, $page));

    $current_page = $events_response->getProperty('page');
    $page_count = $events_response->getProperty('page_count');

    echo "<div class='aligncenter'>
              Page $current_page of $page_count
          </div>";

    echo "</div>"; // div#events

    return ob_get_clean();
}

add_filter('query_vars', 'query_vars_filter');
add_shortcode('events', function () {
    try {
        return events_shortcode();
    } catch(Exception $e) {
        echo 'ERROR ' . $e->getCode();
    }
});
