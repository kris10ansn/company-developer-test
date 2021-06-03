<style>
    div.events div.event {
        margin-bottom: 20px;
    }

    div.events div.event h3 {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    div.events div.event div.info {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }

    div.events div.event p.sold-out {
        color: red;
    }

    div.events div.event p.line-through {
        text-decoration: line-through;
    }
</style>

<?php

require __DIR__ . "/vendor/autoload.php";

use Pims\Api\Client;
use Pims\Api\Endpoint;
use Pims\Api\Exception\ClientException;


/*
 * Plugin Name: [company name] Developer Test
 * Plugin URI: http://maksimer.no
 * Description: [company name] Developer Test shortcode plugin
 * Version: 0.0.1
 * Author: Kristian Silli Nessa
 * Author URI: http://github.com/kris10ansn
*/

function format_date(string $date, string $format)
{
    $date_object = date_create($date);
    return date_format($date_object, $format);
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

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

const DATE_FORMAT = "F jS Y";

function events(): string
{
    // Display all errors (for debugging, probably remove in production)
    debug();

    try {
        $client = new Client(
            "https://sandbox.pims.io/api",
            "sandbox",
            "c5jI1ABi8d0x87oWfVzvXALqkf0hToGq",
            "en",
            "v1"
        );

        $events_result = $client->getAll(Endpoint::EVENTS);
        $events = $events_result->getResource("events");

        $events_html = "";

        foreach ($events as $event) {
            $event_date = format_date(
                $event->getProperty("datetime"),
                DATE_FORMAT
            );

            $venue = $client->getOne(Endpoint::VENUES, $event->getProperty("venue_id"));
            $venue_country = country_from_code($venue->getProperty("country_code"));

            $price_decoration = "";
            $sold_out_message = "";

            if ($event->getProperty("sold_out_date")) {
                $sold_out_date = format_date($event->getProperty("sold_out_date"), DATE_FORMAT);
                $sold_out_message = "<p class='sold-out'>Sold out ($sold_out_date)</p>";
                $price_decoration = "line-through";
            }

            $event_html = sprintf("
                <div class='event'>
                    <h3>{$event->getProperty("label")}</h3>
                    <div class='info'>
                        <small>$event_date</small>
                        <small>{$venue->getProperty("city")}, $venue_country</small>
                    </div>
                    <p class='$price_decoration'>
                        {$event->getProperty("costing_capacity")} {$event->getProperty("currency")}
                    </p>
                    $sold_out_message
                </div>
            ");

            $events_html .= PHP_EOL . $event_html;
        }

        $page = $events_result->getProperty("page");
        $prev = $page - 1;
        $next = $page + 1;

        $previous_disabled = $events_result->hasLink("prev") ? "" : "disabled";
        $next_disabled = $events_result->hasLink("next") ? "" : "disabled";

        return "
                <div class='events'>
                    $events_html
                    <form class='pagination' method='GET' action=''>
                        <button type='submit' $previous_disabled>Previous</button>
                        <input type='number' name='page' value='$page'>
                        <button type='submit' $next_disabled>Next</button>
                    </form>
                </div>
               ";
    } catch (ClientException $e) {
        return "<p>Error</p>";
    }
}

add_shortcode("events", "events");
