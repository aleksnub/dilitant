<?php

declare(strict_types=1);

function dilitant_visibility_with_cookie(
    string $name,
    string $value,
    callable $callback
) {
    $exists = array_key_exists($name, $_COOKIE);
    $previous = $exists ? $_COOKIE[$name] : null;

    try {
        $_COOKIE[$name] = $value;

        return $callback();
    } finally {
        if ($exists) {
            $_COOKIE[$name] = $previous;
        } else {
            unset($_COOKIE[$name]);
        }
    }
}