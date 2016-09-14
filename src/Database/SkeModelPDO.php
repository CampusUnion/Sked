<?php

namespace CampusUnion\Sked\Database;

class SkeModelPDO extends SkeModel {

    /** @var array $aQueryParams Array of PDO params to bind. */
    private $aQueryParams = [];

    /**
     * Init the data connector and other options.
     *
     * @param array $aOptions
     */
    public function __construct(array $aOptions)
    {
        $strDsn = $aOptions['driver'] . ':host=' . $aOptions['host'] . ';dbname=' . $aOptions['dbname'];
        $this->oConnector = new \PDO($strDsn, $aOptions['user'], $aOptions['pass']);
        parent::__construct($aOptions);
    }

    /** @return bool Begin a database transaction. */
    protected function beginTransaction()
    {
        return $this->oConnector->beginTransaction();
    }

    /** @return bool Commit a database transaction. */
    protected function commitTransaction()
    {
        return $this->oConnector->commit();
    }

    /** @return bool Roll back a database transaction. */
    protected function rollBackTransaction()
    {
        return $this->oConnector->rollBack();
    }

    /**
     * Retrieve an event from the database.
     *
     * @param int $iId
     * @return array
     */
    public function find(int $iId)
    {
        $oSelect = $this->oConnector->prepare('SELECT * FROM sked_events WHERE id = :id');
        if (!$oSelect->execute([':id' => $iId]))
            throw new \Exception(__METHOD__ . ' - ' . $oSelect->errorInfo()[2]);
        return $oSelect->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Build the events query for today.
     *
     * @param string $strDateStart Datetime that today starts.
     * @param string $strDateEnd Datetime that today ends.
     * @return array
     */
    protected function queryDay(string $strDateStart, string $strDateEnd)
    {
        $strQuery = $this->querySelectFrom($strDateStart)
            . $this->queryJoin()
            . $this->queryWhereNotExpired();

        // Happening today
        $strQuery .= ' AND (';

            // Original date matches
            $strQuery .= ' sked_events.starts_at BETWEEN :date_start AND :date_end';

            // Or it's already started and...
            $strQuery .= ' OR (sked_events.starts_at <= :date_start AND (';

                // Daily
                $strQuery .= ' (
                    sked_events.interval = "1"
                    AND (
                        DATEDIFF(
                            :date_start_NOTIME,
                            DATE_FORMAT(sked_events.starts_at, "%Y-%m-%d")
                        )/sked_events.frequency
                    ) % 1 =0
                )';

                // Day of week matches for weekly events
                $strQuery .= ' OR (
                    sked_events.interval = "7"
                    AND sked_events.' . date('D', strtotime($strDateStart)) . ' = 1
                    AND (
                        DATEDIFF(
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
                    AND TIMESTAMPADD(
                        MONTH,
                        ROUND(DATEDIFF(:date_start, DATE_FORMAT(sked_events.starts_at, "%Y-%m-%d"))/30),
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
                    AND (:date_start_YEARMONTH - EXTRACT(YEAR_MONTH FROM sked_events.starts_at))
                        % sked_events.frequency = 0
                )';

            $strQuery .= '))';

        $strQuery .= ')' . $this->queryGroupAndOrder();

        // PDO
        return $this->queryPDO($strQuery, $this->aQueryParams + [
            ':date_start' => $strDateStart,
            ':date_start_NOTIME' => date('Y-m-d', strtotime($strDateStart)),
            ':date_start_DAYOFMONTH' => date('d', strtotime($strDateStart)),
            ':date_start_YEARMONTH' => date('Ym', strtotime($strDateStart)),
            ':date_end' => $strDateEnd,
        ]);
    }

    /**
     *
     */
    protected function queryMoment()
    {
        $oNow = new DateTime();
        $strQuery = $this->querySelectFrom($oNow->format('Y-m-d H:i:s'))
            . $this->queryJoin()
            . $this->queryWhereNotExpired()
            . ' AND (';

            // Exact match
            $strQuery .= 'sked_events.starts_at = "' . $this->adjustedStartTime() . '" OR (';

                // Time matches
                $strQuery .= 'SUBSTR(' . $this->adjustedStartTime() . ', 12, 5) = "' . $oNow->format('H:i') . '" AND (';

                    // Daily
                    $strQuery .= 'sked_events.interval = "1"';

                    // Day of week matches for weekly events
                    $strQuery .= ' OR (
                        sked_events.interval = "7"
                        AND sked_events.' . $oNow->format('D') . ' = 1
                        AND (
                            DATEDIFF(
                                "' . $oNow->format('Y-m-d') . '",
                                DATE_FORMAT(' . $this->adjustedStartTime() . ', "%Y-%m-%d")
                            )/7
                        ) % sked_events.frequency = 0
                    )';

                    // Monthly by day of week
                    // Calculate how many months since first session, see if it's an exact match for today
                    $strQuery .= ' OR (
                        sked_events.interval = "Monthly"
                        AND sked_events.' . $oNow->format('D') . ' = 1
                        AND TIMESTAMPADD(
                            MONTH,
                            ROUND(DATEDIFF(
                                "' . $oNow->format('Y-m-d H:i:s') . '",
                                DATE_FORMAT(' . $this->adjustedStartTime() . ', "%Y-%m-%d")
                            )/30),
                            ' . $this->adjustedStartTime() . '
                        ) = "' . $oNow->format('Y-m-d H:i:s') . '"
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
                        AND DAYOFMONTH(' . $this->adjustedStartTime() . ') = "' . $oNow->format('d') . '"
                        AND (
                            "' . $oNow->format('Ym') . '" - EXTRACT(
                                YEAR_MONTH FROM ' . $this->adjustedStartTime()
                            . ')
                        ) % sked_events.frequency = 0
                    )';

                $strQuery .= ')';
            $strQuery .= ')';
        $strQuery .= ')' . $this->queryGroupAndOrder();

        return $this->queryPDO($strQuery, $this->aQueryParams + [
            ':date_start' => $oNow->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Helper function used in queryMoment() that considers the lead_time
     * when appropriate.
     *
     * @return string SQL to represent the datetime, with or without lead_time adjustment.
     */
    protected function adjustedStartTime($iTime = null)
    {
        $strSql = $iTime
            ? 'sked_events.starts_at - INTERVAL DAYOFWEEK(sked_events.starts_at) DAY + INTERVAL '
                . (date('w', $iTime) + 1) . ' DAY'
            : 'sked_events.starts_at';

        if ($this->bAdjustForLeadTime)
            $strSql = 'DATE_SUB(' . $strSql . ', INTERVAL sked_event_members.lead_time MINUTE)';

        return $strSql;
    }

    /**
     *
     */
    private function querySelectFrom(string $strDateStart)
    {
        return 'SELECT sked_events.*,
            CONCAT_WS(
                " ",
                "' . substr($strDateStart, 0, 10) . '",
                DATE_FORMAT(sked_events.starts_at, "%H:%i:%s")
            ) AS session_at
            FROM sked_events'; // @todo FIX session_at
    }

    /**
     *
     */
    private function queryJoin()
    {
        $strReturn = '';

        // Check tags
        if (!empty($this->aTags)) {
            foreach ($this->aTags as $iTagId => $mValue) {
                $strTagParam = ':tag_id_' . $iTagId;
                $strValueParam = ':tag_value_' . $iTagId;
                $strReturn .= ' INNER JOIN sked_event_tags ON sked_event_tags.sked_event_id = sked_events.id'
                    . ' AND sked_event_tags.tag_id = ' . $strTagParam
                    . ' AND sked_event_tags.value = ' . $strValueParam;
                $this->aQueryParams[$strTagParam] = $iTagId;
                $this->aQueryParams[$strValueParam] = $mValue;
            }
        }

        // Check member
        if ($this->iMemberId) {
            if ($this->bPublic) {
                $strReturn .= ' LEFT JOIN sked_event_members ON sked_event_members.sked_event_id = sked_events.id'
                    . ' WHERE (sked_event_members.member_id IS NULL OR ('
                        . 'sked_event_members.owner = 0 AND sked_events.id NOT IN ('
                            . 'SELECT sked_event_id FROM sked_event_members WHERE member_id = :member_id'
                        . ')'
                    . '))';
            } else {
                $strReturn .= ' INNER JOIN sked_event_members ON sked_event_members.sked_event_id = sked_events.id'
                    . ' AND sked_event_members.member_id = :member_id';
            }
            $this->aQueryParams[':member_id'] = $this->iMemberId;
        }

        return $strReturn;
    }

    /**
     *
     */
    private function queryWhereNotExpired()
    {
        return ' ' . ($this->iMemberId && $this->bPublic ? 'AND' : 'WHERE')
            . ' (sked_events.ends_at IS NULL OR sked_events.ends_at > :date_start)';
    }

    /**
     *
     */
    private function queryGroupAndOrder()
    {
        return ' GROUP BY sked_events.id ORDER BY session_at ASC';
    }

    /**
     * Execute the query with PDO and return results.
     *
     * @param string $strQuery SQL query.
     * @param array $aParams Array of parameters to bind.
     * @return array|int|bool
     */
    private function queryPDO(string $strQuery, array $aParams = [])
    {
        $oStmt = $this->oConnector->prepare($strQuery);
        if (!$oStmt->execute($aParams))
            throw new \Exception(__METHOD__ . ' - ' . $oStmt->errorInfo()[2]);

        list($strMethod) = explode(' ', trim($strQuery));
        switch (strtoupper($strMethod)) {

            case 'SELECT':
                return $oStmt->fetchAll(\PDO::FETCH_ASSOC);
                break;

            case 'INSERT':
                return $this->oConnector->lastInsertId();
                break;

            case 'UPDATE':
                return $aParams[':id_value'];
                break;

            case 'DELETE':
            default:
                return true;
                break;
        }
    }

    /**
     * Persist event data to the database.
     *
     * @param array $aData Array of data to persist.
     * @return int On success.
     * @throws \Exception On failure.
     */
    protected function saveEvent(array $aData)
    {
        // Set up binding params
        $aValues = $aExecParams = [];
        $bUpdating = false;

        if ($aData['id'] ?? false) {
            $bUpdating = true;
            $aExecParams[':id_value'] = $aData['id'];
            unset($aData['id']);
        }

        foreach ($aData as $strKey => $mValue) {
            $strValueParam = ':' . $strKey . '_value';
            $aValues[] = $bUpdating
                ? '`' . $strKey . '` = ' . $strValueParam
                : $strValueParam;
            $aExecParams[$strValueParam] = $mValue;
        }

        $strQuery = $bUpdating
            ? 'UPDATE sked_events SET ' . implode(',', $aValues)
                . ' WHERE `id` = :id_value'
            : 'INSERT INTO sked_events (`' . implode('`,`', array_keys($aData))
                . '`) VALUES (' . implode(',', $aValues) . ')';
        return $this->queryPDO($strQuery, $aExecParams);
    }

    /**
     * Fetch sked_event_members from the database.
     *
     * @param int $iEventId
     * @return array
     */
    public function fetchEventMembers(int $iEventId)
    {
        return $this->queryPDO(
            'SELECT * FROM `sked_event_members` WHERE `sked_event_id` = ?',
            [$iEventId]
        );
    }

    /**
     * Persist event member data to the database.
     *
     * @param int $iEventId Event that owns the tags.
     * @param array $aMembers Array of data to persist.
     * @return bool Success/failure.
     */
    protected function saveEventMembers(int $iEventId, array $aMembers)
    {
        if (!empty($aMembers)) {
            $aExecParams[':sked_event_id'] = $iEventId;
            $strQuery = 'INSERT IGNORE INTO `sked_event_members`
                (`sked_event_id`, `member_id`, `owner`, `lead_time`, `created_at`) VALUES ';

            $aValueSets = [];
            foreach ($aMembers as $iMemberId => $aSettings) {
                $strMemberParam = ':member_id_' . $iMemberId;
                $strOwnerParam = ':owner_' . $iMemberId;
                $strLeadTimeParam = ':lead_time_' . $iMemberId;
                $aValueSets[] = '(:sked_event_id, ' . $strMemberParam . ', '
                    . $strOwnerParam . ', ' . $strLeadTimeParam . ', NOW())';
                $aExecParams[$strMemberParam] = $iMemberId;
                $aExecParams[$strOwnerParam] = $aSettings['owner'] ?? 0;
                $aExecParams[$strLeadTimeParam] = $aSettings['lead_time'] ?? 0;
            }

            $strQuery .= implode(',', $aValueSets) . ' ON DUPLICATE KEY UPDATE'
                . ' `owner` = VALUES(`owner`), `lead_time` = VALUES(`lead_time`)';

            $this->queryPDO($strQuery, $aExecParams);
        }

        return true;
    }

    /**
     * Fetch sked_event_tags from the database.
     *
     * @param int $iEventId
     * @return array
     */
    public function fetchEventTags(int $iEventId)
    {
        return $this->queryPDO(
            'SELECT * FROM `sked_event_tags` WHERE `sked_event_id` = ?',
            [$iEventId]
        );
    }

    /**
     * Persist event tag data to the database.
     *
     * @param int $iEventId Event that owns the tags.
     * @param array $aTags Array of data to persist.
     * @return bool true On success.
     * @throws \Exception On failure.
     */
    protected function saveEventTags(int $iEventId, array $aTags)
    {
        // Delete existing tags
        $this->queryPDO(
            'DELETE FROM `sked_event_tags` WHERE `sked_event_id` = ?',
            [$iEventId]
        );

        // Create new tags
        if (!empty($aTags)) {
            $aValueSets = [];
            $aExecParams[':sked_event_id'] = $iEventId;
            $strQuery = 'INSERT INTO `sked_event_tags` (`sked_event_id`, `tag_id`, `value`, `created_at`) VALUES ';
            foreach ($aTags as $iTagId => $mTagValue) {
                $strTagParam = ':tag_id_' . $iTagId;
                $strValueParam = ':value_' . $iTagId;
                $aValueSets[] = '(:sked_event_id, ' . $strTagParam . ', ' . $strValueParam . ', NOW())';
                $aExecParams[$strTagParam] = $iTagId;
                $aExecParams[$strValueParam] = $mTagValue;
            }
            $strQuery .= implode(',', $aValueSets);

            $this->queryPDO($strQuery, $aExecParams);
        }

        return true;
    }

}
