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

        if (!empty($_GET['ref_id'])) {
            $root_id = dciSkin_tabs::getRootCourse($_GET['ref_id']);
            $obj_id = $root_id['obj_id'];
        } else {
            $obj_id = $DIC->ctrl()->getContextObjId();
        }
        $style_id = ilObjStyleSheet::lookupObjectStyle($obj_id);

        if (!empty($style_id)) {
            $query = "SELECT * FROM style_parameter WHERE ";
            $result = $DIC->database()->queryF("SELECT class, parameter, value FROM style_parameter WHERE style_id = %s AND (class='Accent' OR class='PageContainer' OR class='HAccordICntr')", ['integer'], [$style_id]);
            foreach($DIC->database()->fetchAll($result) as $line) {
                if (substr($line['value'], 0, 1) !== "!") $style[$line['class']][$line['parameter']] = $line['value'];
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

        $html .= $style_tag;
        return $html;
    }

    public static function apply_cover($html) {
/*         const rowWrapper = ilContentContainer.querySelector('body.is_course .row');
        if (rowWrapper) {
            const cover = ilContentContainer.querySelector('#il_center_col .dci-cover:first-of-type');
            if (cover) {
                const coverWrapper = document.querySelector('.dci-course-cover') || document.createElement('div');
                coverWrapper.className = "dci-course-cover";
                coverWrapper.appendChild(cover);
                //rowWrapper.parentNode.insertBefore(coverWrapper, rowWrapper);
            }
 */

        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        // remove card container
        $cover = $finder->query('//div[contains(@id, "il_center_col")]//div[contains(@class, "dci-cover")]');
        $cover_wrapper = $finder->query('//div[contains(@class, "dci-course-cover")]');
        //var_dump($cover[0], $cover_wrapper[0]); die();
        if (!empty($cover_wrapper[0]) && !empty($cover[0])) {
            $cover_wrapper[0]->appendChild($cover[0]);
            //$cover[0]->parentNode->removeChild($cover[0]);
        }

        return str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
    }

    public static function getCoverFromRootPage($obj_id)
    {
/*         global $DIC;
        $db = $DIC->database();
        $user = $DIC->user();
        $current_ref_id = $_GET['ref_id'];
        $root_page = dciSkin_tabs::getRootCourse($current_ref_id);

        $sql = "SELECT DISTINCT content FROM page_object WHERE parent_id = %s AND active = %s";
        $res = $db->queryF(
            $sql,
            ['integer', 'integer'],
            [$obj_id, 1]
        );
        $page_content = $db->fetchAssoc($res)["content"];
        if (empty($page_content)) {
            return $ids;
        }

        $dom = new DomDocument();
        $dom->version = "1.0";
        $dom->encoding = "utf-8";
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadXML($page_content);
        libxml_use_internal_errors($internalErrors);

        $finder = new DOMXPath($dom);
        foreach ($finder->query('//Plugged[contains(@PluginName, "Card")]') as $card) {
            $property_ref_id = $finder->query('./PluggedProperty[contains(@Name, "ref_id")]', $card)[0];
            $ids[] = ["ref_id" => (int) $property_ref_id->textContent];
        }

        foreach ($ids as $i => $id) {
            $object = \ilObjectFactory::getInstanceByRefId($id['ref_id']);

            // filter only allowed item types
            if (!in_array($object->getType(), ["lm", "sahs", "file", "htlm", "tst"])) {
                unset($ids[$i]);
                continue;
            }

            $obj_id = $object->getId();

            $already_exists = array_search($id['ref_id'], array_column($ids, 'ref_id'));
            if (isset($already_exists['completed'])) {
                $ids[$i] = $already_exists;
            } else {
                $lp_completed = ilLPStatus::_hasUserCompleted($obj_id, $user->getId());

                $ids[$i]['type'] = $object->getType();
                $ids[$i]['obj_id'] = $obj_id;
                $ids[$i]['completed'] = $lp_completed;
            }
        }

        return $ids; */
    }

}
