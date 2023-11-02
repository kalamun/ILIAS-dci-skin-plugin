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

    foreach ($finder->query('//div[contains(@class, "cntr_VAccordICntr")]') as $node) {
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
        $is_completed = $progress_completed >= $progress_total;

        if ($progress_total > 0) {
          $progress_icon = $dom->createElement('span');
          $progress_icon->setAttribute('class', 'icon-done');
          $progress = $dom->createElement('div', $progress_completed . "/" . $progress_total);
          $progress->setAttribute('class', 'accordion-progress' . ($is_completed ? ' completed' : ' not-completed'));
          if ($is_completed) $progress->insertBefore($progress_icon, $progress->firstChild);
          $heading->appendChild($progress);
        }
        
        if (!$is_completed || $progress_total == 0) {
          $content_wrapper = $finder->query('.//div[contains(@class, "il_VAccordionContentDef")]', $node)[0];
          $content_wrapper->setAttribute('class', str_replace('ilAccHideContent', '', $content_wrapper->getAttribute('class')));
        }
        
        // toggle
        $toggle = $dom->createElement('div');
        $toggle->setAttribute('class', 'icon-down accordion-toggle');
        $heading->appendChild($toggle);
      }
    }

    $html = str_replace('<?xml encoding="utf-8" ?>', "", $dom->saveHTML());
    $html = str_replace("<html><body>", "", $html);
    $html = str_replace("</body></html>", "", $html);

    return $html;
  }

}

