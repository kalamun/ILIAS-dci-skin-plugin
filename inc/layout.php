<?php
/**
 * Generic layout functions
 */

require_once __DIR__ . "/tabs.php";

class dciSkin_layout
{

    public static function apply_custom_placeholders($html)
    {
        global $DIC;
        $user = $DIC->user();

        $body_class = [];
        
        if (dciSkin_tabs::getRootCourse($_GET['ref_id']) !== false) {
            $body_class[] = "is_course";
        }
        
        if ($_GET['cmdClass'] == "ilmailfoldergui" || $_GET['cmdClass'] == "showMail") {
            $body_class[] = "is_inbox";
        }

        $html = str_replace("{BODY_CLASS}", implode(" ", $body_class), $html);
        $html = str_replace("{SKIN_URI}", "/Customizing/global/skin/dci", $html);
        $html = str_replace("{DCI_HOMEPAGE_URL}", "/goto.php?target=root_1&client_id=default", $html);

        $html = str_replace("{LANGUAGE_SELECTOR}", dciSkin_menu::get_language_selector(), $html);

        // short codes
        $name = $user->getFirstName();
        $html = str_replace("[USER_NAME]", $name, $html);
        
        return $html;
    }

    public static function remove_default_cards($html)
    {
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

        return str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
    }

    public static function cleanup_dead_code($html)
    {
        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        // remove card container
        $card_container = $finder->query('//a[contains(@id, "ilPageShowAdvContent")]');
        if (!empty($card_container[0])) {
            $card_container[0]->parentNode->removeChild($card_container[0]);
        }

        return str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
    }

    public static function apply_custom_style($html) {
        global $DIC;
        $style_tag = "";

        $obj_id = $DIC->ctrl()->getContextObjId();
        $style_id = ilObjStyleSheet::lookupObjectStyle($obj_id);
        if (!empty($style_id)) {
            $query = "SELECT * FROM style_parameter WHERE ";
            $result = $DIC->database()->queryF("SELECT class, parameter, value FROM style_parameter WHERE style_id = %s AND (class='Accent' OR class='PageContainer')", ['integer'], [$style_id]);
            foreach($DIC->database()->fetchAll($result) as $line) {
                $style[$line['class']][$line['parameter']] = $line['value'];
            }
            
            ob_start();
            ?>
            <style>
                :root {
                    <?php
                    foreach($style as $class => $parameters) {
                        foreach($parameters as $parameter => $value) {
                            ?>--il-<?= $class; ?>-<?= $parameter; ?>: <?= $value; ?>;
                            <?php
                        }
                    }
                    ?>
                }
            </style>
            <?php
            $style_tag = ob_get_clean();
        }


        $html = str_replace("</head>", $style_tag . "\n</head>", $html);
        return $html;
    }
}
