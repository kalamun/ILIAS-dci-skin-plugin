<?php
/**
 * functions to handle course information
 */

class dciCourse {

    /**
     * LEARNING PROGRESS
     */

    /* get mandatory objects to consider the course as completed */
    public static function get_mandatory_objects($course_obj_id) {
        global $DIC;
        $ilDB = $DIC->database();
        $ilUser = $DIC->user();

        $objects = [];

        if (!ilLPObjSettings::_lookupDBMode($course_obj_id) || ilLPObjSettings::_lookupDBMode($course_obj_id) == ilLPObjSettings::LP_MODE_COLLECTION) {
            $sql = "SELECT * FROM `ut_lp_collections` WHERE obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND active=1 AND lpmode=" . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION);
            $result = $ilDB->query($sql);

            while ($row = $ilDB->fetchAssoc($result)) {
                $ref_id = $row['item_id'];
                $obj = ilObjectFactory::getInstanceByRefId($row['item_id']);
                $obj_id = $obj->getId();

                $obj_lp = ilObjectLP::getInstance($obj_id);
                $mode = $obj_lp->getCurrentMode();

                $objects[] = [
                    "ref_id" => $row['item_id'],
                    "obj_id" => $obj_id,
                    "completed" => $mode <= 6 ? ilLPStatusCollection::_hasUserCompleted($obj_id, $ilUser->getId()) : false,
                ];
            }
        }

        return $objects;
    }


    /* return true if the given object is mandatory to consider the course as completed */
    public static function object_is_mandatory($ref_id, $course_obj_id) {
        global $DIC;
        $ilDB = $DIC->database();
        $sql = "SELECT * FROM `ut_lp_collections` WHERE obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND item_id=" . $ilDB->quote($ref_id, "integer") . " AND active=1 AND lpmode=" . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION) . " LIMIT 1";
        $result = $ilDB->query($sql);
        return $ilDB->numRows($result) > 0;
    }

    public static function update_mandatory_object($course_obj_id, $ref_id, $mandatory) {
        global $DIC;
        $ilDB = $DIC->database();

        if($mandatory) {
            $sql = "SELECT * FROM `ut_lp_settings` WHERE obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND obj_type='crs' LIMIT 1";
            $result = $ilDB->query($sql);

            if ($ilDB->numRows($result) == 0) {
                $sql = "INSERT INTO `ut_lp_settings` (obj_id, obj_type, u_mode, visits) "
                        ." VALUES(" . $ilDB->quote($course_obj_id, "integer") . ", 'crs', " . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION) . ", 0)";
                $result = $ilDB->query($sql);
            } else {
                $sql = "UPDATE `ut_lp_settings` SET u_mode=" . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION) . " WHERE "
                        ."obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND obj_type='crs' LIMIT 1";
                $result = $ilDB->query($sql);
            }
        }
        
        $sql = "SELECT * FROM `ut_lp_collections` WHERE obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND item_id=" . $ilDB->quote($ref_id, "integer") . " LIMIT 1";
        $result = $ilDB->query($sql);
        
        if ($mandatory) {
            if ($ilDB->numRows($result) == 0) {
                $sql = "INSERT INTO `ut_lp_collections` (obj_id, item_id, grouping_id, num_obligatory, active, lpmode) "
                        ." VALUES(" . $ilDB->quote($course_obj_id, "integer") . ", " . $ilDB->quote($ref_id, "integer") . ", 0, 0, 1," . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION) . ")";
                $result = $ilDB->query($sql);
            } else {
                $sql = "UPDATE `ut_lp_collections` SET active=1, lpmode=" . $ilDB->quote(ilLPObjSettings::LP_MODE_COLLECTION) . " WHERE "
                        ."obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND item_id=" . $ilDB->quote($ref_id, "integer") . " LIMIT 1";
                $result = $ilDB->query($sql);
            }
        } else {
            $sql = "DELETE FROM `ut_lp_collections` WHERE obj_id=" . $ilDB->quote($course_obj_id, "integer") . " AND item_id=" . $ilDB->quote($ref_id, "integer") . " LIMIT 1";
            $result = $ilDB->query($sql);
        }
    }


    /* return progress informations for the given object */
    public static function get_obj_progress($obj_id, $user_id) {
        global $DIC;
        $ilDB = $DIC->database();

        $sql = "SELECT * FROM cp_node as a JOIN cmi_node as b ON a.cp_node_id = b.cp_node_id WHERE a.slm_id = " . $ilDB->quote($obj_id, "integer") . " AND b.user_id = " . $ilDB->quote($user_id, "integer") . "";
        $res = $ilDB->query($sql);
        
        $output = [];
        while ($entry = $res->fetch(ilDBConstants::FETCHMODE_OBJECT)) {
            $output[] = $entry;
        }
        return $output;
    }


}