# Developer test

A developer test I did when in communication with a company. This was my first encounter with WordPress (I was familiar with PHP) so this might be apparent in the code.

This repo was originally created only for me to be able to work on the project on multiple machines, but I thought I'd make it public (excuse my messy commit history). Comments are in norwegian as this was for a norwegian company.

While working on this I also ended up creating [this pull request](https://github.com/pimssas/pims-api-client-php/pull/49) to fix a bug in the [Pims PHP API client repo](https://github.com/pimssas/pims-api-client-php/) ([link to issue](https://github.com/pimssas/pims-api-client-php/issues/48)).

## Provided specifications

Create a WordPress plugin that has an event list page fetched from https://api.pims.io/
Plugin should register a shortcode with output that contains:

-   Link to main page where event are listed (first page) called "Home"
-   Links to previous and next page of events
-   List of events with the following info:
    -   Name (label)
    -   Date and time of the event
    -   Price (costing_capacity) and currency
    -   Venue name, city and country \* Display a "Sold out" message if the event is sold out (use sold_out_date).

Add possibility to use search parameters for:

-   events per page
-   Sort by
-   From date - to date

This info should be stored in browser local storage and should be changeable (_I asked for clarification and "storing" this in GET-parameters was ok_). The results page should use all found parameters showing results.

### Additional (optional) task:

Plugin should add functionality to store data for logged in users.

For logged in users a "Save" button should appear for each listed event. Clicking the save button, the event info should be saved for the current logged in user
