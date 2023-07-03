<?php
include_once("./Services/Object/classes/class.ilObjectGUI.php");

/**
 * Class ilDciSkinUIHookGUI
 * @author            Kalamun <rp@kalamun.net>
 * @version $Id$
 * @ingroup ServicesUIComponent
 * @ilCtrl_isCalledBy ilDciSkinUIHookGUI: ilUIPluginRouterGUI, ilAdministrationGUI
 */

class ilDciSkinUIHookGUI extends ilUIHookPluginGUI {
  protected $dic;
  protected $plugin;
  protected $lng;
  protected $request;
  protected $user;
  protected $ctrl;
  protected $object;
  protected $template;

  protected $is_admin;
  protected $is_tutor;

  public function __construct()
  {
    global $DIC;
    $this->dic = $DIC;
    $this->plugin = ilDciSkinPlugin::getInstance();
    $this->lng = $this->dic->language();
    // $this->lng->loadLanguageModule("assessment");
    $this->request = $this->dic->http()->request();
    $this->user = $DIC->user();
    $this->ctrl = $DIC->ctrl();
    $this->object = $DIC->object();

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

    // template_show
    //$DIC->ui()->mainTemplate();
/*     echo '<pre>';
    print_r(get_class_methods($DIC->factory()));
    echo '</pre>';
    die();
 */
    /* Prevent any modification to users not using the DCI Skin */
    include_once "Services/Style/System/classes/class.ilStyleDefinition.php";
    if (ilStyleDefinition::getCurrentSkin() !== 'dci') {
      return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
    }

		if (!$this->is_admin && !$this->is_tutor && !empty($a_par["html"]) && !$this->ctrl->isAsynch()) {
      $html = $a_par["html"];

      /* accordion */
      if($a_part == "template_get" && $a_par["tpl_id"] == "Services/COPage/tpl.page.html" && strpos($html, "ilc_va_ihcap_VAccordIHeadCap") !== false) {
        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        foreach ($finder->query('//div[contains(@class, "ilc_va_cntr_VAccordCntr")]') as $node) {
          $node->setAttribute('class', $node->getAttribute('class') . ' dci-accordion');
          
          // heading
          $heading_wrapper = $finder->query('.//div[contains(@class, "ilc_va_ihead_VAccordIHead")]', $node)[0];
          $heading_wrapper->setAttribute('class', 'dci-accordion-heading');
          $heading = $finder->query('.//div', $heading_wrapper)[0];

          if ($heading) {
            $h2 = $dom->createElement('h2', htmlentities($heading->textContent));
            while ($heading->hasChildNodes()) {
              $heading->removeChild($heading->firstChild);
            }
            $heading->appendChild($h2);
            
            // progress
            $progress_total = count($finder->query('.//div[contains(@class, "kalamun-card_progress")]', $node));
            $progress_completed = count($finder->query('.//div[contains(@class, "kalamun-card_progress completed")]', $node));
            if ($progress_total > 0) {
              $is_completed = $progress_completed >= $progress_total;

              $progress_icon = $dom->createElement('span');
              $progress_icon->setAttribute('class', 'icon-done');
              $progress = $dom->createElement('div', $progress_completed . "/" . $progress_total);
              $progress->setAttribute('class', 'accordion-progress' . ($is_completed ? ' completed' : ' not-completed'));
              if ($is_completed) $progress->insertBefore($progress_icon, $progress->firstChild);
              $heading->appendChild($progress);
            }
            
            // toggle
            $toggle = $dom->createElement('div');
            $toggle->setAttribute('class', 'icon-down accordion-toggle');
            $heading->appendChild($toggle);
          }
        }

        $html = $dom->saveHTML();
      }

      if ($a_part == "template_get" && $a_par["tpl_id"] == "Services/Container/tpl.container_page.html" && strpos($html, "ilContainerBlock") !== false) {
        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        // remove card container
        $card_container = $finder->query('//div[contains(@class, "ilContainerBlock")]');
        if (!empty($card_container[0])) {
          $card_container[0]->parentNode->removeChild($card_container[0]);
        }
        
        $html = str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
      }

      if ($a_part == "template_load") {
        // custom placeholders
        $html = str_replace("{SKIN_URI}", "/Customizing/global/skin/dci", $html);
  
        /* add tabs */
        if (strpos($html, "{DCI_COURSE_MENU}") !== false) {
          $tabs = $this->getCourseTabs();
          $output = "";
          
          if (count($tabs) > 1) {
            $output .= '<div class="dci-course-tabs-inner"><ul>';
            foreach ($tabs as $tab) {
              $output .= '<li class="' . ($tab['current_page'] ? 'selected' : '') . '"><a href="' . $tab['permalink'] . '">' . $tab['title'] . '</a></li>';
            }
            $output .= '</ul></div>';
          }
          
          $html = str_replace("{DCI_COURSE_MENU}", $output, $html);
        }
        
        // custom 

        // Fix navigation structure
        /*
        if (strpos($html, "</body>") !== false) {
          // DOM changes
          $dom = new DomDocument();
          $internalErrors = libxml_use_internal_errors(true);
          $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
          libxml_use_internal_errors($internalErrors);
          $finder = new DomXPath($dom);
  
          $mainbar_wrapper = $finder->query('//div[contains(@class, "il-maincontrols-mainbar")]');
          //var_dump( htmlspecialchars($dom->saveHTML($mainbar_wrapper->item(0))) );
          //var_dump($mainbar_wrapper);
          $slates = $finder->query('//div[contains(concat(" ", normalize-space(@class), " "), " il-maincontrols-mainbar ")]');
          foreach($finder->query("//li[contains(@class, 'dci-mainbar-li')]") as $index => $node) {
            var_dump($node, $slates[$index]);
            echo '<br><hr><br>';
            $node->documentElement->appendChild($slates[$index]);
          }
  
          $html = $dom->saveHTML();
        }
        */ 
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


  /**
   * get course tabs, which means the list of folders of the current course
   */
  private function getCourseTabs() {
    global $DIC;

    $tabs = [];
    $current_ref_id = $_GET['ref_id'];

    $root_course = false;
    for ($ref_id = $current_ref_id; $ref_id; $ref_id = $DIC->repositoryTree()->getParentNodeData($current_ref_id)['ref_id'] ) {
      $node_data = $DIC["tree"]->getNodeData($ref_id);
      if (empty($node_data) || $node_data["type"] == "crs") {
        $root_course = $node_data;
        break;
      }
    }

    if (!$root_course['ref_id']) return $tabs;

    $sorting = \ilContainerSorting::lookupPositions($root_course['obj_id']);

    foreach ($DIC->repositoryTree()->getChilds($root_course['ref_id']) as $index => $tab) {
      if ($tab["type"] !== "fold") continue;
      
      $object = \ilObjectFactory::getInstanceByRefId($tab['ref_id']);
      if (empty($object) || $object->lookupOfflineStatus($tab['ref_id']) == true) continue; // object is offline - do not display
      
      $DIC->ctrl()->setParameterByClass("ilrepositorygui", "ref_id", $tab['ref_id']);
      $permalink = $DIC->ctrl()->getLinkTargetByClass("ilrepositorygui", "frameset");
      
      $tabs[] = [
        "id" => $tab['ref_id'],
        "title" => $object->getTitle(),
        "permalink" => $permalink,
        "current_page" => $tab['ref_id'] == $current_ref_id,
        "order" => $sorting[$tab['ref_id']] ?? $index,
      ];
    }

    usort($tabs, fn($a, $b) => $a["order"] - $b["order"]);

    return $tabs;
  }


}