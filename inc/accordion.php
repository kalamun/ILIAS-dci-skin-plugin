<?php
/**
 * Convert standard accordions to customized ones
 * also adding progress indications
 */

class dciSkin_accordion {

    public static function apply($html) {

        $dom = new DomDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);
        $finder = new DomXPath($dom);

        foreach ($finder->query('//div[contains(@class, "ilc_va_cntr_VAccordCntr")]') as $node) {
          $node->setAttribute('class', $node->getAttribute('class') . ' dci-accordion');
          
          // heading
          $heading_wrapper = $finder->query('.//div[contains(@class, "ilc_va_ihead_VAccordIHead")]', $node)[0];
          $heading_wrapper->setAttribute('class', 'dci-accordion-heading');
          $heading = $finder->query('.//div', $heading_wrapper)[0];

          if ($heading) {
            $h2 = $dom->createElement('h2', htmlentities($heading->textContent));
            while ($heading->hasChildNodes()) {
              $heading->removeChild($heading->firstChild);
            }
            $heading->appendChild($h2);
            
            // progress
            $progress_total = count($finder->query('.//div[contains(@class, "kalamun-card_progress")]', $node));
            $progress_completed = count($finder->query('.//div[contains(@class, "kalamun-card_progress completed")]', $node));
            if ($progress_total > 0) {
              $is_completed = $progress_completed >= $progress_total;

              $progress_icon = $dom->createElement('span');
              $progress_icon->setAttribute('class', 'icon-done');
              $progress = $dom->createElement('div', $progress_completed . "/" . $progress_total);
              $progress->setAttribute('class', 'accordion-progress' . ($is_completed ? ' completed' : ' not-completed'));
              if ($is_completed) $progress->insertBefore($progress_icon, $progress->firstChild);
              $heading->appendChild($progress);
            }
            
            // toggle
            $toggle = $dom->createElement('div');
            $toggle->setAttribute('class', 'icon-down accordion-toggle');
            $heading->appendChild($toggle);
          }
        }

        return $dom->saveHTML();
    }

}
