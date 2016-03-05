<?php

namespace CampusUnion\Sked\Database;

class SkeModelPDO extends SkeModel {

    /**
     * Init the data connector.
     *
     * @param array $aOptions
     */
    public function __construct(array $aOptions)
    {
        $aOptions = [
            'dsn' => 'mysql:host=localhost;dbname=cu_dev;charset=UTF8',
            'user' => 'homestead',
            'pass' => 'secret',
        ];
        $this->oConnector = new PDO($aOptions['dsn'], $aOptions['user'], $aOptions['pass']);
    }

    /**
     * Build the events query.
     *
     * @param string $strDateStart Datetime that today starts.
     * @param string $strDateEnd Datetime that today ends.
     * @param int $iMemberId Optional unique ID of the event participant.
     */
    protected function query(string $strDateStart, string $strDateEnd, int $iMemberId = null)
    {
        // Begin query
        $strQuery = 'SELECT *,
            CONCAT_WS(
                " ",
                "' . substr($this->strDatetime, 0, 10) . '",
                DATE_FORMAT(sked_events.starts_at, "%H:%i:%s")
            ) AS session_at
            FROM sked_events'; // @todo FIX session_at

        // Check member
        if ($iMemberId) {
            $strQuery .= ' INNER JOIN sked_event_members ON sked_event_members.sked_event_id = sked_events.id'
                . ' AND sked_event_members.member_id = :member_id';
        }

        // Not expired
        $strQuery .= ' WHERE (sked_events.ends_at IS NULL OR sked_events.ends_at < :date_start)';

        // Happening today
        $strQuery .= ' AND (';

            // Original date matches
            $strQuery .= ' sked_events.starts_at BETWEEN :date_start AND :date_end';

            // Daily
            $strQuery .= ' OR sked_events.interval = "1"';

            // Day of week matches for weekly events
            $strQuery .= ' OR (
                sked_events.interval = "7"
                AND sked_events.' . date('D', strtotime($strDateStart)) . ' = 1
                AND (
                    DATE_DIFF(
                        :date_start_NOTIME,
                        DATE_FORMAT(sked_events.starts_at, "%Y-%m-%d")
                    )/7
                ) % sked_events.frequency = 0
            )';

            // Monthly by day of week
            // Calculate how many months since first session, see if it's an exact match for today
            $strQuery .= ' OR (
                sked_events.interval = "Monthly"
                AND sked_events.' . date('D', strtotime($strDateStart)) . ' = 1
                AND TIMESTAMP_ADD(
                    MONTH,
                    ROUND(DATE_DIFF(:date_start, DATE_FORMAT(sked_events.starts_at, "%Y-%m-%d"))/30),
                    sked_events.starts_at
                ) BETWEEN :date_start AND :date_end
            )';

            // Monthly by date
            $strQuery .= ' OR (
                sked_events.interval = "Monthly"
                AND sked_events.Mon = 0
                AND sked_events.Tue = 0
                AND sked_events.Wed = 0
                AND sked_events.Thu = 0
                AND sked_events.Fri = 0
                AND sked_events.Sat = 0
                AND sked_events.Sun = 0
                AND DAYOFMONTH(sked_events.starts_at) = :date_start_DAYOFMONTH
                AND (:date_start_YEARMONTH - EXTRACT(YEARMONTH FROM sked_events.starts_at))
                    % sked_events.frequency = 0
            )';

        $strQuery .= ')';

        // PDO
        $oSelect = $this->oConnector->prepare($strQuery);
        $oSelect->execute([
            ':member_id' => $iMemberId,
            ':date_start' => $strDateStart,
            ':date_start_NOTIME' => date('Y-m-d', strtotime($strDateStart)),
            ':date_start_DAYOFMONTH' => date('d', $strDateStart),
            ':date_start_YEARMONTH' => date('Ym', $strDateStart),
            ':date_end' => $strDateEnd,
        ]);
        return $oSelect->fetchAll();
    }

}
