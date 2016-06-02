<?php

namespace CampusUnion\Sked;

class Sked {

    /** @var CampusUnion\Sked\Database\SkeModel Data layer. */
    protected static $oModel;

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
        static::$oModel = new $strModelClass($aOptions['data_connector']['options']);

        // Tags
        if (isset($aOptions['tags']))
            static::$oModel->withTags($aOptions['tags']);
    }

    /**
     * Get dates iterator.
     *
     * @param string $strStart Start date YYYY-MM-DD.
     * @param string|true $mEnd End date YYYY-MM-DD, or "true" to return one full month.
     * @return CampusUnion\Sked\SkeDateIterator
     */
    public function dates(string $strStartDate = null, $mEndDate = null)
    {
        return new SkeDateIterator(static::$oModel, $strStartDate, $mEndDate);
    }

    /**
     * Retrieve an event from the database.
     *
     * @param int $iId
     * @return SkeVent
     */
    public function findEvent(int $iId)
    {
        return new SkeVent(static::$oModel->find($iId));
    }

    /**
     * Get the HTML form.
     *
     * @param array $aOptions Optional array of config options.
     * @param array|CampusUnion\Sked\SkeVent $skeVent Event object for populating form defaults.
     * @return CampusUnion\Sked\SkeForm
     */
    public static function form(array $aOptions = [], $skeVent = null)
    {
        return new SkeForm($aOptions, $skeVent);
    }

    /**
     * Fetch sked_event_members from the database.
     *
     * @param int $iEventId
     * @return array
     */
    public static function getEventMembers(int $iEventId)
    {
        return static::$oModel->fetchEventMembers($iEventId);
    }

    /**
     * Fetch sked_event_tags from the database.
     *
     * @param int $iEventId
     * @return array
     */
    public static function getEventTags(int $iEventId)
    {
        return static::$oModel->fetchEventTags($iEventId);
    }

    /**
     * Limit search results to events with certain tags.
     *
     * @param array $aTags The required tags.
     * @return $this
     */
    public function withTags(array $aTags)
    {
        static::$oModel->withTags($aTags);
        return $this;
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
        if ($iId = static::$oModel->save($skeVent))
            $skeVent->id = $iId;

        return !!$iId;
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
