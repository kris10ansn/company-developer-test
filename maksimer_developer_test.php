<?php

/*
 * Plugin Name: [company name] Developer Test
 * Plugin URI: http://maksimer.no
 * Description: [company name] Developer Test shortcode plugin
 * Version: 0.0.1
 * Author: Kristian Silli Nessa
 * Author URI: http://github.com/kris10ansn
*/


class PimsEvent
{
    public int $id;
    public int $venue_id;
    public int $series_id;
    public string $label;
    public string $datetime;
    public string $currency;
    public int $costing_capacity;
    public string $sold_out_date;
}

function debug()
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function pims_api_get($query)
{
    $base_url = "https://sandbox.pims.io/api/v1/";
    $username = "sandbox";
    $password = "c5jI1ABi8d0x87oWfVzvXALqkf0hToGq";

    $arguments = [
        "headers" => [
            "Authorization" => "Basic " . base64_encode("$username:$password")
        ]
    ];

    $response = wp_remote_get($base_url . $query, $arguments);

    $response["body"] = json_decode($response["body"]);

    return $response;
}

function shortcode_events()
{
    // Display all errors (for debugging, probably remove in production)
    debug();

    $response = pims_api_get("events");

    echo "<pre>";
    var_dump($response["body"]->_embedded->events[0]);
    echo "</pre>";

    return "<p>.</>";
}

add_shortcode("events", "shortcode_events");
