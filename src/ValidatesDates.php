<?php

namespace CampusUnion\Sked;

trait ValidatesDates {

    /**
     * Verifies that the given date string is in format YYYY-MM-DD.
     *
     * @param string|array $mDate YYYY-MM-DD
     * @return bool
     */
    protected function validateDate($mDate)
    {
        foreach (array_filter((array)$mDate) as $strDate) {
            if (!(preg_match("/\d{4}\-\d{2}-\d{2}/", $strDate) && strtotime($strDate))) {
                throw new Exception(
                    'Invalid date string given to ' . debug_backtrace()[1]['function']
                        . '. Use format YYYY-MM-DD.'
                );
            }
        }
    }

}
