<?php

function styles(): string
{
    ob_start();

?>

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
        div#events div.event.unsaved {
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
        div#events form select {
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
            height: 75px;
            margin-top: -75px;
            visibility: hidden;
            background: transparent;
        }

        [hidden] {
            display: none !important;
        }
    </style>

<?php

    return ob_get_clean();
}
