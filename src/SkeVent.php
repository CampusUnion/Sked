<?php

namespace CampusUnion\Sked;

use Carbon\Carbon;

class SkeVent {

    /** @var string INTERVAL_ONCE */
    const INTERVAL_ONCE = 'Once';

    /** @var string INTERVAL_DAILY */
    const INTERVAL_DAILY = '1';

    /** @var string INTERVAL_WEEKLY */
    const INTERVAL_WEEKLY = '7';

    /** @var string INTERVAL_MONTHLY */
    const INTERVAL_MONTHLY = 'Monthly';

    /** @var array WEEKDAYS */
    const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /** @var array $aErrors List of validation errors. */
    protected $aErrors = [];

    /** @var array $aMembers Array of sked_event_members. */
    protected $aMembers = null; // "null" shows we haven't checked DB yet

    /** @var array $aProperties Array of properties retrieved from the database. */
    protected $aProperties = [];

    /** @var array $aTags Array of sked_event_tags. */
    protected $aTags = null; // "null" shows we haven't checked DB yet

    /** @var int $strTimezone PHP timezone name. */
    protected $strTimezone = 'UTC';

    /** @var string $strStartsAt Save the original starts_at value for timezone manipulation. */
    protected $strStartsAt;

    /** @var string $strEndsAt Save the original ends_at value for timezone manipulation. */
    protected $strEndsAt;

    /**
     * Init the event object.
     *
     * @param array $aProperties Array of database fields/properties.
     */
    public function __construct(array $aProperties = [])
    {
        // Set defaults
        if (!isset($aProperties['created_at']))
            $aProperties['created_at'] = date('Y-m-d H:i:s');

        $this->setProperties($aProperties);
    }

    /**
     * Add a validation error.
     *
     * @param string $strKey Name of the erroneous form field.
     * @param string $strMessage Detailed error message.
     * @return $this
     */
    public function addError(string $strKey, string $strMessage)
    {
        $this->aErrors[$strKey] = $strMessage;
        return $this;
    }

    /**
     * Get error message by field name.
     *
     * @param string $strKey Name of the form field.
     * @return string Detailed error message.
     */
    public function getError(string $strKey)
    {
        return $this->aErrors[$strKey] ?? null;
    }

    /**
     * Get all error messages.
     *
     * @return array|false
     */
    public function getErrors()
    {
        return !empty($this->aErrors) ? $this->aErrors : false;
    }

    /**
     * Check for error message by field name.
     *
     * @param string $strKey Name of the form field.
     * @return bool
     */
    public function hasError(string $strKey)
    {
        return isset($this->aErrors[$strKey]);
    }

    /**
     * Check for any error messages.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->aErrors);
    }

    /**
     * Clear all error messages.
     *
     * @return $this
     */
    public function resetErrors()
    {
        $this->aErrors = [];
        return $this;
    }

    /**
     * Allow magic access to event properties.
     *
     * @param string $strKey The name of the property to get.
     * @return mixed
     */
    public function __get(string $strKey)
    {
        return $this->getProperty($strKey);
    }

    /**
     * Get an event property by key.
     *
     * @param string $strKey The name of the property to get.
     * @return mixed
     */
    public function getProperty(string $strKey)
    {
        $mReturn = null;
        switch ($strKey) {

            // Parse weekday values
            case 'weekdays':
                $mReturn = array_keys(array_intersect_key(
                    $this->aProperties,
                    array_flip(self::WEEKDAYS)
                ), 1);
                break;

            // Calculate lead time factors
            case 'lead_time_num':
            case 'lead_time_unit':
                $mReturn = $this->getLeadTimeFactor($strKey);
                break;

            default:
                $mReturn = $this->aProperties[$strKey] ?? null;
                break;
        }
        return $mReturn;
    }

    /**
     * Allow magic setting of event properties.
     *
     * @param string $strKey The name of the property to set.
     * @param mixed $mValue New value for the property.
     */
    public function __set(string $strKey, $mValue)
    {
        $this->setProperty($strKey, $mValue);
    }

    /**
     * Set an event property by key.
     *
     * @param string $strKey The name of the property to set.
     * @param mixed $mValue New value for the property.
     */
    public function setProperty(string $strKey, $mValue)
    {
        // Empty strings coming from an HTML form should be nullified
        if ('' === $mValue)
            $mValue = null;

        switch ($strKey) {

            // Parse weekday values
            case 'weekdays':
            $this->setWeekdays((array)$mValue);
                break;

            // Process tags
            case 'tags':
                $this->setTags($mValue);
                break;

            // Save datetimes & timezone for later
            case 'timezone':
                $this->strTimezone = $mValue;
                break;
            case 'starts_at':
            case 'ends_at':
                $strProperty = 'starts_at' === $strKey ? 'strStartsAt' : 'strEndsAt';
                $this->$strProperty = $mValue;
                // no break, so it falls through to default

            default:
                $this->aProperties[$strKey] = $mValue;
                break;
        }
    }

    /**
     * Set properties from array.
     *
     * @param array $aProperties
     */
    public function setProperties(array $aProperties)
    {
        foreach ($aProperties as $strKey => $mValue)
            $this->setProperty($strKey, $mValue);

        $this->adjustDependentFields();
    }

    /**
     * Set each weekday property based on an array of weekday abbreviations.
     *
     * @param array $aValues List of weekday abbreviations to activate.
     */
    protected function setWeekdays(array $aValues = [])
    {
        foreach (self::WEEKDAYS as $strDay)
            $this->setProperty($strDay, (int)in_array($strDay, $aValues));
    }

