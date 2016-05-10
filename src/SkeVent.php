<?php

namespace CampusUnion\Sked;

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
    const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** @var array $aErrors List of validation errors. */
    protected $aErrors = [];

    /** @var array $aMembers Array of sked_event_members. */
    protected $aMembers = null; // "null" shows we haven't checked DB yet

    /** @var array $aProperties Array of properties retrieved from the database. */
    protected $aProperties = [];

    /** @var array $aTags Array of sked_event_tags. */
    protected $aTags = null; // "null" shows we haven't checked DB yet

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
        switch ($strKey) {

            // Parse weekday values
            case 'weekdays':
                $aValues = (array)$mValue;
                foreach (self::WEEKDAYS as $strDay)
                    $this->setProperty($strDay, (int)in_array($strDay, $aValues));
                break;

            // Process tags
            case 'tags':
                $this->setTags($mValue);
                break;

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
        $bRepeats = $aProperties['repeats'] ?? false;
        foreach ($aProperties as $strKey => $mValue)
            $this->setProperty($strKey, $mValue);
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
        if (!is_array($this->aMembers)) {
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
     * @param int $iTimezoneOffset Optional timezone adjustment.
     * @return string
     */
    public function time(string $strFormat = 'g:ia', int $iTimezoneOffset = 0)
    {
        return date(
            $strFormat,
            strtotime($this->session_at ?? $this->starts_at) + ($iTimezoneOffset * 60 * 60)
        );
    }

    /**
     * Convert to an array of database properties.
     *
     * @param bool $bIncludeTags Should tags be included?
     * @return array
     */
    public function toArray(bool $bIncludeTags = true)
    {
        // Sanitize
        $aReturn = array_filter($this->aProperties, function($mValue, $strKey) {
            return !empty($mValue) && '-' !== $mValue && (
                in_array($strKey, ['created_at', 'updated_at'])
                || in_array($strKey, self::WEEKDAYS)
                || array_key_exists($strKey, SkeForm::getFieldDefinitions())
            );
        }, ARRAY_FILTER_USE_BOTH);
        if (isset($aReturn['starts_at']))
            $aReturn['starts_at'] = date('Y-m-d H:i:s', strtotime($aReturn['starts_at']));
        if (isset($aReturn['ends_at']))
            $aReturn['ends_at'] = date('Y-m-d H:i:s', strtotime($aReturn['ends_at']));

        // Include tags?
        if ($bIncludeTags)
            $aReturn['tags'] = $this->getTags();

        return $aReturn;
    }

}
