<?php
/**
 * Store and retrieve precomputed data from the caching system
 */

class dciSkin_cache
{
    private static $table_name           = "dci_cache";
    private static $db                   = false;
    private static $user                 = false;
    private static $cache_enabled        = false;
    private static $expiration_timeframe = 604800; // one week

    public static function construct()
    {
        global $DIC;
        self::$user          = $DIC->user();
        self::$db            = $DIC->database();
        self::$cache_enabled = $DIC['ilias']->getSetting("dci_cache_enabled");
    }

    public static function purgeCache(): bool
    {
        self::construct();
        $sql     = "TRUNCATE `" . self::$table_name . "`";
        $results = self::$db->query($sql);
        return ! empty($results);
    }

    private function get_user_id($user_id = false)
    {
        if ($user_id === false) {
            return self::$user->getId();
        }
        return $user_id;
    }

    private function get_current_page_type()
    {
        if (strtolower($_GET['baseClass']) == 'ilrepositorygui' && strtolower($_GET['cmdClass']) == 'ilrepositorygui') {
            return ['type' => 'page', 'object_id' => $_GET['ref_id']];
        }
    }

    /* main function to trigger caching on specific scenarios */
    public static function on_loading_page()
    {
        if (empty(self::$cache_enabled)) {
            return;
        }

        $scenario = self::get_current_page_type();
    }

    public static function add($type, $value, $object_id = 0, $user_id = false): bool
    {
        self::construct();

        if (empty(self::$cache_enabled)) {
            return false;
        }

        $user_id = self::get_user_id($user_id);

        $entry = self::get($type, $object_id, $user_id, true/* ignore expiration */);

        if (! self::is_valid($entry)) {
            $result = self::$db->manipulateF(
                "INSERT INTO `" . self::$table_name . "` (`value`, `updated_at`, `type`, `object_id`, `user_id`) VALUES(%s, NOW(), %s, %d, %d)",
                [
                    'text',
                    'text',
                    'integer',
                    'integer',
                ], [
                    self::$db->escape(serialize($value)),
                    self::$db->escape($type),
                    $object_id,
                    $user_id,
                ]
            );
        } else {
            $result = self::$db->manipulateF(
                "UPDATE `" . self::$table_name . "` SET `value` = %s, `updated_at` = NOW() WHERE `type` = %s AND object_id = %d AND user_id = %d",
                [
                    'text',
                    'text',
                    'integer',
                    'integer',
                ], [
                    self::$db->escape(serialize($value)),
                    self::$db->escape($type),
                    $object_id,
                    $user_id,
                ]
            );
        }

        return ! empty($result);
    }

    public static function get($type, $object_id = 0, $user_id = false, $ignore_expiration = false)
    {
        if (empty(self::$cache_enabled)) {
            return "cache-null-return";
        }

        self::construct();
        $user_id = self::get_user_id($user_id);

        $expiration_datetime = $ignore_expiration ? '1970-01-01 01:00:00' : date('Y-m-d H:i:s', time() - self::$expiration_timeframe);

        $sql     = "SELECT * FROM `" . self::$table_name . "` WHERE `type` = " . self::$db->quote($type, "text") . " AND object_id = " . self::$db->quote($object_id, "integer") . " AND user_id = " . self::$db->quote($user_id, "integer") . " AND `updated_at` > '" . $expiration_datetime . "' LIMIT 1";
        $results = self::$db->query($sql);
        $entry   = $results->fetch(ilDBConstants::FETCHMODE_OBJECT);
        if (! empty($entry->value)) {
            return unserialize($entry->value);
        }

        return "cache-null-return";
    }

    public static function is_valid($value)
    {
        return $value !== "cache-null-return";
    }

}
