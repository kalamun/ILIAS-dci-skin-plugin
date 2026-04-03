<?php
/**
 * Class ilDciSkinPlugin
 * @author  Kalamun <rp@kalamun.net>
 * @version $Id$
 */

 class ilDciSkinPlugin extends ilUserInterfaceHookPlugin
 {
    const CTYPE = "Services";
    const CNAME = "UIComponent";
    const SLOT_ID = "uihk";
    const PLUGIN_NAME = "DciSkin";

    protected static $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance() : ilDciSkinPlugin
    {
        if (null === self::$instance) {
            return self::$instance = ilPluginAdmin::getPluginObject(
                self::CTYPE,
                self::CNAME,
                self::SLOT_ID,
                self::PLUGIN_NAME
            );
        }

        return self::$instance;
    }

    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }
}
 