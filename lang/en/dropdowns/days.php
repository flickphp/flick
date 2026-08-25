<?php

// returns the days in the current month, keyed by day number so the value
// submitted matches the day shown

$days = range(1, (int) date('t'));

return array_combine($days, $days);
