<?php

namespace CampusUnion\Sked;

class SkeVent {

    /** @var array $aProperties Array of properties retrieved from the database. */
    protected $aProperties;

    /**
     * Init the date object.
     *
     * @param array $aProperties Array of properties retrieved from the database.
     */
    public function __construct(array $aProperties = [])
    {
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

}
