<?php

/*
 * Plugin Name: [company name] Developer Test
 * Plugin URI: http://maksimer.no
 * Description: [company name] Developer Test shortcode plugin
 * Version: 0.0.1
 * Author: Kristian Silli Nessa
 * Author URI: http://github.com/kris10ansn
*/

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function basic_auth(string $username, string $password): string
{
    return "Basic " . base64_encode("$username:$password");
}

function pims_get(string $query): object
{
    $response = wp_remote_get("https://sandbox.pims.io/api/v1/" . $query, [
        "headers" => [
            "Authorization" => basic_auth("sandbox", "c5jI1ABi8d0x87oWfVzvXALqkf0hToGq")
        ]
    ]);

    return json_decode($response["body"]);
}

/**
 * @param object $pims_response
 * @return PimsEvent[]
 */
function pims_embedded_events(object $pims_response): array
{
    $events = $pims_response->_embedded->events;

    return array_map(fn ($event) => new PimsEvent($event), $events);
}


abstract class APIObject {
    public function __construct(object $object)
    {
        foreach ($object as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

class PimsVenue extends APIObject
{
    public int $id;
    public string $label;
    public string $city;
    public string $country_code;
}

class PimsEvent extends APIObject
{
    public int $id;
    public int $venue_id;
    public ?int $series_id;
    public string $label;
    public string $datetime;
    public string $currency;
    public int $costing_capacity;
    public ?string $sold_out_date;

    public function get_venue(): PimsVenue
    {
        $venue_response = pims_get("venues/$this->venue_id");
        return new PimsVenue($venue_response);
    }
}

function shortcode_events(): string
{
    // Display all errors (for debugging, probably remove in production)
    debug();

    $events_response = pims_get("events");
    $events = pims_embedded_events($events_response);

    return "<p>.</p>";
}

add_shortcode("events", "shortcode_events");
