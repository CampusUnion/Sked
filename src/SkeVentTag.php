<?php

namespace CampusUnion\Sked;

class SkeVentTag implements \JsonSerializable {

    /** @var array $aProperties List of database fields/properties. */
    protected $aProperties = [];

    /**
     * Init the event object.
     *
     * @param array $aProperties Array of database fields/properties.
     */
    public function __construct(array $aProperties = [])
    {
        $this->aProperties = $aProperties;
    }

    /**
     * Allow magic access to event properties.
     *
     * @param string $strKey The name of the property to get.
     * @return mixed
     */
    public function __get(string $strKey)
    {
        return $this->aProperties[$strKey] ?? null;
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
     * Convert to an array of database properties.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->aProperties;
    }

    /**
     * Render the tag value.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }

    /**
     *
     */
    public function jsonSerialize()
    {
        return (string)$this->value;
    }

}