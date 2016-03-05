<?php

namespace CampusUnion\Sked;

trait ValidatesDates {

    /**
     * Verifies that the given date string is in format YYYY-MM-DD.
     *
     * @param string $strDate YYYY-MM-DD
     * @return bool
     */
    protected function validateDate(string $strDate)
    {
        if (!(preg_match("/\d{4}\-\d{2}-\d{2}/", $strDate) && strtotime($strDate))) {
            throw new Exception(
                'Invalid date string given to ' . __METHOD__ . '. Use format YYYY-MM-DD.'
            );
        }
    }

}
