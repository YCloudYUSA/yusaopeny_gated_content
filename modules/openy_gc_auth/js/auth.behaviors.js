(function (Drupal) {
 Drupal.behaviors.openy_gc_auth_store_hash = {
   attach: function (context) {
     // Only run this script on full documents, not ajax requests.
     if (context !== document) {
       return;
     }
     if (!window.location.hash || window.location.hash === '#') {
       return;
     }
     // Same value and attributes js-cookie wrote: path=/, session cookie, and
     // its encoding, which keeps characters like # and / readable.
     var value = encodeURIComponent(window.location.hash)
       .replace(/%(2[346BF]|3[AC-F]|40|5[BDE]|60|7[BCD])/g, decodeURIComponent);
     document.cookie = 'openy_gc_auth_destination=' + value + '; path=/';
   }
 }
})(Drupal);
