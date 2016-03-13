<?php

namespace CampusUnion\Sked;

trait ValidatesDates {

    /**
     * Verifies that the given date string is in format YYYY-MM-DD.
     *
     * Converts the date string to the desired format if possible.
     *
     * @param string $strDate YYYY-MM-DD
     * @return bool
     */
    protected function validateDate(string &$strDate)
    {
        if (!preg_match("/\d{4}\-\d{2}-\d{2}/", $strDate)) {
            if ($iTime = strtotime($strDate)) {
                $strDate = date('Y-m-d', $iTime);
            } else {
                throw new \Exception(
                    'Invalid date string given to ' . debug_backtrace()[1]['function']
                        . '. Use format YYYY-MM-DD.'
                );
            }
        }
    }

}
