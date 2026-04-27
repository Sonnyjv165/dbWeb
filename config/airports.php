<?php
$AIRPORTS = [
    'MNL' => ['city' => 'Manila',        'country' => 'Philippines', 'name' => 'Ninoy Aquino International Airport'],
    'CEB' => ['city' => 'Cebu',          'country' => 'Philippines', 'name' => 'Mactan-Cebu International Airport'],
    'DXB' => ['city' => 'Dubai',         'country' => 'UAE',         'name' => 'Dubai International Airport'],
    'SIN' => ['city' => 'Singapore',     'country' => 'Singapore',   'name' => 'Singapore Changi Airport'],
    'NRT' => ['city' => 'Tokyo',         'country' => 'Japan',       'name' => 'Narita International Airport'],
    'HKG' => ['city' => 'Hong Kong',     'country' => 'China',       'name' => 'Hong Kong International Airport'],
    'BKK' => ['city' => 'Bangkok',       'country' => 'Thailand',    'name' => 'Suvarnabhumi Airport'],
    'KUL' => ['city' => 'Kuala Lumpur',  'country' => 'Malaysia',    'name' => 'Kuala Lumpur International Airport'],
    'SYD' => ['city' => 'Sydney',        'country' => 'Australia',   'name' => 'Kingsford Smith Airport'],
    'LAX' => ['city' => 'Los Angeles',   'country' => 'USA',         'name' => 'Los Angeles International Airport'],
    'JFK' => ['city' => 'New York',      'country' => 'USA',         'name' => 'John F. Kennedy International Airport'],
    'LHR' => ['city' => 'London',        'country' => 'UK',          'name' => 'London Heathrow Airport'],
];

function airportCity($code) {
    global $AIRPORTS;
    return $AIRPORTS[$code]['city'] ?? $code;
}

function airportCountry($code) {
    global $AIRPORTS;
    return $AIRPORTS[$code]['country'] ?? '';
}

function airportLabel($code) {
    global $AIRPORTS;
    $a = $AIRPORTS[$code] ?? null;
    return $a ? "{$a['city']} ($code) — {$a['country']}" : $code;
}
