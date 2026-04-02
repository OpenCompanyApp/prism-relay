<?php

$generated = file_exists(__DIR__ . '/relay.generated.php')
    ? require __DIR__ . '/relay.generated.php'
    : [];
$manual = file_exists(__DIR__ . '/relay.manual.php')
    ? require __DIR__ . '/relay.manual.php'
    : [];

$merge = function (array $base, array $overlay) use (&$merge): array {
    foreach ($overlay as $key => $value) {
        if (is_int($key)) {
            if (! in_array($value, $base, true)) {
                $base[] = $value;
            }

            continue;
        }

        if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
            $base[$key] = $merge($base[$key], $value);

            continue;
        }

        $base[$key] = $value;
    }

    return $base;
};

return $merge($generated, $manual);
