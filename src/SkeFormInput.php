<?php

namespace CampusUnion\Sked;

class SkeFormInput {

    /** @var array $aAttribs Array of element attributes. */
    protected $aAttribs = [];

    /** @var array $aOptions Array of input options. */
    protected $aOptions;

    /** @var bool $bMulti Is this a multi-element input? */
    protected $bMulti = false;

    /** @var mixed $mValue Default field value. */
    protected $mValue;

    /** @var string $strElementType Type of HTML element. */
    protected $strElementType;

    /** @var string $strLabel Label that goes before the element. */
    protected $strLabel;

    /** @var string $strName Name/ID of the field. */
    protected $strName;

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
        if (isset($aAttribs['multi'])) {
            $this->strName .= '[]';
            $this->bMulti = $aAttribs['multi'];
            unset($aAttribs['multi']);
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

    /** @return string Name/ID of the field. */
    public function getName()
    {
        return $this->strName;
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

    /**
     * Render the element.
     *
     * @return string HTML
     */
    public function __toString()
    {
        return $this->renderLabel() . ' ' . ($this->bMulti ? $this->renderMulti() : $this->renderSingle());
    }

    /**
     * Render a single element.
     *
     * @return string HTML
     */
    protected function renderSingle()
    {
        // Build opening tag
        $strHtml = '<' . $this->strElementType . ' ' . $this->renderAttribs() . '>';

        // Optional - Build inner HTML
        if (!empty($this->aOptions)) {
            // An indexed array means use the label as the value also.
            $bLabelIsValue = isset($this->aOptions[0]);
            foreach ($this->aOptions as $mValue => $strLabel) {
                if ($bLabelIsValue)
                    $mValue = $strLabel;
                $strSelected = isset($this->mValue) && $this->mValue === (string)$mValue
                    ? ' selected' : '';
                $strHtml .= '<option value="' . $mValue . '"' . $strSelected . '>'
                    . $strLabel . '</>';
            }
        }

        // Optional - Build closing tag
        if ('input' !== $this->strElementType)
            $strHtml .= '<' . $this->strElementType . '>';

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
            $strSelected = isset($this->mValue) && in_array($strLabel, $this->mValue)
                ? ' checked' : '';
            $strHtml .= '<input value="' . $mValue . '" ' . $this->renderAttribs()
                . $strSelected . '>' . $strLabel;
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
    protected function renderLabel()
    {
        return $this->strLabel ? '<label>' . $this->strLabel . '</label>' : '';
    }

}
