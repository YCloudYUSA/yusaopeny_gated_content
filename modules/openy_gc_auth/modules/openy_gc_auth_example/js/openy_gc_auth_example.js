/**
 * @file
 * JavaScript for openy_gc_auth_example.
 */
(function ($, Drupal) {

  Drupal.behaviors.openy_gc_auth_example = {
    attach: function(context, settings) {
      $('input[data-autosubmit=1]', context).once('openy_gc_auth_example').form().submit();
    }
  };

})(jQuery, Drupal);
