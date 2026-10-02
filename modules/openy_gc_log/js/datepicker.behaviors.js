(function (Drupal, once) {
 Drupal.behaviors.openy_gc_log_datepicker = {
   attach: function (context) {
     // Native date inputs submit yyyy-mm-dd, the format the jQuery UI datepicker produced.
     once('openy-gc-log-datepicker', '#edit-created-min, #edit-created-max, #edit-changed-from, #edit-changed-to', context).forEach(function (input) {
       input.type = 'date';
     });
   }
 }
})(Drupal, once);
