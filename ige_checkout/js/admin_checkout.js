(function ($, Drupal) {
  'use strict';

  $(document).ready(function(event) {
    if ($('.ige-select-address').length > 0) {
      $('.ige-select-address').val('_new');
      $('.ige-select-address').trigger('change');
    }
  });

})(jQuery, Drupal);
