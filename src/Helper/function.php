<?php

/**
 * Returns the microtime
 *
 * @return int The microtime calculated
 */
function microtimeFloat()
{
    [$usec, $sec] = explode(' ', microtime());

    return ( float )$usec + ( float )$sec;
}