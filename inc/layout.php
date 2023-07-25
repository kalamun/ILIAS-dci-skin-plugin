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

        $html = str_replace("{BODY_CLASS}", implode(" ", $body_class), $html);
        $html = str_replace("{SKIN_URI}", "/Customizing/global/skin/dci", $html);

        // short codes
        $name = $user->getFirstName();
        $html = str_replace("[USER_NAME]", $name, $html);
        
        // URL SHORT CODES
        if (strpos($html, "#TRAINING_CENTER_URI#") !== false) {
            $centers = [];
            foreach (self::getCoursesOfUser($DIC->user()->getId(), true) as $user_course) {
                $center = $DIC->repositoryTree()->getParentNodeData($user_course['ref_id']);
                if (!isset($centers[ $center['ref_id'] ])) {
                    $centers[ $center['ref_id'] ] = $center;
                    
                    $DIC->ctrl()->setParameterByClass("ilrepositorygui", "ref_id", $center['ref_id']);
                    $permalink = $DIC->ctrl()->getLinkTargetByClass("ilrepositorygui", "frameset");
                    $centers[ $center['ref_id'] ]['permalink'] = $permalink;
                }
            }
    
            foreach ($centers as $center) {
                $my_center_uri = $center['permalink'];
                $html = str_replace("#TRAINING_CENTER_URI#", "/" . $my_center_uri, $html);
            }
        }

        if (strpos($html, "#COURSES_URI#") !== false) {
            $my_courses_uri = "";
            $html = str_replace("#COURSES_URI#", "/" . $my_courses_uri, $html);
        }

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




    public static function getCoursesOfUser(
        int $a_user_id,
        bool $a_add_path = false
    ): array {
        global $DIC;
        $tree = $DIC->repositoryTree();

        // see ilPDSelectedItemsBlockGUI

        $items = ilParticipants::_getMembershipByType($a_user_id, ['crs']);

        $repo_title = $tree->getNodeData(ROOT_FOLDER_ID);
        $repo_title = $repo_title["title"];
        if ($repo_title == "ILIAS") {
            $repo_title = "Repository"; //$this->lng->txt("repository");
        }

        $references = $lp_obj_refs = array();
        foreach ($items as $obj_id) {
            $ref_id = ilObject::_getAllReferences($obj_id);
            if (is_array($ref_id) && count($ref_id)) {
                $ref_id = array_pop($ref_id);
                if (!$tree->isDeleted($ref_id)) {
                    $visible = false;
                    $active = ilObjCourseAccess::_isActivated($obj_id, $visible, false);
                    if ($active && $visible) {
                        $references[$ref_id] = array(
                            'ref_id' => $ref_id,
                            'obj_id' => $obj_id,
                            'title' => ilObject::_lookupTitle($obj_id)
                        );

                        if ($a_add_path) {
                            $path = array();
                            foreach ($tree->getPathFull($ref_id) as $item) {
                                $path[] = $item["title"];
                            }
                            // top level comes first
                            if (count($path) === 2) {
                                $path[0] = 0;
                            } else {
                                $path[0] = 1;
                            }
                            $references[$ref_id]["path_sort"] = implode("__", $path);
                            array_shift($path);
                            array_pop($path);
                            if (!count($path)) {
                                array_unshift($path, $repo_title);
                            }
                            $references[$ref_id]["path"] = implode(" &rsaquo; ", $path);
                        }

                        $lp_obj_refs[$obj_id] = $ref_id;
                    }
                }
            }
        }

        // get lp data for valid courses

        if (count($lp_obj_refs)) {
            // listing the objectives should NOT depend on any LP status / setting
            foreach ($lp_obj_refs as $obj_id => $ref_id) {
                // only if set in DB (default mode is not relevant
                if (ilObjCourse::_lookupViewMode($obj_id) === ilCourseConstants::IL_CRS_VIEW_OBJECTIVE) {
                    $references[$ref_id]["objectives"] = static::parseObjectives($obj_id, $a_user_id);
                }
            }

            // LP must be active, personal and not anonymized
            if (ilObjUserTracking::_enabledLearningProgress() &&
                ilObjUserTracking::_enabledUserRelatedData() &&
                ilObjUserTracking::_hasLearningProgressLearner()) {
                // see ilLPProgressTableGUI
                $lp_data = ilTrQuery::getObjectsStatusForUser($a_user_id, $lp_obj_refs);
                foreach ($lp_data as $item) {
                    $ref_id = $item["ref_ids"];
                    $references[$ref_id]["lp_status"] = $item["status"];
                }
            }
        }

        return $references;
    }

    protected function parseObjectives(
        int $a_obj_id,
        int $a_user_id
    ): array {
        $res = array();

        // we need the collection for the correct order
        $coll_objtv = new ilLPCollectionOfObjectives($a_obj_id, ilLPObjSettings::LP_MODE_OBJECTIVES);
        $coll_objtv = $coll_objtv->getItems();
        if ($coll_objtv) {
            // #13373
            $lo_results = static::parseLOUserResults($a_obj_id, $a_user_id);

            $lo_ass = ilLOTestAssignments::getInstance($a_obj_id);

            $tmp = array();

            foreach ($coll_objtv as $objective_id) {
                /** @var array $title */
                $title = ilCourseObjective::lookupObjectiveTitle($objective_id, true);

                $tmp[$objective_id] = array(
                    "id" => $objective_id,
                    "title" => $title["title"],
                    "desc" => $title["description"],
                    "itest" => $lo_ass->getTestByObjective($objective_id, ilLOSettings::TYPE_TEST_INITIAL),
                    "qtest" => $lo_ass->getTestByObjective($objective_id, ilLOSettings::TYPE_TEST_QUALIFIED)
                );

                if (array_key_exists($objective_id, $lo_results)) {
                    $lo_result = $lo_results[$objective_id];
                    $tmp[$objective_id]["user_id"] = $lo_result["user_id"];
                    $tmp[$objective_id]["result_perc"] = $lo_result["result_perc"] ?? null;
                    $tmp[$objective_id]["limit_perc"] = $lo_result["limit_perc"] ?? null;
                    $tmp[$objective_id]["status"] = $lo_result["status"] ?? null;
                    $tmp[$objective_id]["type"] = $lo_result["type"] ?? null;
                    $tmp[$objective_id]["initial"] = $lo_result["initial"] ?? null;
                }
            }

            // order
            foreach ($coll_objtv as $objtv_id) {
                $res[] = $tmp[$objtv_id];
            }
        }

        return $res;
    }

    // see ilContainerObjectiveGUI::parseLOUserResults()
    protected function parseLOUserResults(
        int $a_course_obj_id,
        int $a_user_id
    ): array {
        $res = array();
        $initial_status = "";

        $lur = new ilLOUserResults($a_course_obj_id, $a_user_id);
        foreach ($lur->getCourseResultsForUserPresentation() as $objective_id => $types) {
            // show either initial or qualified for objective
            if (isset($types[ilLOUserResults::TYPE_INITIAL])) {
                $initial_status = $types[ilLOUserResults::TYPE_INITIAL]["status"];
            }

            // qualified test has priority
            if (isset($types[ilLOUserResults::TYPE_QUALIFIED])) {
                $result = $types[ilLOUserResults::TYPE_QUALIFIED];
                $result["type"] = ilLOUserResults::TYPE_QUALIFIED;
                $result["initial"] = $types[ilLOUserResults::TYPE_INITIAL] ?? null;
            } else {
                $result = $types[ilLOUserResults::TYPE_INITIAL];
                $result["type"] = ilLOUserResults::TYPE_INITIAL;
            }

            $result["initial_status"] = $initial_status;

            $res[$objective_id] = $result;
        }

        return $res;
    }

}
