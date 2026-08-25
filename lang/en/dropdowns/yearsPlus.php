<?php

// returns a list of years, starting from the current year to 10 years in the future

return array_combine(
    range(date('Y'), date('Y', strtotime('+10 years'))),
    range(date('Y'), date('Y', strtotime('+10 years')))
);
