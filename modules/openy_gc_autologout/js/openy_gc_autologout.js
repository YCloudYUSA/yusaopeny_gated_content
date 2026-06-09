/**
 * @file
 * JavaScript for openy_gc_autologout.
 */
(function ($, Drupal) {

  var awayCallback = function() {
    var video = $('.video iframe');
    // Check if user is on video page and logout if session expired and video is not playing.
    if (video.length > 0) {
      var videoUrl = video.attr('src');
      var playerType = videoUrl.indexOf('vimeo') !== -1 ? 'vimeo' : 'youtube';
      var player = {};
      if (playerType === 'vimeo') {
        player = new Vimeo.Player(video);
        player.getPaused().then(function (paused) {
          if (paused) {
            awayCallbackLogout();
          }
        });
      }
      else if (playerType === 'youtube') {
        player = YT.get(video.attr('id'));
        // 2 video is on pause.
        if (player.getPlayerState() === 2) {
          awayCallbackLogout();
        }
      }
    }
    // Logout if session expired and user is on other pages.
    else {
      awayCallbackLogout();
    }
  };

  var awayCallbackLogout = function() {
    $.ajax({
      url: drupalSettings.path.baseUrl + "openy_gc_autologout",
      type: "POST",
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-Requested-With', {
          toString: function () {
            return '';
          }
        });
      },
      success: function () {
        window.location = drupalSettings.openy_gc_autologout.redirect_url;
      },
      error: function (XMLHttpRequest, textStatus) {
        if (XMLHttpRequest.status === 403 || XMLHttpRequest.status === 404) {
          window.location = drupalSettings.openy_gc_autologout.redirect_url;
        }
      }
    });
  };

  var idle = new Idle({
    onAway: awayCallback,
    awayTimeout: typeof drupalSettings.openy_gc_autologout.autologout_timeout !== undefined ? drupalSettings.openy_gc_autologout.autologout_timeout * 1000 : 7200 * 1000,
  }).start();

})(jQuery, Drupal);
