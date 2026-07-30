<?php
/**
 * Class ilDciSkinPlugin
 * @author  Kalamun <rp@kalamun.net>
 * @version $Id$
 */

 class ilDciSkinPlugin extends ilUserInterfaceHookPlugin
 {
    const CTYPE = "components/ILIAS";
    const CNAME = "UIComponent";
    const SLOT_ID = "uihk";
    const PLUGIN_NAME = "DciSkin";

    protected static $instance = null;

    public function __construct(
        \ilDBInterface $db,
        \ilComponentRepositoryWrite $component_repository,
        string $id
    )
    {
        parent::__construct($db, $component_repository, $id);
    }

    // https://docu.ilias.de/ilias.php?ref_id=42&obj_id=27236&cmd=layout&cmdClass=illmpresentationgui&cmdNode=13g&baseClass=ilLMPresentationGUI

    public static function getInstance() : ilDciSkinPlugin
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $component_repository = $DIC['component.repository'];
        $component_factory = $DIC['component.factory'];

        $plugin_info = $component_repository->getComponentByTypeAndName(
            self::CTYPE,
            self::CNAME
        )->getPluginSlotById(self::SLOT_ID)->getPluginByName(self::PLUGIN_NAME);

        self::$instance = $component_factory->getPlugin($plugin_info->getId());

        return self::$instance;
    }

    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }

}
 