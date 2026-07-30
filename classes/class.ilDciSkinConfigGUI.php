<?php
/**
 * Class ilDciSkinConfigGUI
 * @author            Roberto Pasini <bonjour@kalamun.net>
 * @ilCtrl_IsCalledBy ilDciSkinConfigGUI: ilObjComponentSettingsGUI
 */

use ILIAS\Filesystem\Stream\Streams;

class ilDciSkinConfigGUI extends ilPluginConfigGUI
{
    const PLUGIN_CLASS_NAME     = ilDciSkinPlugin::class;
    const CMD_CONFIGURE         = "configure";
    const CMD_UPDATE_CONFIGURE  = "updateConfigure";
    const CMD_PURGE_CACHE       = "purgeCache";
    const LANG_MODULE           = "config";
    const LOGIN_IMAGE_NAME      = 'minarm_login.jpg';
    const LOGIN_IMAGE_MAX_WIDTH = 1600;
    const UPLOAD_DIR_NAME       = 'upload';

    /** @var \ILIAS\DI\Container */
    protected $dic;
    /** @var ilDciSkinPlugin */
    protected $plugin;
    /** @var ilLanguage */
    protected $lng;
    /** @var \Psr\Http\Message\ServerRequestInterface */
    protected $request;
    /** @var ilObjUser */
    protected $user;
    /** @var ilCtrl */
    protected $ctrl;
    /** @var ilObject */
    protected $object;
    protected $plugin_path;
    protected $plugin_url;

    public function __construct()
    {
        global $DIC;
        $this->dic         = $DIC;
        $this->plugin      = ilDciSkinPlugin::getInstance();
        $this->lng         = $this->dic->language();
        $this->request     = $this->dic->http()->request();
        $this->user        = $this->dic->user();
        $this->ctrl        = $this->dic->ctrl();
        $this->object      = $this->dic->object();
        $this->plugin_path = dirname(__DIR__);
        $this->plugin_url  = '/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/DciSkin';
    }

    public function performCommand(string $cmd): void
    {
        $this->plugin = $this->getPluginObject();

        switch ($cmd) {
            case self::CMD_CONFIGURE:
            case self::CMD_UPDATE_CONFIGURE:
            case self::CMD_PURGE_CACHE:
                $this->{$cmd}();
                break;
            default:
                break;
        }
    }

    protected function configure()
    {
        global $tpl, $DIC;

        $cache_enabled = filter_var($DIC['ilias']->getSetting("dci_cache_enabled"), FILTER_VALIDATE_BOOLEAN);

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt('settings'));

        $homepage_url = new ilTextInputGUI($this->plugin->txt('homepage_url'), 'dci_homepage_url');
        $homepage_url_value = $DIC['ilias']->getSetting("dci_homepage_url");
        $homepage_url->setValue($homepage_url_value);
        $form->addItem($homepage_url);

        $login_image = new ilImageFileInputGUI($this->plugin->txt('login_image'), 'login_image');
        $login_image->setAllowDeletion(false);
        $image_file = $this->plugin_path . '/' . self::LOGIN_IMAGE_NAME;
        $image_url  = $this->plugin_url . '/' . self::LOGIN_IMAGE_NAME;
        if (file_exists($image_file)) {
            $login_image->setImage($image_url);
        }
        $form->addItem($login_image);

        $cache_input = new ilCheckboxInputGUI($this->plugin->txt('cache'), 'dci_cache_enabled');
        $cache_input->setOptionTitle($this->plugin->txt('enable_cache'));
        $cache_input->setValue('true');
        $cache_input->setChecked($cache_enabled);
        $form->addItem($cache_input);

        $form->addCommandButton(self::CMD_PURGE_CACHE, $this->plugin->txt('purge_cache'));
        $form->addCommandButton(self::CMD_UPDATE_CONFIGURE, $this->lng->txt('save'));

        $tpl->setContent($form->getHTML());
    }

    protected function updateConfigure()
    {
        global $DIC;

        if (! empty($_FILES['login_image']['name']) && is_uploaded_file($_FILES['login_image']['tmp_name'])) {
            $this->storeLoginImage($_FILES['login_image']['tmp_name'], $_FILES['login_image']['name']);
        }

        $DIC['ilias']->setSetting("dci_cache_enabled", isset($_POST['dci_cache_enabled']) ? 1 : 0);
        $DIC['ilias']->setSetting("dci_homepage_url", (string)$_POST['dci_homepage_url']);

        self::configure();

        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('configuration_saved'), true);
    }

    /**
     * Stores the uploaded login background, resized to a maximum width,
     * in the plugin's own upload directory, and exports a public copy
     * next to the plugin so the (anonymous) login page can display it
     * directly, without going through ILIAS's access-controlled file delivery.
     */
    protected function storeLoginImage(string $tmp_name, string $original_name): void
    {
        global $DIC;

        $image_size = @getimagesize($tmp_name);
        if ($image_size === false) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->plugin->txt('login_image_invalid'), true);
            return;
        }

        $target_width = min((int) $image_size[0], self::LOGIN_IMAGE_MAX_WIDTH);
        $converted = $DIC->fileConverters()->images()->resizeByWidth(
            Streams::ofResource(fopen($tmp_name, 'rb')),
            $target_width
        );
        if (! $converted->isOK()) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->plugin->txt('login_image_invalid'), true);
            return;
        }

        $extension = image_type_to_extension((int) $image_size[2]);
        $stored_name = pathinfo($original_name, PATHINFO_FILENAME) . $extension;

        $upload_dir = $this->plugin_path . '/' . self::UPLOAD_DIR_NAME;
        if (! is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $upload_file = $upload_dir . '/' . $stored_name;

        file_put_contents($upload_file, $converted->getStream()->getContents());

        copy($upload_file, $this->plugin_path . '/' . self::LOGIN_IMAGE_NAME);
    }

    protected function purgeCache()
    {
        global $DIC;

        $success = dciSkin_cache::purgeCache();

        self::configure();

        if ($success) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('cache_purged'), true);
        }

        return $success;
    }
}
