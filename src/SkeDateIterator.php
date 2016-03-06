<?php

namespace CampusUnion\Sked;

class SkeDateIterator implements Iterator {

    use ValidatesDates;

    /** @var \CampusUnion\Sked\Database\SkeModel $oModel Data layer. */
    protected $oModel;

    /** @var string $strStart Start date YYYY-MM-DD. */
    protected $strStart;

    /** @var string $strEnd End date YYYY-MM-DD. */
    protected $strEnd;

    /** @var int $iCurrentKey Current iteration key. */
    protected $iCurrentKey;

    /** @var string $strCurrentValue Current iteration date YYYY-MM-DD. */
    protected $strCurrentValue;

    /**
     * Init the iterator with start & end values.
     *
     * @param \CampusUnion\Sked\Database\SkeModel $oModel Data layer.
     * @param string $strStart Start date YYYY-MM-DD.
     * @param string $strEnd End date YYYY-MM-DD.
     */
    public function __construct(Database\SkeModel $oModel, string $strStart = null, string $strEnd = null)
    {
        $this->validateDate([$strStart, $strEnd]);

        $this->oModel = $oModel;
        $this->strStart = $strStart ?: date('Y-m-d');
        $this->strEnd = $strEnd;
    }

    /** @return SkeDate Get current iteration. */
    public function current()
    {
        return new SkeDate($this->strCurrentValue, $this->oModel);
    }

    /** @return int Get current key. */
    public function key()
    {
        return $this->iCurrentKey;
    }

    /** Advance to the next iteration. */
    public function next()
    {
        $this->iCurrentKey++;
        $this->strCurrentValue = strftime('%Y-%m-%d', strtotime($this->strCurrentValue . ' +1 day'));
    }

    /** Go to the beginning. */
    public function rewind()
    {
        $this->iCurrentKey = 0;
        $this->strCurrentValue = $this->strStart;
    }

    /** @return bool Are we done yet? */
    public function valid()
    {
        return $this->strCurrentValue >= $this->strStart
            && (is_null($this->strEnd) || $this->strCurrentValue <= $this->strEnd)
            && $this->iCurrentKey <= 365 * 10; // 10-yr limit to prevent it from going on forever
    }

}
