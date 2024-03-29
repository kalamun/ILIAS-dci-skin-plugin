<?php
include_once("./Services/Object/classes/class.ilObjectGUI.php");
require_once(__DIR__ . "/../inc/accordion.php");
require_once(__DIR__ . "/../inc/course.php");
require_once(__DIR__ . "/../inc/footer.php");
require_once(__DIR__ . "/../inc/layout.php");
require_once(__DIR__ . "/../inc/tabs.php");
require_once(__DIR__ . "/../inc/menu.php");

/**
 * Class ilDciSkinUIHookGUI
 * @author            Kalamun <rp@kalamun.net>
 * @version $Id$
 * @ingroup ServicesUIComponent
 * @ilCtrl_isCalledBy ilDciSkinUIHookGUI: ilUIPluginRouterGUI, ilAdministrationGUI, ilRepositoryGUI
 */

class ilDciSkinUIHookGUI extends ilUIHookPluginGUI {
  protected $user;
  protected $ctrl;

  protected $is_admin;
  protected $is_tutor;

  public function __construct()
  {
    global $DIC;
    $this->user = $DIC->user();
    $this->ctrl = $DIC->ctrl();

    $this->is_admin = false;
    $this->is_tutor = false;

    $global_roles_of_user = $DIC->rbac()->review()->assignedRoles($DIC->user()->getId());

		foreach ($DIC->rbac()->review()->getGlobalRoles() as $role){
      if (in_array($role, $global_roles_of_user)) {
        $role = new ilObjRole($role);
        if ($role->getTitle() == "Administrator") $this->is_admin = true;
        if ($role->getTitle() == "Tutor") $this->is_tutor = true;
      }
		}
  }

  /**
	 * Modify HTML output of GUI elements. Modifications modes are:
	 * - ilUIHookPluginGUI::KEEP (No modification)
	 * - ilUIHookPluginGUI::REPLACE (Replace default HTML with your HTML)
	 * - ilUIHookPluginGUI::APPEND (Append your HTML to the default HTML)
	 * - ilUIHookPluginGUI::PREPEND (Prepend your HTML to the default HTML)
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array array with entries "mode" => modification mode, "html" => your html
	 */
	function getHTML($a_comp = false, $a_part = false, $a_par = array()) {
    global $tpl;  
    global $DIC;
    
    // redirect to the home page
    $homepage_url = "/ilias.php?ref_id=1&cmd=frameset&cmdClass=ilrepositorygui&baseClass=ilrepositorygui"; // TODO: Set in config UI
    if (strpos($homepage_url, "ilDashboardGUI") === false &&  $_GET['baseClass'] == "ilDashboardGUI" && $_GET['cmd'] == "jumpToSelectedItems") {
      header('Location: ' . $homepage_url);
    }
    
    // template_show
    
    /* Prevent any modification to users not using the DCI Skin */
    include_once "Services/Style/System/classes/class.ilStyleDefinition.php";
    if (ilStyleDefinition::getCurrentSkin() !== 'dci') {
      return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
    }
    
		if (!$this->is_admin && !$this->is_tutor && !empty($a_par["html"]) && !$this->ctrl->isAsynch()) {
      $html = $a_par["html"];
      
      if($a_part == "template_show") {
        // custom placeholders
        $html = dciSkin_layout::apply_custom_placeholders($html);
        $html = dciSkin_layout::apply_custom_style($html);
        $html = dciSkin_layout::apply_cover($html);
      }

      /* login */
      if($a_part == "template_add" && strpos($a_par["tpl_id"], "tpl.login.html") !== false) {
        $html = dciSkin_layout::add_login_thumbnail($html);
      }
      
      /* menu */
      if($a_part == "template_get" && $a_par["tpl_id"] == "src/UI/templates/default/MainControls/tpl.mainbar.html") {
        $html = dciSkin_menu::apply_mainbar($html);
      }
      if($a_part == "template_get" && $a_par["tpl_id"] == "src/UI/templates/default/MainControls/tpl.metabar.html") {
        $html = dciSkin_menu::apply_metabar($html);
      }

      /* accordion */
      if($a_part == "template_get" && $a_par["tpl_id"] == "Services/COPage/tpl.page.html" && strpos($html, "ilc_va_ihcap_VAccordIHeadCap") !== false) {
        $html = dciSkin_accordion::apply($html);
      }
      
      /* remove cards default section */
      if ($a_part == "template_get" && $a_par["tpl_id"] == "Services/Container/tpl.container_page.html" && strpos($html, "ilContainerBlock") !== false) {
        $html = dciSkin_layout::remove_default_cards($html);
        $html = dciSkin_layout::cleanup_dead_code($html);
      }

      /* footer */
      if ($a_part == "template_get" && $a_par['tpl_id'] == "src/UI/templates/default/MainControls/tpl.footer.html") {
        $html = dciSkin_footer::apply($html);
      }
      
      if ($a_part == "template_load") {
        // custom placeholders
        $html = dciSkin_layout::apply_custom_placeholders($html);
        
        /* add tabs */
        $html = dciSkin_tabs::apply_custom_placeholders($html);
      }

      return ["mode" => ilUIHookPluginGUI::REPLACE, "html" => $html];
    }

    return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
  }


  /**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
  function modifyGUI($a_comp, $a_part, $a_par = array()) {
	}

}