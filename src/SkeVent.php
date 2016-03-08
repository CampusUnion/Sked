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

    /** @var array $aProperties Array of properties retrieved from the database. */
    protected $aProperties;

    /**
     * Init the event object.
     *
     * @param array $aProperties Array of database fields/properties.
     */
    public function __construct(array $aProperties = [])
    {
        // Parse weekday values
        if (isset($aProperties['weekdays'])) {
            foreach ((array)$aProperties['weekdays'] as $strDay)
                $aProperties[$strDay] = 1;
            unset($aProperties['weekdays']);
        }

        // Set defaults
        if (!isset($aProperties['created_at']))
            $aProperties['created_at'] = date('Y-m-d H:i:s');

        $this->aProperties = $aProperties;
    }

    /**
     * Get an event property by key.
     *
     * @param string $strKey The name of the property to get.
     * @return mixed
     */
    public function getProperty(string $strKey)
    {
        return $this->aProperties[$strKey] ?? null;
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
     * Allow magic setting of event properties.
     *
     * @param string $strKey The name of the property to set.
     * @param mixed $mValue New value for the property.
     */
    public function __set(string $strKey, $mValue)
    {
        $this->aProperties[$strKey] = $mValue;
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
     * @return array
     */
    public function toArray()
    {
        return $this->aProperties;
    }

}
