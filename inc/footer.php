<?php
/**
 * Cleanup the footer
 */

class dciSkin_footer {

    public static function apply($html) {

        $html = preg_replace("/powered by ILIAS \(v(.*?) .*?\)/", "ILIAS $1", $html);

        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        foreach ($finder->query('//nav[contains(@class, "il-mainbar")]') as $node) {
        }
        
        $html = str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
        $html = str_replace("<html><body>", "", $html);
        $html = str_replace("</body></html>", "", $html);
    
        return $html;
    }

}

