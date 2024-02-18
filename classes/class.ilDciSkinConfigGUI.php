<?php

/**
 * Config screen
 */
class ilDciSkinConfigGUI extends ilPluginConfigGUI {

    const PLUGIN_CLASS_NAME = ilDciSkinPlugin::class;
    const CMD_CONFIGURE = "configure";
    const CMD_UPDATE_CONFIGURE = "updateConfigure";
    const LANG_MODULE = "config";

    protected $dic;
    protected $plugin;
    protected $lng;
    protected $request;
    protected $user;
    protected $ctrl;
    protected $object;
  
    public function __construct()
    {
      global $DIC;
      $this->dic = $DIC;
      $this->plugin = ilDciSkinPlugin::getInstance();
      $this->lng = $this->dic->language();
      // $this->lng->loadLanguageModule("assessment");
      $this->request = $this->dic->http()->request();
      $this->user = $this->dic->user();
      $this->ctrl = $this->dic->ctrl();
      $this->object = $this->dic->object();
    }
    
    public function performCommand(/*string*/ $cmd)/*:void*/
    {
        $this->plugin = $this->getPluginObject();

        switch ($cmd)
		{
			case self::CMD_CONFIGURE:
            case self::CMD_UPDATE_CONFIGURE:
                $this->{$cmd}();
                break;

            default:
                break;
		}
    }

    protected function configure()/*: void*/
    {
        global $tpl, $ilCtrl, $lng, $DIC;

        $title_long = $DIC['ilias']->getSetting("inst_name");
        $title_short = $DIC['ilias']->getSetting("short_inst_name");

		require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($ilCtrl->getFormAction($this));
        $form->setTitle($this->plugin->txt("settings"));
        
        $login_image = new ilImageFileInputGUI($this->plugin->txt("login_image"), 'login_image');
        $login_image->setAllowDeletion(false);
        $image_url = './minarm_login.jpg';
        if (file_exists($image_url)) {
            $login_image->setImage($image_url);
        }
        $form->addItem($login_image);
        
        $form->addCommandButton("updateConfigure", $lng->txt("save"));

		$tpl->setContent($form->getHTML());
    }

    protected function updateConfigure()/*: void*/
    {
        global $lng, $DIC;

        if (!empty($_FILES['login_image']['name'])) {
            move_uploaded_file($_FILES["login_image"]["tmp_name"], './minarm_login.jpg');
        }

        self::configure();

        ilUtil::sendSuccess($this->plugin->txt("configuration_saved"), true);

    }
}
