<?php

namespace CampusUnion\Sked;

class SkeDate {

    use ValidatesDates;

    /** @var \CampusUnion\Sked\Database\SkeModel $oModel Data layer. */
    protected $oModel;

    /** @var string $strDate The date of focus YYYY-MM-DD. */
    protected $strDate;

    /**
     * Init the date object.
     *
     * @param string $strDate YYYY-MM-DD
     * @param \CampusUnion\Sked\Database\SkeModel $oModel Data layer.
     */
    public function __construct(string $strDate, Database\SkeModel $oModel)
    {
        $this->validateDate($strDate);
        $this->strDate = $strDate;
        $this->oModel = $oModel;
    }

    /**
     * Get today's events.
     *
     * @param int $iMemberId Unique ID of the event participant.
     * @return array
     */
    public function skeVents(int $iMemberId = null)
    {
        $aReturn = [];
        $this->oModel->forMember($iMemberId);
        foreach ($this->oModel->fetch($this->strDate) as $aEvent)
            $aReturn[] = new SkeVent($aEvent);
        return $aReturn;
    }

    /**
     * Format the date using the given pattern.
     *
     * @param string $strFormat PHP date format.
     * @return string
     */
    public function format(string $strFormat = 'Y-m-d')
    {
        return date($strFormat, strtotime($this->strDate));
    }

}
