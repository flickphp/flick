<?php

// returns a list of years, starting from 1930 to current

$startDate = 1930;
$stopDate = date('Y');

return array_combine(range($stopDate, $startDate), range($stopDate, $startDate));
