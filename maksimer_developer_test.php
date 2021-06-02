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

function debug(): void
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function country_from_code(string $country_code) {
    $names = json_decode(file_get_contents(__DIR__ . "/country_codes.json"), true);

    if (!$names[$country_code]) {
        return $country_code;
    } else {
        return $names[$country_code];
    }
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
    public string $country;

    public function __construct(object $object)
    {
        parent::__construct($object);
        $this->country = country_from_code($this->country_code);
    }

    public static function get(int $id): PimsVenue
    {
        return new PimsVenue(
            pims_get("venues/$id")
        );
    }
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

    public PimsVenue $venue;

    public function __construct(object $object)
    {
        parent::__construct($object);
    }

    public function display(): string
    {
        $venue = PimsVenue::get($this->venue_id);
        $sold_out_message = "";

        if ($this->sold_out_date) {
            $sold_out_date = date_create($this->sold_out_date);
            $sold_out_date_formatted = date_format($sold_out_date, "F jS Y");
            $sold_out_message = "<p class='sold-out'>Sold out! ($sold_out_date_formatted)</p>";
        }

        return sprintf('
            <div class="event">
                <h3 title="%1$s">%1$s</h3>
                <div class="info">      
                    <small>%2$s</small>
                    <small>%3$s, %4$s</small>
                </div>
                <p>Price: %5$d %6$s</p>
                %7$s
            </div>
        ',
            $this->label,
            $this->formatted_datetime(),
            $venue->city,
            $venue->country,
            $this->costing_capacity,
            $this->currency,
            $sold_out_message
        );
    }

    /**
     * @return string
     * Example output: October 22nd 2021
     */
    public function formatted_datetime(): string
    {
        $datetime = date_create($this->datetime);
        return date_format($datetime, "F jS Y");
    }
}

function events(): string
{
    // Display all errors (for debugging, probably remove in production)
    debug();

    $events_response = pims_get("events");
    $events = pims_embedded_events($events_response);

    $events_html = join(
        PHP_EOL,
        array_map(fn($event) => $event->display(), $events)
    );

    return sprintf('
        <div class="events">
            %s
        </div>
    ', $events_html);
}

add_shortcode("events", "events");
