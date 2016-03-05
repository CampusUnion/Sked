<?php

namespace CampusUnion\Sked;

class Sked {

    /** @var CampusUnion\Sked\Database\SkeModel Data layer. */
    protected $oModel;

    /**
     *
     * @param array $aOptions Config options.
     */
    public function __construct(array $aOptions)
    {
        // Validate input
        if (!isset($aOptions['data_connector']['name']))
            throw new Exception('No data_connector[name] passed to ' . __METHOD__);
        if (!isset($aOptions['data_connector']['options']) || !is_array($aOptions['data_connector']['options']))
            throw new Exception('Must pass an array of data_connector[options] to ' . __METHOD__);

        $strModelClass = 'Database\SkeModel' . ucfirst($aOptions['data_connector']['name']);
        $this->oModel = new $strModelClass($aOptions['data_connector']['options']);
    }

    /**
     *
     */
    public function skeDates($mStart, $mEnd = null)
    {
        return new SkeDateIterator($this->oModel, $mStart, $mEnd);
    }

}
