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

function pick_random_file($directory) {
    if (!is_dir($directory)) {
        return null;
    }

    $opendir = opendir($directory);
    if (!$opendir) {
        return null;
    }

    $selected_file = null;
    $count = 0;

    // Iterate through alll files
    while (($file = readdir($opendir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue; // Skip traversals
        }

        $count++;
        // First file always selected, each subsequent file has diminishing odds based on it's index within the dir, resulting in equal odds for each file 
        if (random_int(1, $count) === 1) {
            $selected_file = $directory . '/' . $file;
        }
    }

    closedir($opendir);

    return $selected_file;
}

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
    $fs = get_file_storage();

    $language = current_language();
    // strip off the country code
    $language = preg_replace('![_-].*!', '', $language);

    // inside the upload area
    $files = $fs->get_area_files(\context_system::instance()->id, 'local_captcha', 'audio_files', 0, 'itemid', false);
    $audio_files = [];
    foreach ($files as $file) {
        $filepath = trim($file->get_filepath(), '/');
        if ($filepath) {
            $id = str_replace('/', '_', $filepath);
        } else {
            $id = str_replace('.mp3', '', $file->get_filename());
        }

        // in case the language / character was written uppercase
        $id = strtolower($id);

        if (!isset($audio_files[$id])) {
            $audio_files[$id] = [];
        }
        $audio_files[$id][] = $file;
    }

    // inside the audio_files_directory
    $audio_files_directory = get_config('local_captcha', 'audio_files_directory');
    if ($audio_files_directory) {
        $audio_files = [];
        $language = current_language();
        $language = preg_replace('![_-].*!', '', $language);

        // Only process directories for the current language
        $language_dir = $audio_files_directory . '/' . $language;
        if (is_dir($language_dir)) {
            $dh_char = opendir($language_dir);
            if ($dh_char) {
                while (($char = readdir($dh_char)) !== false) {
                    if ($char === '.' || $char === '..') {
                        continue;
                    }

                    $char_dir = $language_dir . '/' . $char;
                    if (is_dir($char_dir)) {
                        $random_file = pick_random_file($char_dir);
                        if ($random_file) {
                            $id = $language . '_' . $char;
                            if (!isset($audio_files[$id])) {
                                $audio_files[$id] = [];
                            }
                            $audio_files[$id][] = $random_file;
                        }
                    }
                }
                closedir($dh_char);
            }
        }

        // Fallback to English if necessary
        if (empty($audio_files)) {
            $language_dir = $audio_files_directory . '/en';
            if (is_dir($language_dir)) {
                $dh_char = opendir($language_dir);
                if ($dh_char) {
                    while (($char = readdir($dh_char)) !== false) {
                        if ($char === '.' || $char === '..') {
                            continue;
                        }

                        $char_dir = $language_dir . '/' . $char;
                        if (is_dir($char_dir)) {
                            $random_file = pick_random_file($char_dir);
                            if ($random_file) {
                                $id = 'en_' . $char;
                                if (!isset($audio_files[$id])) {
                                    $audio_files[$id] = [];
                                }
                                $audio_files[$id][] = $random_file;
                            }
                        }
                    }
                    closedir($dh_char);
                }
            }
        }
    }


    header('Content-type: audio/mp3');

    $phrase = strtolower($SESSION->captcha_phrase);
    for ($i = 0; $i < strlen($phrase); $i++) {
        $char = $phrase[$i];

        if (isset($audio_files[$language . '_' . $char])) {
            $files = $audio_files[$language . '_' . $char];
        } elseif (isset($audio_files[$char])) {
            // Alternative: ohne language code zum testen
            $files = $audio_files[$char];
        } elseif (isset($audio_files['en' . '_' . $char])) {
            // Fallback: english
            $files = $audio_files['en' . '_' . $char];
        } else {
            // should not happen, skip the character...
            continue;
        }

        // randomly get a character from all variations
        $file = $files[random_int(0, count($files) - 1)];

        // you can just concatenate the single audio files and it will work!
        if (is_string($file)) {
            echo file_get_contents($file);
        } else {
            echo $file->get_content();
        }
    }
} else {
    header('Content-type: image/jpeg');
    $builder->output();
}
