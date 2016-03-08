<?php

namespace CampusUnion\Sked;

class Sked {

    use ValidatesDates;

    /** @var CampusUnion\Sked\Database\SkeModel Data layer. */
    protected $oModel;

    /**
     * Init Sked.
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

        $strModelClass = 'CampusUnion\Sked\Database\SkeModel' . ucfirst($aOptions['data_connector']['name']);
        $this->oModel = new $strModelClass($aOptions['data_connector']['options']);
    }

    /**
     * Get dates iterator.
     *
     * @param string $strStartDate
     * @param string $strEndDate
     * @return CampusUnion\Sked\SkeDateIterator
     */
    public function skeDates(string $strStartDate = null, string $strEndDate = null)
    {
        $this->validateDate([$strStartDate, $strEndDate]);
        return new SkeDateIterator($this->oModel, $strStartDate, $strEndDate);
    }

    /**
     * Persist event data to the database.
     *
     * @param CampusUnion\Sked\SkeVent $skeVent
     * @return bool
     */
    public function save(SkeVent $skeVent)
    {
        return $this->oModel->save($skeVent->toArray());
    }

    /**
     * Shortcut for skeDates(). If you use this, you're boring.
     *
     * @param string $strStartDate
     * @param string $strEndDate
     * @return CampusUnion\Sked\SkeDateIterator
     */
    public function dates(string $strStartDate, string $strEndDate = null)
    {
        return $this->skeDates($strStartDate, $strEndDate);
    }

    /**
     * Get the HTML form.
     *
     * @param array $aOptions Optional array of config options.
     * @return CampusUnion\Sked\SkeForm
     */
    public function form(array $aOptions = [])
    {
        return new SkeForm($aOptions);
    }

}
