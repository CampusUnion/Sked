<?php

namespace CampusUnion\Sked;

class SkeFormInput {

    /** @var array $aAttribs Array of element attributes. */
    protected $aAttribs = [];

    /** @var array $aOptions Array of input options. */
    protected $aOptions;

    /** @var bool $bHasFollower Is there an input that immediately follows this one? */
    protected $bHasFollower = false;

    /** @var bool $bIsFollower Does this input immediately follow another? */
    protected $bIsFollower = false;

    /** @var bool $bIsRecurringField Is this input related to recurring events? */
    protected $bIsRecurringField = false;

    /** @var bool $bMulti Is this a multi-element input? */
    protected $bMulti = false;

    /** @var mixed $mValue Default field value. */
    protected $mValue;

    /** @var string $strElementType Type of HTML element. */
    protected $strElementType;

    /** @var string $strErrorMessage Detailed error message. */
    protected $strErrorMessage;

    /** @var string $strLabel Label that goes before the element. */
    protected $strLabel;

    /** @var string $strName Name/ID of the field. */
    protected $strName;

    /** @var string $strSuffix Label that goes after the element. */
    protected $strSuffix;

    /**
     * Init the input object.
     *
     * @param string $strName Name/ID of the field.
     * @param string $strType Type of HTML element.
     * @param array $aOptions Array of input options.
     * @param array $aAttribs Array of element attributes.
     */
    public function __construct(string $strName, string $strType, array $aOptions = [], array $aAttribs = [])
    {
        $this->strName = $strName;
        $this->defineType($strType);

        if (isset($aAttribs['label'])) {
            $this->strLabel = $aAttribs['label'];
            unset($aAttribs['label']);
        }
        if (isset($aAttribs['suffix'])) {
            $this->strSuffix = $aAttribs['suffix'];
            unset($aAttribs['suffix']);
        }
        if (isset($aAttribs['multi'])) {
            $this->strName .= '[]';
            $this->bMulti = $aAttribs['multi'];
            unset($aAttribs['multi']);

            if ('select' === $strType)
                $aAttribs['multiple'] = true;
        }
        if (isset($aAttribs['has_follower'])) {
            $this->bHasFollower = (bool)$aAttribs['has_follower'];
            unset($aAttribs['has_follower']);
        }
        if (isset($aAttribs['is_follower'])) {
            $this->bIsFollower = (bool)$aAttribs['is_follower'];
            unset($aAttribs['is_follower']);
        }
        if (isset($aAttribs['is_recurring_field'])) {
            $this->bIsRecurringField = (bool)$aAttribs['is_recurring_field'];
            unset($aAttribs['is_recurring_field']);
        }

        $this->aAttribs += ['id' => $strName] + $aAttribs;
        if (!empty($aOptions))
            $this->aOptions = $aOptions;
    }

    /**
     * Init the HTML element type.
     *
     * @param string $strType Type of HTML element.
     */
    protected function defineType(string $strType)
    {
        switch ($strType) {
            case 'select':
            case 'textarea':
                $this->strElementType = $strType;
                break;
            case 'checkbox':
            case 'hidden':
            case 'radio':
            case 'text':
            default:
                $this->strElementType = 'input';
                $this->aAttribs['type'] = $strType;
                break;
        }
    }

    /** @return string Input type. */
    public function getType()
    {
        return 'input' === $this->strElementType ? $this->aAttribs['type'] : $this->strElementType;
    }

    /** @return string Name/ID of the field. */
    public function getName()
    {
        return $this->strName;
    }

    /** @return string Detailed error message. */
    public function getError()
    {
        return $this->strErrorMessage;
    }

    /** @return bool Check for error message. */
    public function hasError()
    {
        return !!$this->strErrorMessage;
    }

    /**
     * Set error message.
     *
     * @param string $strMessage Detailed error message.
     * @return $this
     */
    public function setError(string $strMessage)
    {
        $this->strErrorMessage = $strMessage;
        return $this;
    }

    /**
     * Set current field value.
     *
     * @param mixed $mValue
     * @return $this
     */
    public function setValue($mValue)
    {
        $this->mValue = $mValue;
        return $this;
    }

