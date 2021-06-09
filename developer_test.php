<style>
    div#events div.event div.info {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }

    div#events div.event div.top {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }

    div#events div.event div.top input[type=submit] {
        all: unset;
        cursor: pointer;
    }

    @keyframes background-removal {
        to {
            background: transparent;
        }
    }

    div#events div.event.saved {
        background: rgba(0, 255, 0, 0.5);
    }
    div#events div.event.unsaved {
        background: rgba(255, 0, 0, 0.5);
    }

    div#events div.event.saved,
    div#events div.event.unsaved
    {
        animation: background-removal 1s forwards;
    }

    div#events span.red {
        color: red;
    }

    div#events form {
        display: flex;
        flex-direction: column;
    }

    div#events form input,
    div#events form select
    {
        margin: 0 0 10px;
    }

    div#events form div.dates {
        display: flex;
        flex-direction: row;
    }

    div#events form div.dates input[type=date] {
        flex-basis: 100%;
    }

    div#events details {
        cursor: pointer;
    }

    div#events hr {
        margin: var(--global--spacing-vertical) 0;
    }

    /* Offset om man linker til event med #id (skjer f.eks. når et event lagres) */
    [id]::before {
        content: '';
        display: block;
        height:      75px;
        margin-top: -75px;
        visibility: hidden;
        background: transparent;
    }
</style>


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

function query_vars_filter($vars) {
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

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

/**
 * @throws ClientException
 */
function display_event(HalResource $event, Client $client): string
{
    $id = $event->getProperty('id');

    $datetime = $event->getProperty('datetime');
    $datetime_date = format_date($datetime, "F jS Y");
    $datetime_time = format_date($datetime, "H:i");

    $venue = $client->getOne(Endpoint::VENUES, $event->getProperty('venue_id'));
    $country = country_from_code($venue->getProperty('country_code'));

    $price = "{$event->getProperty('costing_capacity')} {$event->getProperty('currency')}";
    $price_information = "<b>$price</b>";

    if ($event->getProperty('sold_out_date')) {
        $sold_out_date = format_date($event->getProperty('sold_out_date'), 'd-m-Y');
        $price_information = "<b><s>$price</s> <span class='red'>SOLD OUT ($sold_out_date)</span></b>";
    }

    $save_button = "";

    if (is_user_logged_in() && !event_is_saved($id, get_current_user_id())) {
        $save_button = "
            <input type='number' hidden name='".EVENT_SAVE_KEY."' value='$id'>
            <input type='submit' value='💾' >
        ";
    } else if (is_user_logged_in()) {
        $save_button = "
            <input type='number' hidden name='".EVENT_UNSAVE_KEY."' value='$id'>
            <input type='submit' value='❌'>
        ";
    }

    return "
        <div class='event ". string_if(was_saved($id), "saved") . ' ' . string_if(was_unsaved($id), "unsaved") ."' id='$id'>
            <div class='top'>
                <h3>{$event->getProperty('label')}</h3>
                <form action='#$id' method='POST'>
                    $save_button
                </form>
            </div>
            
            <div class='info'>
                <p>
                    {$venue->getProperty('label')}
                    <i>({$venue->getProperty('city')}, $country)</i>
                </p>
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
    global $wp_query;
    debug();

    // Public sandbox credentials (https://api.pims.io/)
    $client = new Client(
        "https://sandbox.pims.io/api",
        "sandbox",
        "c5jI1ABi8d0x87oWfVzvXALqkf0hToGq",
        "en",
        "v1"
    );

    $page = get_query_var(EVENT_PAGE_KEY, 1);
    $page_size = get_query_var(EVENT_PAGE_SIZE_KEY, 25);
    $order = get_query_var(EVENT_SORT_ORDER_KEY, "");
    $sort = get_query_var(EVENT_SORT_KEY,"label");
    $date_from = get_query_var(EVENT_DATE_FROM_KEY, "");
    $date_to = get_query_var(EVENT_DATE_TO_KEY, "");

    $args = [
        "page" => $page,
        "sort" => $order . $sort,
        "page_size" => $page_size,
        "from_datetime" => string_if(!empty($date_from), format_date($date_from, "Y-m-d?H:i:s")),
        "to_datetime" => string_if(!empty($date_to), format_date($date_to, "Y-m-d?H:i:s")),
    ];

    $events_response = $client->getAll(Endpoint::EVENTS, $args);

    $events = $events_response->getResource('events');
    $page_count = $events_response->getProperty('page_count');

    $next_link = add_query_arg([EVENT_PAGE_KEY => $page + 1], $_SERVER['REQUEST_URI']);
    $prev_link = add_query_arg([EVENT_PAGE_KEY => $page - 1], $_SERVER['REQUEST_URI']);

    ob_start();

    echo "<div id='events'>";

    $save_id = $_POST[EVENT_SAVE_KEY] ?? NULL;
    if ($save_id && is_user_logged_in()) {
        save_event(get_current_user_id(), $save_id);
    }

    $unsave_id = $_POST[EVENT_UNSAVE_KEY] ?? NULL;
    if ($unsave_id && is_user_logged_in()) {
        unsave_event(get_current_user_id(), $unsave_id);
    }

    $option = fn (string $value, string $label, string $current_option)
        => "<option value='$value' " . selected_if($current_option === $value)  ." >$label</option>";

    echo "
        <details>
            <summary>Search parameters</summary>
            
            <form method='GET' action='#'>
                <label for='sort'>Sorting</label>
                <select name='" . EVENT_SORT_KEY . "' id='sort'>
                    <option selected disabled hidden>Sort by...</option>"
                    . $option("label", "Label", $sort)
                    . $option("datetime", "Date", $sort)
                    . $option("venue_label", "Venue name", $sort)
                    . $option("venue_city", "Venue city", $sort)
                    . $option("venue_country", "Venue country", $sort)
                    . "
                </select>
                <select name='" . EVENT_SORT_ORDER_KEY . "'>"
                    . $option("", "Ascending", $order)
                    . $option("-", "Descending", $order) . "
                </select>
                <label for='page_size'>Page size</label>
                <input type='number' min='1' name='". EVENT_PAGE_SIZE_KEY ."' id='page_size' value='$page_size'>
                
                <label>From date to date:</label>
                <div class='dates'>
                    <input type='date' name='".EVENT_DATE_FROM_KEY ."' value='$date_from'>
                    <input type='date' name='".EVENT_DATE_TO_KEY ."' value='$date_to'>
                </div>
                
                <!-- Set the page to 1 when updating sorting parameters -->
                <input type='number' name='". EVENT_PAGE_KEY ."' value='1' hidden>
                <button type='submit'>Go</button>
            </form>
        </details>
        ";

    foreach ($events as $event) {
        echo "<hr>";
        echo  display_event($event, $client);
    }
    echo "<hr>";

    if ($events_response->hasLink('prev')) {
        echo "<div class='nav-previous alignleft'>
                  <a href='$prev_link'>
                      <button>Previous</button>
                  </a>
              </div>";
    }

    if ($events_response->hasLink('next')) {
        echo "<div class='nav-next alignright'>
                  <a href='$next_link'>
                      <button>Next</button>
                  </a>
              </div>";
    }


    echo "<div class='aligncenter'>
              Page $page of $page_count
          </div>";

    echo "</div>";

    return ob_get_clean();
}

function removable_query_args() {

}

add_filter('query_vars', 'query_vars_filter');
add_shortcode("events", "events_shortcode");