    /**
     * Adjust fields that are dependent upon other fields.
     *
     * - Reset all the recurring-event fields if not a repeating event.
     * - Adjust datetimes for timezone offsets
     */
    protected function adjustDependentFields()
    {
        // Recurring-event fields
        if (array_key_exists('repeats', $this->aProperties) && !$this->repeats) {
            $this->frequency = null;
            $this->interval = self::INTERVAL_ONCE;
            $this->setWeekdays();
            $this->ends_at = null;
        }

        // Datetimes & timezone
        if (!is_null($this->strStartsAt)) {
            $this->aProperties['starts_at'] = Carbon::parse($this->strStartsAt, $this->strTimezone)
                ->setTimezone('UTC')
                ->toDateTimeString();
        }
        if (!is_null($this->strEndsAt)) {
            $this->aProperties['ends_at'] = Carbon::parse($this->strEndsAt, $this->strTimezone)
                ->setTimezone('UTC')
                ->toDateTimeString();
        }
    }

    /**
     * Get the "num" or "unit" factor from the lead time.
     *
     * @param string $strFactor "lead_time_num" or "lead_time_unit"
     * @return int
     */
    public static function getLeadTimeFactor(string $strFactor, int $iLeadTime)
    {
        // minutes
        if ($iLeadTime < 60 || $iLeadTime % 60 !== 0) {
            $iNum = $iLeadTime;
            $iUnit = 1;
        // hours
        } elseif ($iLeadTime % (24 * 60) !== 0) {
            $iNum = $iLeadTime / 60;
            $iUnit = 60;
        // days
        } else {
            $iNum = $iLeadTime / (24 * 60);
            $iUnit = 24 * 60;
        }

        return ${'i' . ucfirst(strtolower(str_replace('lead_time_', '', $strFactor)))};
    }

    /**
     * Get associated sked_event_members.
     *
     * @return array
     */
    public function getMembers()
    {
        if (empty($this->aMembers)) {
            $this->aMembers = [];
            if ($this->id) {
                foreach (Sked::getEventMembers($this->id) as $aEventMember)
                    $this->aMembers[$aEventMember['member_id']] = $aEventMember;
            }
        }
        return $this->aMembers;
    }

    /**
     * Set associated sked_event_members.
     *
     * @param array $aEventMembers Array of SkeVentMember objects.
     * @return $this
     */
    public function setMembers(array $aEventMembers)
    {
        $this->aMembers = [];

        foreach ($aEventMembers as $iMemberId => $skeVentMember)
            $this->aMembers[$iMemberId] = $skeVentMember + ['member_id' => $iMemberId];

        return $this;
    }

    /**
     * Get the event owner ID.
     *
     * @return int
     */
    public function owner()
    {
        $iReturn = 0;

        foreach ($this->getMembers() as $iId => $aMember) {
            if (1 == $aMember['owner']) {
                $iReturn = $iId;
                break;
            }
        }

        return $iReturn;
    }

    /**
     * Get associated sked_event_tags.
     *
     * @return array
     */
    public function getTags()
    {
        if (!is_array($this->aTags) && $this->id) {
            $this->aTags = [];
            foreach (Sked::getEventTags($this->id) as $aEventTag)
                $this->aTags[$aEventTag['tag_id']] = new SkeVentTag($aEventTag);
        }
        return $this->aTags;
    }

    /**
     * Set associated sked_event_tags.
     *
     * @param array $aEventTags Array of SkeVentTag objects.
     * @return $this
     */
    public function setTags(array $aEventTags)
    {
        $this->aTags = [];

        foreach ($aEventTags as $iTagId => $skeVentTag) {
            if (!$skeVentTag instanceof SkeVentTag) {
                $skeVentTag = new SkeVentTag([
                    'tag_id' => $iTagId,
                    'value' => $skeVentTag,
                ]);
            }
            $this->aTags[$skeVentTag->tag_id] = $skeVentTag;
        }

        return $this;
    }

    /**
     * Format the time using the given pattern.
     *
     * @param string $strFormat PHP date format.
     * @param string $strTimezone PHP timezone name.
     * @return string
     */
    public function time(string $strFormat = 'g:ia', string $strTimezone = 'UTC')
    {
        return Carbon::parse($this->session_at ?? $this->starts_at)
            ->setTimezone($strTimezone)
            ->format($strFormat);
    }

    /**
     * Convert to an array of database properties.
     *
     * @param bool $bIncludeExtras Should tags & "repeats" be included?
     * @return array
     */
    public function toArray(bool $bIncludeExtras = true)
    {
        // Sanitize
        $this->adjustDependentFields();

        $aReturn = array_filter($this->aProperties, function($mValue, $strKey) {
            return !empty($mValue) && '-' !== $mValue && (
                in_array($strKey, ['created_at', 'updated_at'])
                || in_array($strKey, self::WEEKDAYS)
                || array_key_exists($strKey, Sked::form()->getFieldDefinitions())
            );
        }, ARRAY_FILTER_USE_BOTH);
        if (isset($aReturn['starts_at']))
            $aReturn['starts_at'] = date('Y-m-d H:i:s', strtotime($aReturn['starts_at']));
        if (isset($aReturn['ends_at']))
            $aReturn['ends_at'] = date('Y-m-d H:i:s', strtotime($aReturn['ends_at']));

        // Include tags?
        if ($bIncludeExtras) {
            $aReturn['tags'] = $this->getTags();
            $aReturn['repeats'] = self::INTERVAL_ONCE !== $aReturn['interval'] ? '1' : null;
        } else {
            unset($aReturn['repeats']);
        }

        return $aReturn;
    }

}
