/**
 * Drupal behaviors for y LB.
 */
(function ($) {
  "use strict";

  /**
   * Set fixed position for the main page header and Virtual Y navigation bar.
   */
  Drupal.behaviors.yLBHeaderPosition = {
    attach: function (context, settings) {
      const breakpoint = 768;
      let pageHeaderTop = $('.ws-header .header--top');
      let virtualYheaderTopMenu = $('#gated-content .top-menu');
      let openyAlertsHeader = $('#openy_alerts_app_header');

      if (window.screen.width < breakpoint) {
        const headerBottom = 95;
        const virtualYheaderHeight = 47;
        let mobileHeaderTopHeight = pageHeaderTop.outerHeight();
        let openyAlertsHeaderMarginTop = mobileHeaderTopHeight + virtualYheaderHeight + headerBottom;
        let virtualYheaderTopPosition= mobileHeaderTopHeight + headerBottom;
        virtualYheaderTopMenu.css('top', virtualYheaderTopPosition + 'px');
        if (openyAlertsHeader) {
          openyAlertsHeader.css('margin-top', openyAlertsHeaderMarginTop + 'px');
        }
      }
    }
  };
})(jQuery);
