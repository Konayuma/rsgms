<?php

function sqlDateAdd($pdo, $dateCol, $intervalCol, $unit = 'MONTH') {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        return "($dateCol + ($intervalCol || ' " . strtolower($unit) . "')::INTERVAL)::DATE";
    }
    return "DATE_ADD($dateCol, INTERVAL $intervalCol $unit)";
}

function sqlDateFormat($pdo, $col, $format) {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        $map = ['%Y' => 'YYYY', '%m' => 'MM', '%d' => 'DD', '%b' => 'Mon', '%M' => 'Month'];
        $pgFmt = str_replace(array_keys($map), array_values($map), $format);
        return "TO_CHAR($col, '$pgFmt')";
    }
    return "DATE_FORMAT($col, '$format')";
}
