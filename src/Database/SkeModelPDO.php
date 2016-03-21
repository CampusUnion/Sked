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
        $strDsn = $aOptions['driver'] . ':host=' . $aOptions['host'] . ';dbname=' . $aOptions['dbname'];
        $this->oConnector = new \PDO($strDsn, $aOptions['user'], $aOptions['pass']);
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
                "' . substr($strDateStart, 0, 10) . '",
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

        $strQuery .= ')';

        // PDO
        $oSelect = $this->oConnector->prepare($strQuery);
        $aParams = [
            ':member_id' => $iMemberId,
            ':date_start' => $strDateStart,
            ':date_start_NOTIME' => date('Y-m-d', strtotime($strDateStart)),
            ':date_start_DAYOFMONTH' => date('d', strtotime($strDateStart)),
            ':date_start_YEARMONTH' => date('Ym', strtotime($strDateStart)),
            ':date_end' => $strDateEnd,
        ];
        if (!$oSelect->execute($aParams))
            throw new \Exception(__METHOD__ . ' - ' . $oSelect->errorInfo()[2]);
        return $oSelect->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Persist event data to the database.
     *
     * @param array $aData Array of data to persist.
     * @return int true On success.
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

        $oStmt = $this->oConnector->prepare($bUpdating
            ? 'UPDATE sked_events SET ' . implode(',', $aValues)
                . ' WHERE `id` = :id_value'
            : 'INSERT INTO sked_events (`' . implode('`,`', array_keys($aData))
                . '`) VALUES (' . implode(',', $aValues) . ')'
        );

        if (!$oStmt->execute($aExecParams))
            throw new \Exception($oStmt->errorInfo()[2]);
        else
            return $aExecParams[':id_value'] ?? $this->oConnector->lastInsertId();
    }

    /**
     * Fetch sked_event_tags from the database.
     *
     * @param int $iEventId
     * @return array
     */
    public function fetchEventTags(int $iEventId)
    {
        $oSelect = $this->oConnector->prepare('SELECT * FROM `sked_event_tags` WHERE `sked_event_id` = ?');
        if (!$oSelect->execute([$iEventId]))
            throw new \Exception($oSelect->errorInfo()[2]);
        return $oSelect->fetchAll(\PDO::FETCH_ASSOC);
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
        $oStmt = $this->oConnector->prepare('DELETE FROM `sked_event_tags` WHERE `sked_event_id` = ?');
        if (!$oStmt->execute([$iEventId]))
            throw new \Exception($oStmt->errorInfo()[2]);

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

            $oStmt = $this->oConnector->prepare($strQuery);
            if (!$oStmt->execute($aExecParams))
                throw new \Exception($oStmt->errorInfo()[2]);
        }

        return true;
    }

}
