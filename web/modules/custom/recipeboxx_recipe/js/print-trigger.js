/**
 * @file
 * Handles print dialog form submission to open in new window.
 */

(function (Drupal, $) {
  'use strict';

  /**
   * Intercept form submission to open print view in new window.
   */
  Drupal.behaviors.recipePrintTrigger = {
    attach: function (context, settings) {
      // Handle form submission in modal
      $(document).on('submit', '#recipeboxx-recipe-print-options-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var sections = [];
        $form.find('input[name^="sections["]:checked').each(function() {
          sections.push($(this).val());
        });

        var nodeId = $form.find('input[name="node_id"]').val();
        var printUrl = '/recipe/' + nodeId + '/print';

        if (sections.length > 0) {
          printUrl += '?sections=' + sections.join(',');
        }

        // Open in new window
        window.open(printUrl, '_blank');

        // Close modal
        $('.ui-dialog-titlebar-close').trigger('click');

        return false;
      });
    }
  };

})(Drupal, jQuery);