    /** @return bool Is this a date/time field? */
    public function isDateField()
    {
        return in_array($this->getName(), ['starts_at', 'ends_at']);
    }

    /** @return bool Is there an input that immediately follows this one? */
    public function hasFollower()
    {
        return $this->bHasFollower;
    }

    /** @return bool Does this input immediately follow another? */
    public function isFollower()
    {
        return $this->bIsFollower;
    }

    /** @return bool Is this input related to recurring events? */
    public function isRecurringField()
    {
        return $this->bIsRecurringField;
    }

    /** @return bool Is this a multi-checkbox/multi-radio field? */
    public function isMulti()
    {
        return $this->bMulti && 'select' !== $this->getType();
    }

    /**
     * Render the element.
     *
     * @return string HTML
     */
    public function __toString()
    {
        return $this->renderLabel() . ' ' . $this->renderInput();
    }

    /**
     * Render the input element.
     *
     * @param array $aAttribs Array of element attributes.
     * @return string HTML
     */
    public function renderInput(array $aAttribs = [])
    {
        $this->aAttribs += $aAttribs;
        $strElement = $this->isMulti() ? $this->renderMulti() : $this->renderSingle();
        $strSuffix = $this->strSuffix ? ' ' . $this->strSuffix : '';
        return $strElement . $strSuffix;
    }

    /**
     * Render a single element.
     *
     * @return string HTML
     */
    protected function renderSingle()
    {
        $strHtml = '';

        // Apply label?
        if (in_array($this->getType(), ['checkbox', 'radio']))
            $strHtml .= '<label class="sked-input-multi">';

        // Build opening tag
        $strHtml .= '<' . $this->strElementType . ' ' . $this->renderAttribs() . '>';

        // Optional - Build inner HTML
        if (!empty($this->aOptions)) {
            // An indexed array means use the label as the value also.
            $bLabelIsValue = isset($this->aOptions[0]);
            foreach ($this->aOptions as $mValue => $strLabel) {
                if ($bLabelIsValue)
                    $mValue = $strLabel;
                $strSelected = isset($this->mValue) && (string)$this->mValue === (string)$mValue
                    ? ' selected' : '';
                $strHtml .= '<option value="' . $mValue . '"' . $strSelected . '>'
                    . $strLabel . '</option>';
            }
        } elseif ('textarea' === $this->strElementType) {
            $strHtml .= $this->mValue;
        }

        // Optional - Build closing tag
        if ('input' !== $this->strElementType)
            $strHtml .= '</' . $this->strElementType . '>';

        // Close label?
        if (in_array($this->getType(), ['checkbox', 'radio']))
            $strHtml .= '</label>';

        return $strHtml;
    }

    /**
     * Render multiple related elements.
     *
     * @return string HTML
     */
    protected function renderMulti()
    {
        $strHtml = '';

        // An indexed array means use the label as the value also.
        $bLabelIsValue = isset($this->aOptions[0]);
        foreach ($this->aOptions as $mValue => $strLabel) {
            if ($bLabelIsValue)
                $mValue = $strLabel;
            $strSelected = isset($this->mValue) && in_array($strLabel, (array)$this->mValue)
                ? ' checked' : '';
            $strHtml .= '<label class="sked-input-multi">'
                . '<input value="' . $mValue . '" ' . $this->renderAttribs() . $strSelected . '> '
                . $strLabel . '</label>';
        }

        return $strHtml;
    }

    /**
     * Render HTML element attributes.
     *
     * @return string HTML
     */
    protected function renderAttribs()
    {
        // Element name
        $aHtml = ['name="' . $this->strName . '"'];

        // Default value
        if ($this->mValue && empty($this->aOptions))
            $aHtml[] = 'value="' . $this->mValue . '"';

        // Misc attribs
        foreach ($this->aAttribs as $strKey => $mValue) {
            $aHtml[] = is_bool($mValue)
                ? ($mValue ? $strKey : '') // just the attribute name for booleans
                : $strKey . '="' . $mValue . '"'; // otherwise key="value"
        }

        return implode(' ', $aHtml);
    }

    /**
     * Render HTML element <label>.
     *
     * @return string HTML
     */
    public function renderLabel()
    {
        return $this->strLabel ? '<label>' . $this->strLabel . '</label>' : '';
    }

}
