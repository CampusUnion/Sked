<?php

namespace CampusUnion\Sked\Database;

use CampusUnion\Sked\Sked;
use CampusUnion\Sked\SkeVent;

abstract class SkeModel {

    use \CampusUnion\Sked\ValidatesDates;

    /** @var array $aTags Limit search results to events with certain tags. */
    protected $aTags;

    /** @var bool $bAdjustForLeadTime Used for sending reminders ahead of time. */
    protected $bAdjustForLeadTime = false;

    /** @var bool $bPublicEvents Retrieve only public events? (those without an owner) */
    protected $bPublic = false;

    /** @var int $iMemberId Whose events are we looking for? */
    protected $iMemberId;

    /** @var int $iTimezoneOffset Optional timezone adjustment. */
    protected $iTimezoneOffset = 0;

    /** @var mixed $oConnector Database connector. */
    protected $oConnector;

    /**
     * Init the data connector and other options.
     *
     * @param array $aOptions
     */
    public function __construct(array $aOptions)
    {
        if (isset($aOptions['timezone_offset']))
            $this->iTimezoneOffset = $aOptions['timezone_offset'];
    }

    /**
     * Retrieve an event from the database.
     *
     * @param int $iId
     * @return array
     */
    abstract public function find(int $iId);

    /**
     * Build the events query for today.
     *
     * @param string $strDateStart Datetime that today starts.
     * @param string $strDateEnd Datetime that today ends.
     * @return array
     */
    abstract protected function queryDay(string $strDateStart, string $strDateEnd);

    /** @return bool Begin a database transaction. */
    abstract protected function beginTransaction();

    /** @return bool Commit a database transaction. */
    abstract protected function commitTransaction();

    /** @return bool Roll back a database transaction. */
    abstract protected function rollBackTransaction();

    /**
     * End a database transaction.
     *
     * @param bool $bSuccess Did the queries within the transaction succeed?
     * @return bool
     */
    protected function endTransaction(bool $bSuccess)
    {
        return $bSuccess ? $this->commitTransaction() : $this->rollBackTransaction();
    }

    /**
     * Persist event data to the database.
     *
     * @param array $aData Array of data to persist.
     * @return int On success.
     * @throws \Exception On failure.
     */
    abstract protected function saveEvent(array $aData);

    /**
     * Fetch sked_event_members from the database.
     *
     * @param int $iEventId
     * @return array
     */
    abstract public function fetchEventMembers(int $iEventId);

    /**
     * Persist event member data to the database.
     *
     * @param int $iEventId Event that owns the tags.
     * @param array $aMembers Array of data to persist.
     * @return bool Success/failure.
     */
    abstract protected function saveEventMembers(int $iEventId, array $aMembers);

    /**
     * Fetch sked_event_tags from the database.
     *
     * @param int $iEventId
     * @return array
     */
    abstract public function fetchEventTags(int $iEventId);

    /**
     * Persist event tag data to the database.
     *
     * @param int $iEventId Event that owns the tags.
     * @param array $aTags Array of data to persist.
     * @return bool Success/failure.
     */
    abstract protected function saveEventTags(int $iEventId, array $aTags);

    /**
     * Fetch today's event sessions from the database.
     *
     * @param string $strDate Date of event sessions to fetch.
     * @return array
     */
    public function fetch(string $strDate)
    {
        // Filter input
        $this->validateDate($strDate);
        $strDateStart = $strDate . ' 00:00:00';
        if ($this->iTimezoneOffset) {
            $strDateStart = date(
                'Y-m-d H:i:s',
                strtotime(
                    $strDateStart . ($this->iTimezoneOffset < 0 ? ' +' : ' ')
                        . -$this->iTimezoneOffset . ' hours'
                )
            );
        }
        $strDateEnd = date('Y-m-d H:i:s', strtotime($strDateStart . ' + 1 day - 1 second'));

        // Get results and sort by time
        $aResults = $this->queryDay($strDateStart, $strDateEnd);
        usort($aResults, function($aResult1, $aResult2) {
            return $aResult1['session_at'] <=> $aResult2['session_at']
                ?: $aResult1['starts_at'] <=> $aResult2['starts_at'];
        });
        return $aResults;
    }

    /**
     * Fetch current event sessions from the database.
     *
     * @param bool $bAdjustForLeadTime Used for sending reminders ahead of time.
     * @return array
     */
    public function fetchCurrent(bool $bAdjustForLeadTime = false)
    {
        $this->bAdjustForLeadTime = $bAdjustForLeadTime;
        return $this->queryMoment();
    }

