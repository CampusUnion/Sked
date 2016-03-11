<?php

namespace CampusUnion\Sked;

class SkeForm {

    /** @var CampusUnion\Sked\SkeVent Event object for populating defaults. */
    protected $skeVent;

    /** @var string $strAction Form submit URL. */
    protected $strAction;

    /** @var string $strBeforeInput HTML code to print before each input. */
    protected $strBeforeInput = '<p>';

    /** @var string $strAfterInput HTML code to print after each input. */
    protected $strAfterInput = '</p>';

    /** @var array $aAttribs HTML element attributes. */
    protected $aAttribs = [];

    /**
     * Init the form.
     *
     * @param array $aOptions Optional array of config options.
     * @param CampusUnion\Sked\SkeVent $skeVent Event object for populating form defaults.
     */
    public function __construct(array $aOptions =[], SkeVent $skeVent = null)
    {
        $this->setBeforeInput($aOptions)
            ->setAfterInput($aOptions)
            ->setAttribs($aOptions)
            ->setSkeVent($skeVent);
    }

    /**
     * Set the HTML code to print before each input.
     *
     * @param string|array $mBeforeInput HTML code to print before each input.
     *                     Passing an array of options by reference allows this
     *                     function to remove the beforeInput from the original array.
     * @return $this
     */
    public function setBeforeInput(&$mBeforeInput)
    {
        if (is_array($mBeforeInput)) {
            $strBeforeInput = $mBeforeInput['beforeInput'] ?? null;
            unset($mBeforeInput['beforeInput']);
        } else {
            $strBeforeInput = $mBeforeInput;
        }
        if ($strBeforeInput)
            $this->strBeforeInput = $strBeforeInput;

        return $this;
    }

    /**
     * Set the HTML code to print before each input.
     *
     * @param string|array $mAfterInput HTML code to print before each input.
     *                     Passing an array of options by reference allows this
     *                     function to remove the beforeInput from the original array.
     * @return $this
     */
    public function setAfterInput($mAfterInput)
    {
        if (is_array($mAfterInput)) {
            $strAfterInput = $mAfterInput['beforeInput'] ?? null;
            unset($mAfterInput['beforeInput']);
        } else {
            $strAfterInput = $mAfterInput;
        }
        if ($strAfterInput)
            $this->strAfterInput = $strAfterInput;

        return $this;
    }

    /**
     * Set the HTML element attributes.
     *
     * @param array The HTML element attributes.
     * @return $this
     */
    public function setAttribs(array $aAttribs)
    {
        if (!isset($aAttribs['method']))
            $aAttribs['method'] = 'POST';
        $aAttribs['class'] = isset($aAttribs['class'])
            ? $aAttribs['class'] . ' sked-form' : 'sked-form';
        $this->aAttribs = $aAttribs;
        return $this;
    }

    /**
     * Save event object for populating defaults.
     *
     * @param array|CampusUnion\Sked\SkeVent Event object for populating defaults.
     * @return $this
     */
    public function setSkeVent($skeVent)
    {
        if (is_array($skeVent))
            $skeVent = new SkeVent($skeVent);
        $this->skeVent = $skeVent;
        return $this;
    }

    /** @return array List of form fields. */
    public static function getFieldDefinitions()
    {
        // Set up options for 'duration'
        $aDurationOptions = [];
        foreach (range(15, 6 * 60, 15) as $iOption) {
            $strLabel = '';
            $iHours = floor($iOption / 60);
            $iMinutes = ($iOption % 60);

            if ($iHours)
                $strLabel .= sprintf('%d hr', $iHours);
            if ($iMinutes)
                $strLabel .= sprintf(' %d min', $iMinutes);

            $aDurationOptions[$iOption] = trim($strLabel);
        }

        // List fields
        return [
            'id' => [
                'type' => 'hidden',
            ],
            'label' => [
                'attribs' => [
                    'label' => 'Description',
                    'maxlength' => 255,
                ],
            ],
            'starts_at' => [
                'attribs' => [
                    'label' => 'Starts At',
                //     'class' => 'datetime-picker',
                ],
            ],
            'duration' => [
                'type' => 'select',
                'options' => $aDurationOptions,
                'attribs' => [
                    'label' => 'Duration',
                ],
            ],
            'ends_at' => [
                'attribs' => [
                    'label' => 'Repeat Until',
                //     'class' => 'datetimepicker',
                ],
            ],
            'frequency' => [
                'type' => 'select',
                'options' => range(1, 31),
                'attribs' => [
                    'label' => 'Repeat every',
                //     'disabled' => true,
                ],
            ],
            'interval' => [
                'type' => 'select',
                'options' => [
                    SkeVent::INTERVAL_DAILY => 'day',
                    SkeVent::INTERVAL_WEEKLY => 'week',
                    SkeVent::INTERVAL_MONTHLY => 'month',
                ],
                // 'attribs' => [
                //     'id' => 'interval',
                //     'disabled' => true,
                // ],
            ],
            'weekdays' => [
                'type' => 'checkbox',
                'options' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'attribs' => [
                    'label' => 'On',
                    'multi' => true,
                //     'class' => $strCheckFieldType . '-inline weekday'
                ],
            ],
        ];
    }

    /**
     * Get inputs for SkeVent form.
     *
     * @return array
     */
    public function inputs()
    {
        $aReturn = [];
        foreach (static::getFieldDefinitions() as $strFieldName => $aField) {
            $skeFormInput = new SkeFormInput(
                $strFieldName,
                $aField['type'] ?? 'text',
                $aField['options'] ?? [],
                $aField['attribs'] ?? []
            );
            // Set value for existing event
            if ($this->skeVent)
                $skeFormInput->setValue($this->skeVent->getProperty($strFieldName));
            $aReturn[] = $skeFormInput;
        }
        return $aReturn;
    }

    /**
     * Render the entire form.
     *
     * @return string
     */
    public function __toString()
    {
        $strHtml = '<form ' . $this->renderAttribs() . '>';
        $strHtml .= '<input type="hidden" name="sked_form" value="1">';
        foreach ($this->inputs() as $oInput) {
            // Start the repeating-event section
            if ('ends_at' === $oInput->getName())
                $strHtml .= '<h3>For repeating events only</h3>';

            // The "interval" field must immediately follow the "frequency" field.
            // The "id" field is hidden.
            if (!in_array($oInput->getName(), ['interval', 'id']))
                $strHtml .= $this->strBeforeInput;
            $strHtml .= $oInput;
            // The "frequency" field is immediately followed by the "interval" field.
            // The "id" field is hidden.
            if (!in_array($oInput->getName(), ['frequency', 'id']))
                $strHtml .= $this->strAfterInput;
        }
        $strHtml .= '<button type="submit">Submit</button>';
        return $strHtml . '</form>';
    }

    /**
     * Render HTML element attributes.
     *
     * @return string HTML
     */
    protected function renderAttribs()
    {
        $strHtml = '';
        foreach ($this->aAttribs as $strKey => $mValue) {
            $strHtml .= is_bool($mValue)
                ? ($mValue ? $strKey : '') // just the attribute name for booleans
                : $strKey . '="' . $mValue . '"'; // otherwise key="value"
        }
        return $strHtml;
    }

}
