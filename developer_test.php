<style>
    div#events div.event div.info {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
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

    div#events details {
        cursor: pointer;
    }

    div#events hr {
        margin: var(--global--spacing-vertical) 0;
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

use Jsor\HalClient\HalResource;
use Pims\Api\Client;
use Pims\Api\Endpoint;
use Pims\Api\Exception\ClientException;

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

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

const EVENT_PAGE_KEY = "events_page";
const EVENT_PAGE_SIZE_KEY = "events_page_size";
const EVENT_SORT_KEY = "events_sort";
const EVENT_SORT_ORDER_KEY = "events_sort_order";

/**
 * @throws ClientException
 */
function display_event(HalResource $event, Client $client): string
{
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

    return "
        <div class='event'>
            <h3>{$event->getProperty('label')}</h3>
            
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

function selected_if(bool $bool): string
{
    if ($bool) {
        return "selected";
    }

    return "";
}

/**
 * @throws ClientException
 */
function events_shortcode(): string
{
    debug();

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
    $sort_parameter = $order . $sort;

    $events_response = $client->getAll(Endpoint::EVENTS, [
        "page" => $page,
        "sort" => $sort_parameter,
        "page_size" => $page_size,
    ]);

    $events = $events_response->getResource('events');
    $page_count = $events_response->getProperty('page_count');

    $next_link = add_query_arg([EVENT_PAGE_KEY => $page + 1], $_SERVER['REQUEST_URI']);
    $prev_link = add_query_arg([EVENT_PAGE_KEY => $page - 1], $_SERVER['REQUEST_URI']);

    ob_start();

    echo "<div id='events'>";

    function option(string $value, string $label, string $current_option): string
    {
        return "<option value='$value' " . selected_if($current_option === $value)  ." >$label</option>";
    }

    echo "
        <details>
            <summary>Search parameters</summary>
            
            <form method='GET' action=''>
                <label for='sort'>Sorting</label>
                <select name='" . EVENT_SORT_KEY . "' id='sort'>
                    <option selected disabled hidden>Sort by...</option>"
                    . option("label", "Label", $sort)
                    . option("datetime", "Date", $sort) . "
                </select>
                <select name='" . EVENT_SORT_ORDER_KEY . "'>"
                    . option("", "Ascending", $order)
                    . option("-", "Descending", $order) . "
                </select>
                <label for='page_size'>Page size</label>
                <input type='number' min='1' name='". EVENT_PAGE_SIZE_KEY ."' id='page_size' value='" . $page_size . "'>
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

function query_vars_filter($vars) {
    $vars[] = EVENT_PAGE_KEY;
    $vars[] = EVENT_PAGE_SIZE_KEY;
    $vars[] = EVENT_SORT_KEY;
    $vars[] = EVENT_SORT_ORDER_KEY;
    return $vars;
}

add_filter('query_vars', 'query_vars_filter');
add_shortcode("events", "events_shortcode");
