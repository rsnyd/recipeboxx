/**
 * @file
 * Automatically trigger print dialog on print view page.
 */

(function (Drupal) {
  'use strict';

  /**
   * Auto-trigger print dialog when page loads.
   */
  Drupal.behaviors.recipePrintAuto = {
    attach: function (context, settings) {
      if (context === document) {
        // Small delay to ensure page is fully rendered
        setTimeout(function() {
          window.print();
        }, 500);
      }
    }
  };

})(Drupal);
