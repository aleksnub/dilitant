<?php

declare(strict_types=1);

function dilitant_test_abort(string $message): never
{
    fwrite(STDERR, "ABORT: {$message}\n");
    exit(1);
}