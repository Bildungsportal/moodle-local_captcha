<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_captcha
 * @copyright  2024 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/inc.php');

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

$phraseBuilder = new PhraseBuilder(6, 'abcdefghijklmnpqrstuvwxyz123456789');

$newCode = false;
if (!empty($SESSION->captcha_phrase) && $SESSION->captcha_time >= microtime(true) - 60 * 10 && !@$_REQUEST['regenerate_captcha']) {
    // same captcha for X minutes
    $builder = new CaptchaBuilder($SESSION->captcha_phrase, $phraseBuilder);
    $builder->build(150, 60, null, $SESSION->captcha_fingerprint);
} else {
    $newCode = true;
    $builder = new CaptchaBuilder(null, $phraseBuilder);
    $builder->build(150, 60);

    $SESSION->captcha_phrase = $builder->getPhrase();
    $SESSION->captcha_time = microtime(true);
    $SESSION->captcha_fingerprint = $builder->getFingerprint();
}

if (optional_param('audio', false, PARAM_BOOL)) {
    header('Content-type: audio/mp3');

    $language = current_language();
    // strip off the country code
    $language = preg_replace('![_-].*!', '', $language);

    // inside the upload area
    $fs = get_file_storage();
    $files = $fs->get_area_files(\context_system::instance()->id, 'local_captcha', 'audio_files', 0, 'itemid', false);
    $filarea_audio_files = [];
    foreach ($files as $file) {
        $filepath = trim($file->get_filepath(), '/');
        if ($filepath) {
            $id = str_replace('/', '_', $filepath);
        } else {
            $id = str_replace('.mp3', '', $file->get_filename());
        }

        // in case the language / character was written uppercase
        $id = strtolower($id);

        if (!isset($filarea_audio_files[$id])) {
            $filarea_audio_files[$id] = [];
        }
        $filarea_audio_files[$id][] = $file;
    }

    $audio_files_directory = get_config('local_captcha', 'audio_files_directory');

    $phrase = strtolower($SESSION->captcha_phrase);
    for ($i = 0; $i < strlen($phrase); $i++) {
        $char = $phrase[$i];

        if ($filarea_audio_files) {
            if (isset($filarea_audio_files[$language . '_' . $char])) {
                $files = $filarea_audio_files[$language . '_' . $char];
            } elseif (isset($filarea_audio_files[$char])) {
                // Alternative: ohne language code zum testen
                $files = $filarea_audio_files[$char];
            } elseif (isset($filarea_audio_files['en' . '_' . $char])) {
                // Fallback: english
                $files = $filarea_audio_files['en' . '_' . $char];
            } else {
                // should not happen, skip the character...
                throw new \moodle_exception("no audio file for char '{$char}' found");
            }

            // randomly get an audio for the character
            $randomKey = array_rand($files);
            $file = $files[$randomKey];

            echo $file->get_content();
        } elseif ($audio_files_directory) {
            if (!is_dir($audio_files_directory)) {
                throw new \moodle_exception('audio files directory not found');
            }

            // directory for the current language
            $files = glob("{$audio_files_directory}/{$language}/{$char}/*.mp3")
                // Fallback to English if necessary
                ?: glob("{$audio_files_directory}/en/{$char}/*.mp3")
                    // Fallback in dev
                    ?: glob("{$audio_files_directory}/{$char}.mp3");

            if (!$files) {
                throw new \moodle_exception("no audio file for char '{$char}' found");
            }

            // randomly get an audio for the character
            $randomKey = array_rand($files);
            $file = $files[$randomKey];

            echo file_get_contents($file);
        } else {
            throw new \moodle_exception('no audio files configured');
        }
    }
} else {
    header('Content-type: image/jpeg');
    $builder->output();
}
