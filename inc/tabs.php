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
            if(!empty($tabs)) {
                $output = '<div class="dci-course-tabs-inner">';
                $output .= static::print_tabs_node($tabs);
                $output .= '</div>';
            }

            $html = str_replace("{DCI_COURSE_MENU}", $output, $html);
        }

        return $html;
    }

    public static function count_cards_by_status($tabs, $status) {
        $cards_count = 0;
        foreach ($tabs as $page) {
            $cards_count += $page['cards_' . $status];
            if (isset($tabs['childs']) && count($tabs['childs']) > 0) {
                $cards_count += self::count_cards_by_status($tabs, $status);
            }
        }
        return $cards_count;
    }

    public static function print_tabs_node($tabs) {
        $output = "";
        $current_ref_id = $_GET['ref_id'];

        if (count($tabs) > 0
            && ($tabs[0]['root'] || array_filter($tabs, fn($tab) => $tab['ref_id'] == $current_ref_id) || array_filter($tabs, fn($tab) => $tab['parent_id'] == $current_ref_id))
        ) {
            $output .= '<ul>';

            //usort($tabs, fn($a, $b) => $a['order'] - $b['order']);

            $mandatory_cards_count = self::count_cards_by_status($tabs, "mandatory");
            $completed_cards_count = self::count_cards_by_status($tabs, "completed");

            foreach ($tabs as $tab) {
                $output .= '<li class="'
                    . ($tab['current_page'] ? 'selected' : '') . ' '
                    . ($tab['root'] ? 'is-root' : '') . ' '
                    . ($tab['completed'] ? 'is-completed' : '')
                    . '"><a href="' . $tab['permalink'] . '">'
                    . '<span class="title">' . $tab['title'] . '</span>'
                    . (
                    $tab['root'] ? (
                        $mandatory_cards_count > 0 ? '<span class="course-progress"><meter min="0" max="0" value=" ' . round(100 / $mandatory_cards_count * $completed_cards_count) . '"></meter></span>' : ''
                    ) : (
                        $tab['cards'] > 0
                        ? '<span class="progress' . ($tab['completed'] ? ' completed' : '') . '">'
                            . ($tab['completed'] ? '<span class="icon-done"></span>' : '')
                            . $tab['cards_completed'] . ' / ' . $tab['cards']
                            . '</span>'
                        : ''
                    )
                    )
                    . '</a>';
                if ($tab['current_page'] && $tab['show_anchors']) {
                    $output .= '<div class="dci-page-navbar"></div>';
                }

                $output .= static::print_tabs_node($tab['childs']);
                $output .= '</li>';
            }
            $output .= '</ul>';
        }

        return $output;
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
        $current_ref_id = $ref_id ?? $_GET['ref_id'];
        $root_course = static::getRootCourse($current_ref_id);

        $tabs = static::getChildArray($root_course['ref_id'], $current_ref_id);
        return $tabs;
    }

    public static function getChildArray($ref_id, $current_ref_id) {
	if (empty($ref_id)) return [];
        global $DIC;
        $ctrl = $DIC->ctrl();
        $tree = $DIC->repositoryTree();

        $tabs = [];
        $childs = [];

        $root_course = static::getRootCourse($current_ref_id);

        $object = \ilObjectFactory::getInstanceByRefId($ref_id);
        if (empty($object) || $object->lookupOfflineStatus($ref_id) == true) {
            return [];
        }
        
        if (!$object) {
            return [];
        }

        $obj_id = $object->getId();

        $sorting = \ilContainerSorting::lookupPositions($ref_id);

        $mandatory_objects = \dciCourse::get_mandatory_objects($root_course["obj_id"]);
        
        $mandatory_objects_status = [];
        foreach($mandatory_objects as $obj) {
            $mandatory_objects_status[$obj['obj_id']] = $obj['completed'];
        }

        
        if ($ref_id == $root_course['ref_id']) {
            $tabs = [
                [
                    "id" => $root_course['ref_id'],
                    "ref_id" => $root_course['ref_id'],
                    "obj_id" => $obj_id,
                    "title" => $root_course["title"],
                    "permalink" => $permalink,
                    "current_page" => $tab['ref_id'] == $current_ref_id,
                    "order" => 0,
                    "root" => true,
                    "parent_id" => 0,
                    "cards" => 0,
                    "cards_mandatory" => 0,
                    "cards_completed" => 0,
                    "show_anchors" => false,
                    "childs" => [],
                    "completed" => false,
                ]
            ];
        }
            
        $childs = $tree->getChilds($ref_id);
        
        $container_sorting = \ilContainerSorting::_getInstance($obj_id);
        $sorting_settings = $container_sorting->getSortingSettings();
        $sorting_settings->setSortMode(ilContainer::SORT_MANUAL);
        $sorted = $container_sorting->sortItems(['lsitems' => $childs]);
        $childs = $sorted['lsitems'];

        if (count($childs) > 0) {
             if ($ref_id == $root_course['ref_id']) {
                array_unshift($childs, [
                    "type" => "fold",
                    "ref_id" => $root_course['ref_id'],
                ]);
            }
 
            foreach ($childs as $index => $tab) {

                if ($tab["type"] !== "fold") {
                    continue;
                }

                $object = \ilObjectFactory::getInstanceByRefId($tab['ref_id']);
                if (empty($object) || $object->lookupOfflineStatus($tab['ref_id']) == true) {
                    // object is offline - do not display
                    continue;
                }

                if (!$object) {
                    continue;
                }

                $obj_id = $object->getId();
                $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $tab['ref_id']);
                $permalink = $ctrl->getLinkTargetByClass("ilrepositorygui", "frameset");

                $cards = static::getCardsOnPage($obj_id);
                $cards_completed = array_filter($cards, fn($card) => !!$mandatory_objects_status[$card['obj_id']]);
                $cards_mandatory = array_filter($cards, fn($card) => isset($mandatory_objects_status[$card['obj_id']]));
                $is_completed = count($cards_completed) === count($cards);
                $title = $obj_id != $root_course['obj_id'] ? $object->getTitle() : static::getH1($obj_id);

                $childs = [];
                if ($root_course['ref_id'] !== $tab['ref_id']) {
                    $childs = static::getChildArray($tab['ref_id'], $current_ref_id);
                }

                $tabs[] = [
                    "id" => $tab['ref_id'],
                    "ref_id" => $tab['ref_id'],
                    "obj_id" => $obj_id,
                    "title" => $title,
                    "permalink" => $permalink,
                    "current_page" => $tab['ref_id'] == $current_ref_id,
                    "order" => $tab['position'],
                    "root" => false,
                    "parent_id" => $tab['parent'],
                    "cards" => count($cards),
                    "cards_mandatory" => count($cards_mandatory),
                    "cards_completed" => count($cards_completed),
                    "completed" => $is_completed,
                    "show_anchors" => count($childs) == 0 && $tab['parent'] == $root_course['ref_id'],
                    "childs" => $childs,
                ];
            }
        }

        return $tabs;
    }

    public static function getCardsOnPage($obj_id)
    {
        global $DIC;
        $db = $DIC->database();
        $user = $DIC->user();
        $ids = [];

        $current_language = $DIC->language()->getContentLanguage();
        $sql = "SELECT DISTINCT content FROM page_object WHERE parent_id = %s AND active = %s AND lang = %s ORDER BY rendered_time DESC LIMIT 1";
        $res = $db->queryF(
            $sql,
            ['integer', 'integer', 'string'],
            [$obj_id, 1, $current_language]
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

    public static function getH1($obj_id)
    {
        global $DIC;
        $db = $DIC->database();
        $user = $DIC->user();

        $current_language = $DIC->language()->getContentLanguage();
        $sql = "SELECT DISTINCT content FROM page_object WHERE parent_id = %s AND active = %s AND lang = %s ORDER BY rendered_time DESC LIMIT 1";
        $res = $db->queryF(
            $sql,
            ['integer', 'integer', 'string'],
            [$obj_id, 1, $current_language]
        );
        $page_content = $db->fetchAssoc($res)["content"];
        if (empty($page_content)) {
            $sql = "SELECT DISTINCT content FROM page_object WHERE parent_id = %s AND active = %s AND lang = %s ORDER BY rendered_time DESC LIMIT 1";
            $res = $db->queryF(
                $sql,
                ['integer', 'integer', 'string'],
                [$obj_id, 1, "-"]
            );
            $page_content = $db->fetchAssoc($res)["content"];
            if (empty($page_content)) {
                return "";
            }
        }

        $dom = new DomDocument();
        $dom->version = "1.0";
        $dom->encoding = "utf-8";
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadXML($page_content);
        libxml_use_internal_errors($internalErrors);

        $finder = new DOMXPath($dom);
        $first_h1 = $finder->query('//Paragraph[contains(@Characteristic, "Headline1")]')[0];
        if (!empty($first_h1)) {
            return $first_h1->textContent;
        }
    }

}
