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

    /** @var array $aErrors List of validation errors. */
    protected $aErrors = [];

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
     * Get an event property by key.
     *
     * @param string $strKey The name of the property to get.
     * @return mixed
     */
    public function getProperty(string $strKey)
    {
        if ('weekdays' === $strKey) {
            $aReturn = array_keys(array_intersect_key(
                $this->aProperties,
                array_flip(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
            ), 1);
        }
        return $aReturn ?? $this->aProperties[$strKey] ?? null;
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
        // Sanitize
        $aReturn = array_filter($this->aProperties, function($mValue, $strKey) {
            return !empty($mValue) && '-' !== $mValue && (
                'created_at' === $strKey || 'updated_at' === $strKey
                || in_array($strKey, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                || array_key_exists($strKey, SkeForm::getFieldDefinitions())
            );
        }, ARRAY_FILTER_USE_BOTH);
        if (isset($aReturn['starts_at']))
            $aReturn['starts_at'] = date('Y-m-d H:i:s', strtotime($aReturn['starts_at']));
        if (isset($aReturn['ends_at']))
            $aReturn['ends_at'] = date('Y-m-d H:i:s', strtotime($aReturn['ends_at']));

        return $aReturn;
    }

}
