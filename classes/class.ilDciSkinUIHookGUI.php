<?php
require_once __DIR__ . "/../inc/accordion.php";
require_once __DIR__ . "/../inc/course.php";
require_once __DIR__ . "/../inc/footer.php";
require_once __DIR__ . "/../inc/layout.php";
require_once __DIR__ . "/../inc/tabs.php";
require_once __DIR__ . "/../inc/menu.php";
require_once __DIR__ . "/../inc/cache.php";

/**
 * Class ilDciSkinUIHookGUI
 * @author            Kalamun <rp@kalamun.net>
 * @version $Id$
 * @ingroup ServicesUIComponent
 * @ilCtrl_isCalledBy ilDciSkinUIHookGUI: ilUIPluginRouterGUI, ilAdministrationGUI, ilRepositoryGUI
 */

class ilDciSkinUIHookGUI extends ilUIHookPluginGUI
{
    protected $user;
    protected $ctrl;

    protected $is_dci_skin = false;
    protected $is_admin = false;
    protected $is_tutor = false;
    protected $is_initialized = false;

    public function __construct()
    {
        if ($this->is_initialized == true) {
            return;
        }

        // === Protection 1 : appel cache wrapped ===
        try {
            dciSkin_cache::on_loading_page();
        } catch (\Throwable $e) {
            error_log('[DciSkin] cache::on_loading_page() failed: ' . $e->getMessage());
        }

        /* Prevent any modification to users not using the DCI Skin */
        try {
            $this->is_dci_skin = ilStyleDefinition::getCurrentSkin() === 'dci';
        } catch (\Throwable $e) {
            $this->is_dci_skin = false;
        }

        if (! $this->is_dci_skin) {
            return;
        }

        // === Protection 2 : vérifier l'état du DIC avant tout accès ===
        global $DIC;
        if (! isset($DIC) || ! ($DIC instanceof \ILIAS\DI\Container)) {
            return;
        }

        if (! isset($DIC['ilUser'])) {
            return;
        }

        if (! isset($DIC['ilCtrl'])) {
            return;
        }

        try {
            $this->user = $DIC->user();
            $this->ctrl = $DIC->ctrl();

            $user_id = (int) $this->user->getId();
            if ($user_id <= 0 || $user_id === ANONYMOUS_USER_ID) {
                // Anonymous users: we keep dci skin enabled for the public functions
                $this->is_initialized = true;
                return;
            }

            // === Protection 3 : RBAC en try/catch ===
            if (! isset($DIC['rbacreview'])) {
                $this->is_initialized = true;
                return;
            }

            $rbac_review = $DIC->rbac()->review();
            $global_roles_of_user = $rbac_review->assignedRoles($user_id);
            $global_roles = $rbac_review->getGlobalRoles();

            foreach ($global_roles as $role) {
                if (in_array($role, $global_roles_of_user)) {
                    try {
                        $role_obj = new ilObjRole($role);
                        $title = $role_obj->getTitle();
                        if ($title === "Administrator") {
                            $this->is_admin = true;
                        } elseif ($title === "Tutor") {
                            $this->is_tutor = true;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }

            $this->is_initialized = true;

        } catch (\Throwable $e) {
            error_log('[DciSkin] __construct error: ' . $e->getMessage());
            $this->is_initialized = false;
        }
    }

    /**
     * Modify HTML output of GUI elements.
     */
    public function getHTML(string $a_comp, string $a_part, array $a_par = []) : array
    {
        if (! $this->is_dci_skin) {
            return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
        }

        // Degradate if init failed
        if (! $this->is_initialized || $this->ctrl === null) {
            return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
        }

        try {
            global $tpl;
            global $DIC;

            $homepage_url = "/ilias.php?ref_id=1&cmd=frameset&cmdClass=ilrepositorygui&baseClass=ilrepositorygui";

            $base_class = isset($_GET['baseClass']) ? (string) $_GET['baseClass'] : '';
            $cmd = isset($_GET['cmd']) ? (string) $_GET['cmd'] : '';

            if (strpos($homepage_url, "ilDashboardGUI") === false
                && $base_class === "ilDashboardGUI"
                && $cmd === "jumpToSelectedItems") {
                header('Location: ' . $homepage_url);
                exit;
            }

            if (! $this->is_admin && ! $this->is_tutor && ! empty($a_par["html"]) && ! $this->ctrl->isAsynch()) {
                $html = $a_par["html"];

                if ($a_part == "template_show") {
                    $html = dciSkin_layout::apply_custom_placeholders($html);
                    $html = dciSkin_layout::apply_custom_style($html);
                    $html = dciSkin_layout::apply_cover($html);
                }

                /* login */
                if ($a_part == "template_add" && isset($a_par["tpl_id"])
                    && strpos($a_par["tpl_id"], "tpl.login.html") !== false) {
                    $html = dciSkin_layout::add_login_thumbnail($html);
                }

                /* menu */
                if ($a_part == "template_get" && isset($a_par["tpl_id"])
                    && $a_par["tpl_id"] == "src/UI/templates/default/MainControls/tpl.mainbar.html") {
                    $html = dciSkin_menu::apply_mainbar($html);
                }
                if ($a_part == "template_get" && isset($a_par["tpl_id"])
                    && $a_par["tpl_id"] == "src/UI/templates/default/MainControls/tpl.metabar.html") {
                    $html = dciSkin_menu::apply_metabar($html);
                }

                /* accordion */
                if ($a_part == "template_get" && isset($a_par["tpl_id"])
                    && $a_par["tpl_id"] == "Services/COPage/tpl.page.html"
                    && strpos($html, "ilc_va_icntr_VAccordICntr") !== false) {
                    $html = dciSkin_accordion::apply($html);
                }

                /* remove cards default section */
                if ($a_part == "template_get" && isset($a_par["tpl_id"])
                    && $a_par["tpl_id"] == "Services/Container/tpl.container_page.html"
                    && strpos($html, "ilContainerBlock") !== false) {
                    $html = dciSkin_layout::remove_default_cards($html);
                    $html = dciSkin_layout::cleanup_dead_code($html);
                }

                /* footer */
                if ($a_part == "template_get" && isset($a_par['tpl_id'])
                    && $a_par['tpl_id'] == "src/UI/templates/default/MainControls/tpl.footer.html") {
                    $html = dciSkin_footer::apply($html);
                }

                if ($a_part == "template_load") {
                    $html = dciSkin_layout::apply_custom_placeholders($html);
                    $html = dciSkin_tabs::apply_custom_placeholders($html);
                }

                return ["mode" => ilUIHookPluginGUI::REPLACE, "html" => $html];
            }

            return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];

        } catch (\Throwable $e) {
            // Filet de sécurité final : on retourne KEEP pour ne pas casser la page
            error_log('[DciSkin] getHTML error in ' . $a_part . ': ' . $e->getMessage());
            return ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];
        }
    }

    /**
     * Modify GUI objects, before they generate output
     */
    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []) : void
    {
        try {
            dciSkin_cache::after_loading_page();
        } catch (\Throwable $e) {
            error_log('[DciSkin] cache::after_loading_page() failed: ' . $e->getMessage());
        }
    }
}