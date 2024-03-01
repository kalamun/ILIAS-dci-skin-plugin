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
 
        global $DIC;
        //var_dump($this->provider_collection); die();
        /*
        $this->provider_collection->setMainBarProvider(new MainBarProvider($DIC, $this));
        $this->provider_collection->setMetaBarProvider(new MetaBarProvider($DIC, $this));
        $this->provider_collection->setNotificationProvider(new NotificationProvider($DIC, $this));
        $this->provider_collection->setModificationProvider(new ModificationProvider($DIC, $this));
        $this->provider_collection->setToolProvider(new ToolProvider($DIC, $this));
 */    }

    // https://docu.ilias.de/ilias.php?ref_id=42&obj_id=27236&cmd=layout&cmdClass=illmpresentationgui&cmdNode=13g&baseClass=ilLMPresentationGUI

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
 