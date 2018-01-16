<?php


/**
 * Defines a constant with the default value of './'
 *
 * @return void
 */
function defineConstant($value)
{
    if (!defined($value)) {
        define($value, './');
    }
}

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