    /**
     * Whose events are we looking for?
     *
     * @param int $iMemberId
     * @return $this
     */
    public function forMember(int $iMemberId = null)
    {
        $this->iMemberId = $iMemberId;
        return $this;
    }

    /**
     * Limit search results to events with certain tags.
     *
     * @param array $aTags The required tags.
     * @return $this
     */
    public function withTags(array $aTags)
    {
        $this->aTags = array_filter($aTags);
        return $this;
    }

    /**
     * Retrieve only public events? (those without an owner)
     *
     * @param bool $bPublic
     * @return $this
     */
    public function public(bool $bPublic = true)
    {
        $this->bPublic = $bPublic;
        return $this;
    }

    /**
     * Persist data to the database.
     *
     * @param CampusUnion\Sked\SkeVent $skeVent Passed by reference.
     * @return int|bool Success/failure.
     */
    public function save(SkeVent &$skeVent)
    {
        $mReturn = false;

        // Validate
        if ($this->validateEvent($skeVent)) {
            $aValues = $skeVent->toArray(false);
            unset($aValues['lead_time_num'], $aValues['lead_time_unit']);

            // Run transaction
            $this->beginTransaction();
            try {
                $mReturn = $this->saveEvent($aValues);
                $this->saveEventTags($mReturn, $skeVent->getTags());
                $this->saveEventMembers($mReturn, $skeVent->getMembers());
            } catch (\Exception $e) {
                $mReturn = false;
                $skeVent->addError(2, $e->getMessage());
            }
            $this->endTransaction((bool)$mReturn);
        }

        return $mReturn;
    }

    /**
     * Validate the event data.
     *
     * If errors are found, adds them to the SkeVent object.
     *
     * @param CampusUnion\Sked\SkeVent $skeVent Passed by reference.
     * @return bool Valid/invalid.
     */
    protected function validateEvent(SkeVent &$skeVent)
    {
        $bValid = true;
        $skeVent->resetErrors();
        $aData = $skeVent->toArray();

        // Check required fields && valid options
        foreach (Sked::form()->getFieldDefinitions() as $strKey => $aDefinition) {

            // Required
            if (!isset($aData[$strKey]) && ($aDefinition['required'] ?? false) && !isset($aData['id'])) {
                $bValid = false;
                $skeVent->addError(
                    $strKey,
                    'The "' . $aDefinition['attribs']['label'] . '" field is required.'
                );

            // Valid option
            } elseif (!$this->validateOption($aData[$strKey] ?? null, $aDefinition['options'] ?? null)) {
                $bValid = false;
                $skeVent->addError(
                    $strKey,
                    'An invalid ' . $strKey . ' option was given.'
                );
            }
        }

        // Check reminder fields - should both be present, or neither
        if (isset($aData['lead_time_num']) || isset($aData['lead_time_unit'])) { // one is set
            if (!isset($aData['lead_time_num']) || !isset($aData['lead_time_unit'])) { // but not both
                $bValid = false;
                $skeVent->addError(
                    isset($aData['lead_time_num']) ? 'lead_time_unit' : 'lead_time_num',
                    'Both Reminder fields should be filled out (or clear them both).'
                );
            }
        }

        // Check recurring-event fields
        if (isset($aData['ends_at'])) {
            if (!isset($aData['frequency'])) {
                $bValid = false;
                $skeVent->addError(
                    'frequency',
                    'A frequency is required for recurring events.'
                );
            }
            if (!isset($aData['interval']) || SkeVent::INTERVAL_ONCE === $aData['interval']) {
                $bValid = false;
                $skeVent->addError(
                    'interval',
                    'An interval (daily, weekly, etc.) is required for recurring events.'
                );
            }
        }
        if (isset($aData['frequency']) && !isset($aData['interval'])) {
            $bValid = false;
            $skeVent->addError(
                'interval',
                'An interval (daily, weekly, etc.) is required when a frequency is selected.'
            );
        }
        if (isset($aData['interval'])) {
            if (SkeVent::INTERVAL_DAILY === $aData['interval'] && isset($aData['weekdays'])) {
                $bValid = false;
                $skeVent->addError(
                    'weekdays',
                    'A day of the week cannot be selected for daily events.'
                );
            }
        }

        return $bValid;
    }

    /**
     * Check if value is a valid option.
     *
     * If either value or options is null, return true (no validation rules).
     *
     * @param int|string $mValue The value in question.
     * @param array $aOptions The list of valid options.
     * @return bool
     */
    protected function validateOption($mValue, array $aOptions = null)
    {
        return is_null($mValue) || is_null($aOptions)
            || array_key_exists($mValue, $aOptions);
    }

}
