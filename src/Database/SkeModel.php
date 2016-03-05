<?php

namespace CampusUnion\Sked\Database;

abstract class SkeModel {

    use CampusUnion\Sked\ValidatesDates;

    /** @var mixed $oConnector Database connector. */
    protected $oConnector;

    /**
     * Build the events query.
     *
     * @param string $strDateStart Datetime that today starts.
     * @param string $strDateEnd Datetime that today ends.
     * @param int $iMemberId Optional unique ID of the event participant.
     */
    abstract protected function query(string $strDateStart, string $strDateEnd, int $iMemberId = null);

    /**
     * Init the data connector.
     *
     * @param array $aOptions
     */
    abstract public function __construct(array $aOptions);

    /**
     *
     * @return array // @todo WILL THIS BE AN ARRAY???
     */
    public function fetch(string $strDate, int $iMemberId = null, int $iTimezoneOffset = 0)
    {
        $this->validateDate($strDate);
        $strDateStart = $strDate . ' 00:00:00';
        if ($iTimezoneOffset) {
            $strDateStart = date(
                'Y-m-d H:i:s',
                strtotime($strDateStart . ($iTimezoneOffset > 0 ? ' +' : ' ') . $iTimezoneOffset . ' hours')
            );
        }
        $strDateEnd = date('Y-m-d H:i:s', strtotime($strDateStart . ' + 1 day - 1 second'));

        $aResults = $this->query();
        // @todo SORT RESULTS BY TIME

        return $aResults;
    }

}
