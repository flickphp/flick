<?php

// The same list as countries.php with the three most common pulled to the top.
// `+` keeps the left keys, in the left order, and appends whatever the right
// side has that the left does not - so US, CA and GB appear once, up here.
return [
    'US' => 'United States ',
    'CA' => 'Canada',
    'GB' => 'United Kingdom',
    ' ' => '---------------',
] + require __DIR__.'/countries.php';
