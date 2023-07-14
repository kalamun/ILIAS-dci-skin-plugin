<?php
/**
 * Generic layout functions
 */

class dciSkin_layout {

    public static function apply_custom_placeholders($html) {
        $html = str_replace("{SKIN_URI}", "/Customizing/global/skin/dci", $html);
        return $html;
    }

    public static function remove_default_cards($html) {
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

}