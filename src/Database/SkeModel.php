<?php

namespace CampusUnion\Sked\Database;

abstract class SkeModel {

    use \CampusUnion\Sked\ValidatesDates;

    /** @var mixed $oConnector Database connector. */
    protected $oConnector;

    /**
     * Init the data connector.
     *
     * @param array $aOptions
     */
    abstract public function __construct(array $aOptions);

    /**
     * Build the events query.
     *
     * @param string $strDateStart Datetime that today starts.
     * @param string $strDateEnd Datetime that today ends.
     * @param int $iMemberId Optional unique ID of the event participant.
     */
    abstract protected function query(string $strDateStart, string $strDateEnd, int $iMemberId = null);

    /**
     * Persist event data to the database.
     *
     * @param array $aData Array of data to persist.
     * @return int|bool Success/failure.
     */
    abstract public function save(array $aData);

    /**
     * Fetch event sessions from the database.
     *
     * @param string $strDate Date of event sessions to fetch.
     * @param int $iMemberId Optional member ID to limit results for a single person.
     * @param int $iTimezoneOffset Optional timezone adjustment.
     * @return array
     */
    public function fetch(string $strDate, int $iMemberId = null, int $iTimezoneOffset = 0)
    {
        // Filter input
        $this->validateDate($strDate);
        $strDateStart = $strDate . ' 00:00:00';
        if ($iTimezoneOffset) {
            $strDateStart = date(
                'Y-m-d H:i:s',
                strtotime($strDateStart . ($iTimezoneOffset > 0 ? ' +' : ' ') . $iTimezoneOffset . ' hours')
            );
        }
        $strDateEnd = date('Y-m-d H:i:s', strtotime($strDateStart . ' + 1 day - 1 second'));

        // Get results and sort by time
        return usort($this->query($strDateStart, $strDateEnd, $iMemberId), function($aResult1, $aResult2) {
            return $aResult1['session_at'] <=> $aResult2['session_at']
                ?: $aResult1['starts_at'] <=> $aResult2['starts_at'];
        });
    }

}
