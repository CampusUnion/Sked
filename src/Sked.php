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
        $this->validateDate($strStartDate);
        $this->validateDate($strEndDate);
        return new SkeDateIterator($this->oModel, $strStartDate, $strEndDate);
    }

    /**
     * Persist event data to the database.
     *
     * Updates the ID of the original event object.
     *
     * @param CampusUnion\Sked\SkeVent $skeVent Passed by reference.
     * @return bool
     */
    public function save(SkeVent &$skeVent)
    {
        if ($iId = $this->oModel->save($skeVent))
            $skeVent->id = $iId;

        return !!$iId;
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
     * @param array|CampusUnion\Sked\SkeVent $skeVent Event object for populating form defaults.
     * @return CampusUnion\Sked\SkeForm
     */
    public function form(array $aOptions = [], $skeVent = null)
    {
        return new SkeForm($aOptions, $skeVent);
    }

    /**
     * Do it all in one easy method call.
     *
     * @param array $aOptions Config options.
     */
    public static function skeDoosh(array $aOptions)
    {
        // Load JS
        echo <<<EOD
<script>
    if (typeof jQuery == 'undefined') {
        skedLoadTag(
            'http://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js',
            loadSkedJs
        );
    } else {
         loadSkedJs();
    }

    function skedLoadTag(strSrc, fnOnload)
    {
        var eHead = document.getElementsByTagName('head')[0];
        var e$ = document.createElement('script');
        e$.src = strSrc;
        if (fnOnload)
            e$.onload = fnOnload;
        eHead.appendChild(e$);
    }

    function loadSkedJs()
    {
        skedLoadTag('https://raw.githubusercontent.com/CampusUnion/Sked-JS/master/sked.js');
    }
</script>
EOD;

        // Init Sked
        $sked = new self($aOptions);
        $skeVent = null;
        $bSuccess = false;
        if ($_REQUEST['sked_form'] ?? null === '1') {
            $skeVent = new \CampusUnion\Sked\SkeVent($_REQUEST);
            $bSuccess = $sked->save($skeVent);
        }
        echo $sked->form(['method' => 'get'], $bSuccess ?: $skeVent);
    }

}
