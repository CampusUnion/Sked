<?php

namespace CampusUnion\Sked\Database;

use CampusUnion\Sked\SkeForm;
use CampusUnion\Sked\SkeVent;

abstract class SkeModel {

    use \CampusUnion\Sked\ValidatesDates;

    /** @var bool $bAdjustForLeadTime Used for sending reminders ahead of time. */
    protected $bAdjustForLeadTime = false;

    /** @var int $iMemberId Whose events are we looking for? */
    protected $iMemberId;

    /** @var mixed $oConnector Database connector. */
    protected $oConnector;

    /**
     * Init the data connector.
     *
     * @param array $aOptions
     */
    abstract public function __construct(array $aOptions);

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
     * @return int|bool Success/failure.
     */
    abstract protected function saveEvent(array $aData);

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
     * @param int $iTimezoneOffset Optional timezone adjustment.
     * @return array
     */
    public function fetch(string $strDate, int $iTimezoneOffset = 0)
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
    public function forMember(int $iMemberId)
    {
        $this->iMemberId = $iMemberId;
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
                $this->endTransaction((bool)$mReturn);
            } catch (\Exception $e) {
                $mReturn = false;
                $this->endTransaction(false);
                $skeVent->addError(2, $e->getMessage());
            }
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
        foreach (SkeForm::getFieldDefinitions() as $strKey => $aDefinition) {

            // Required
            if (($aDefinition['required'] ?? false) && !isset($aData[$strKey])) {
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

        // Check recurring-event fields
        if (isset($aData['ends_at'])) {
            if (!isset($aData['frequency'])) {
                $bValid = false;
                $skeVent->addError(
                    'frequency',
                    'A frequency is required for recurring events.'
                );
            }
            if (!isset($aData['interval'])) {
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
