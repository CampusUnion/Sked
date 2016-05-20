<?php

namespace CampusUnion\Sked;

class SkeDateIterator implements \Iterator {

    use ValidatesDates;

    /** @var int Limit number of days to iterate to 10 years. */
    const MAX_DATES = 365 * 10;

    /** @var string $strTimezone PHP timezone name, to alter display. */
    protected $strTimezone = 0;

    /** @var \CampusUnion\Sked\Database\SkeModel $oModel Data layer. */
    protected $oModel;

    /** @var string $strStart Start date YYYY-MM-DD. */
    protected $strStart;

    /** @var string $strEnd End date YYYY-MM-DD. */
    protected $strEnd;

    /** @var bool $bOneFullMonth Are we returning one full month? */
    protected $bOneFullMonth = false;

    /** @var int $iCurrentKey Current iteration key. */
    private $iCurrentKey;

    /** @var string $strCurrentValue Current iteration date YYYY-MM-DD. */
    private $strCurrentValue;

    /**
     * Init the iterator with start & end values.
     *
     * @param \CampusUnion\Sked\Database\SkeModel $oModel Data layer.
     * @param string $strStart Start date YYYY-MM-DD.
     * @param string|true $mEnd End date YYYY-MM-DD, or "true" to return one full month.
     */
    public function __construct(Database\SkeModel $oModel, string $strStart = null, $mEnd = null)
    {
        $this->oModel = $oModel;

        $this->validateDate($strStart);
        $this->strStart = $strStart ?: date('Y-m-d');

        // When end date is "true," it means return one full month.
        if (true === $mEnd) {
            $iStartTime = strtotime($this->strStart);
            $this->strStart = date('Y-m-01', $iStartTime);
            $this->strEnd = date('Y-m-t', $iStartTime);
            $this->bOneFullMonth = true;
        } else {
            $this->validateDate($mEnd);
            $this->strEnd = $mEnd;
        }
    }

    /**
     * Set the timezone for date display.
     *
     * @param string $strTimezone
     * @return $this
     */
    public function setTimezone(string $strTimezone)
    {
        $this->strTimezone = $strTimezone;

        return $this;
    }

    /** @return int Number of dates in the range. */
    public function count()
    {
        return $this->strEnd
            ? (int) round(
                (strtotime($this->strEnd) - strtotime($this->strStart))
                / (24 * 60 * 60)
            ) + 1 // inclusive
            : self::MAX_DATES;
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
            && $this->iCurrentKey <= self::MAX_DATES;
    }

    /**
     * Render the set of dates as a calendar.
     *
     * @return string HTML
     */
    public function __toString()
    {
        // Can this set of dates be rendered?
        if (!$this->bOneFullMonth) {
            throw new \Exception(
                __METHOD__ . ' - Only one full month can be rendered (end date must be "true").'
            );
        }

        $i = 0;
        $strHtml = '<h3 class="sked-cal-title">' . date('F', strtotime($this->strStart))
            . '</h3><table class="sked-cal"><tr>';

        for ($j = 0; $j < $this->monthPadDates(); $j++) {
            $i++;
            $strHtml .= '<td></td>';
        }

        foreach ($this as $skeDate) {
            $i++;
            $strHtml .= '<td class="sked-cal-date' . (date('Y-m-d') !== $skeDate->format('Y-m-d') ?: ' sked-cal-date-current') . '">';
                $strHtml .= '<span class="sked-cal-date-num">' . $skeDate->format('j') . '</span>';
                $strHtml .= '<ul class="sked-cal-date-list">';
                foreach ($skeDate->events() as $skeVent) {
                    $strHtml .= '<li class="sked-cal-date-event">'
                        . '<a href="#" class="sked-cal-event-link" id="skevent-' . $skeVent->id . '">'
                            . $skeVent->label
                        . '</a><span>' . $skeVent->time('g:ia', $this->strTimezone) . '</span>'
                    . '</li>';
                }
                $strHtml .= '<ul>';
            $strHtml .= '</td>';
            if (7 === $i) {
                $i = 0;
                $strHtml .= '</tr><tr>';
            }
        }

        if ($i) {
            for ($j = $i; $j < 7; $j++) {
                $strHtml .= '<td></td>';
            }
        }

        return $strHtml . '</tr></table>';
    }

    /**
     * Get number of padding dates needed at the beginning of the month's calendar view.
     *
     * @return int
     */
    public function monthPadDates()
    {
        return date('w', strtotime(date('Y-m-01', strtotime($this->strStart))));
    }

}
