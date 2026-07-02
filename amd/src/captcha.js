/* eslint-disable jsdoc/require-jsdoc, no-console, max-len */

import $ from 'jquery';
import Cfg from 'core/config';

let inited = false;
let captchaAudio = null;
let refreshTimeout = null;
let captchaid = null;
let is_solved = false;
let config = null;

let refresh_time = 10; // default 30 seconds

async function refreshCaptcha(regenerate_captcha) {
  const url = Cfg.wwwroot + '/local/captcha/captcha.php?type=json';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      captchaid,
      regenerate_captcha: regenerate_captcha ? '1' : '0',
    })
  });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status} while fetching ${url}`);
  }

  const data = await response.json();

  if (!data.is_same) {
    // captcha has changed
    captchaid = data.captchaid;
    is_solved = false;
    $('input[name="captchaid"]').val(captchaid);

    // Bild aktualisieren
    $('#local_captcha-captcha_image').attr('src', data.image_data);

    // Audio beenden
    if (captchaAudio) {
      // reset audio, if playing
      captchaAudio.pause();
      captchaAudio = null;
    }

    // Eingabe löschen
    $('.local_captcha-input').val('');
  }

  if (refreshTimeout) {
    clearTimeout(refreshTimeout);
    refreshTimeout = null;
  }

  rerenderCaptcha();
  refreshTimeout = setTimeout(() => {
    refreshCaptcha(false);
  }, refresh_time * 1000);
}

function playCaptchaAudio() {
  if (!captchaAudio) {
    captchaAudio = new Audio(Cfg.wwwroot + '/local/captcha/captcha.php?type=audio&captchaid=' + captchaid + '&' + Math.random());
  }
  if (captchaAudio.paused) {
    captchaAudio.play();
  } else {
    captchaAudio.pause();
    captchaAudio.currentTime = 0; // Reset the audio to the beginning
  }
}

function focusNextFormElement() {
  const $fitem = $('#local_captcha-captcha_container').closest('.fitem');
  const focusableSelector = 'input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])';
  const $nextFocusable = $fitem.nextAll().find(focusableSelector).filter(':visible').first();

  if ($nextFocusable.length) {
    $nextFocusable.focus();
  }
}

function rerenderCaptcha() {
  const value = $('.local_captcha-input').val();

  $('#local_captcha-captcha_container').show();
  $('#local_captcha-captcha_container').toggleClass('is_solved', is_solved);
  const $fitem = $('#local_captcha-captcha_container').closest('.fitem');
  $fitem.find('.invalid-feedback').toggle(!is_solved);
  $fitem.find('.text-danger').toggle(!is_solved);

  if (value.length == 6 && !is_solved) {
    $fitem.find('.form-control-feedback').addClass('invalid-feedback').html(config.strings['captcha:incorrect']);
  } else if (value.length > 0) {
    $fitem.find('.form-control-feedback').html('');
  }
}

async function checkCaptcha() {
  const value = $('.local_captcha-input').val();

  const url = Cfg.wwwroot + '/local/captcha/captcha.php?type=check';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      captchaid,
      captcha: value,
    })
  });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status} while fetching ${url}`);
  }

  const data = await response.json();

  is_solved = data.is_solved;

  if (captchaid != data.captchaid) {
    // was changed somehow
    refreshCaptcha(false);
    return;
  }

  // Check if focus is on captcha input before rerenderCaptcha() hides it
  const $captchaInput = $('.local_captcha-input');
  const focusWasOnCaptcha = document.activeElement === $captchaInput[0];

  rerenderCaptcha();

  // Focus the next visible form element after captcha is solved
  if (is_solved && focusWasOnCaptcha) {
    focusNextFormElement();
  }
}

export function init(_config) {
  if (inited) {
    return;
  }
  inited = true;

  config = _config;
  captchaid = config.captchaid;
  is_solved = config.is_solved;

  if (performance.getEntriesByType("navigation")[0].type == 'back_forward') {
    refreshCaptcha(false);
  }else {
    refreshTimeout = setTimeout(() => {
      refreshCaptcha(false);
    }, refresh_time * 1000);
  }

  $(document).on('input', '.local_captcha-input', function() {
    if (this.value.length == 6) {
      checkCaptcha();
    } else {
      rerenderCaptcha();
    }
  });
  rerenderCaptcha();

  $(document).on('click', '#local_captcha-play_audio', function (e) {
    e.preventDefault();
    this.blur();

    playCaptchaAudio();
  });

  $(document).on('click', '#local_captcha-regnerate_captcha', function (e) {
    e.preventDefault();
    this.blur();

    refreshCaptcha(true);
  });

  // prevent form submission, until captcha is correct
  $('.local_captcha-input').closest('form').submit(function () {
    if (!is_solved) {
      const $fitem = $('#local_captcha-captcha_container').closest('.fitem');
      $fitem.find('.form-control-feedback').addClass('invalid-feedback').html(config.strings['captcha:incorrect']);
      $('#local_captcha-captcha_container .local_captcha-input').focus();
      return false;
    }
  });
}
