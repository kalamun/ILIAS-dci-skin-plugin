<?php
/**
 * Convert standard accordions to customized ones
 * also adding progress indications
 */

class dciSkin_tabs
{

    public static function apply_custom_placeholders($html)
    {
        if (strpos($html, "{DCI_COURSE_MENU}") !== false) {
            $tabs = static::getCourseTabs();
            $output = "";

            if (count($tabs) > 0) {
                $output .= '<div class="dci-course-tabs-inner"><ul>';

                $mandatory_cards_count = 0;
                $completed_cards_count = 0;
                foreach ($tabs as $page) {
                    $mandatory_cards_count += $page['cards_mandatory'];
                    $completed_cards_count += $page['cards_completed'];
                }

                foreach ($tabs as $tab) {
                    $output .= '<li class="'
                        . ($tab['current_page'] ? 'selected' : '') . ' '
                        . ($tab['root'] ? 'is-root' : '') . ' '
                        . ($tab['completed'] ? 'is-completed' : '')
                        . '"><a href="' . $tab['permalink'] . '">'
                        . '<span class="title">' . $tab['title'] . '</span>'
                        . (
                        $tab['root'] ? (
                            $mandatory_cards_count > 0 ? '<span class="progress"><meter min="0" max="0" value=" ' . round(100 / $mandatory_cards_count * $completed_cards_count) . '"></meter></span>' : ''
                        ) : (
                            '<span class="progress' . ($tab['completed'] ? ' completed' : '') . '">'
                            . ($tab['completed'] ? '<span class="icon-done"></span>' : '')
                            . $tab['cards_completed'] . ' / ' . $tab['cards']
                            . '</span>'
                        )
                        )
                        . '</a>';
                    if ($tab['current_page']) {
                        $output .= '<div class="dci-page-navbar"></div>';
                    }
                    $output .= '</li>';
                }
                $output .= '</ul></div>';
            }

            $html = str_replace("{DCI_COURSE_MENU}", $output, $html);
        }

        return $html;
    }

    /**
     * Get the parent course, if any
     */
    public static function getRootCourse($current_ref_id)
    {
        if (empty($current_ref_id)) return false;
        
        global $DIC;
        $tree = $DIC->repositoryTree();

        $root_course = false;
        for ($ref_id = $current_ref_id; $ref_id; $ref_id = $tree->getParentNodeData($ref_id)['ref_id']) {
            $node_data = $DIC["tree"]->getNodeData($ref_id);
            if (empty($node_data) || $node_data["type"] == "crs") {
                $root_course = $node_data;
                break;
            }
        }
        return $root_course;
    }

    /**
     * get course tabs, which means the list of folders of the current course
     */
    public static function getCourseTabs($ref_id = null)
    {
        global $DIC;
        $ctrl = $DIC->ctrl();
        $tree = $DIC->repositoryTree();

        $tabs = [];
        $current_ref_id = $ref_id ?? $_GET['ref_id'];

        $root_course = static::getRootCourse($current_ref_id);

        if (!$root_course['ref_id']) {
            return $tabs;
        }

        $sorting = \ilContainerSorting::lookupPositions($root_course['obj_id']);
        $mandatory_objects = \dciCourse::get_mandatory_objects($root_course['obj_id']);
        $mandatory_objects_status = [];
        foreach($mandatory_objects as $obj) {
            $mandatory_objects_status[$obj['obj_id']] = $obj['completed'];
        }

        $childs = $tree->getChilds($root_course['ref_id']);
        if (count($childs) > 0) {

            array_unshift($childs, [
                "type" => "fold",
                "ref_id" => $root_course['ref_id'],
            ]);

            foreach ($childs as $index => $tab) {
                if ($tab["type"] !== "fold") {
                    continue;
                }

                $object = \ilObjectFactory::getInstanceByRefId($tab['ref_id']);
                if (empty($object) || $object->lookupOfflineStatus($tab['ref_id']) == true) {
                    // object is offline - do not display
                    continue;
                }

                $obj_id = $object->getId();
                $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $tab['ref_id']);
                $permalink = $ctrl->getLinkTargetByClass("ilrepositorygui", "frameset");

                $cards = static::getCardsOnPage($obj_id);
                $cards_completed = array_filter($cards, fn($card) => !!$mandatory_objects_status[$card['obj_id']]);
                $cards_mandatory = array_filter($cards, fn($card) => isset($mandatory_objects_status[$card['obj_id']]));
                $is_completed = count($cards_completed) === count($cards);

                $tabs[] = [
                    "id" => $tab['ref_id'],
                    "ref_id" => $tab['ref_id'],
                    "obj_id" => $obj_id,
                    "title" => $object->getTitle(),
                    "permalink" => $permalink,
                    "current_page" => $tab['ref_id'] == $current_ref_id,
                    "order" => $sorting[$tab['ref_id']] ?? $index,
                    "root" => ($root_course['ref_id'] === $tab['ref_id']),
                    "cards" => count($cards),
                    "cards_mandatory" => count($cards_mandatory),
                    "cards_completed" => count($cards_completed),
                    "completed" => $is_completed,
                ];
            }
        }

        usort($tabs, fn($a, $b) => $a["order"] - $b["order"]);

        return $tabs;
    }

    public static function getCardsOnPage($obj_id)
    {
        global $DIC;
        $db = $DIC->database();
        $user = $DIC->user();
        $ids = [];

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

        return $ids;
    }

}
