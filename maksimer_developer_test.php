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

require __DIR__ . "/vendor/autoload.php";
use Pims\Api\Client;


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

function events(): string
{
    // Display all errors (for debugging, probably remove in production)
    debug();
}

add_shortcode("events", "events");
