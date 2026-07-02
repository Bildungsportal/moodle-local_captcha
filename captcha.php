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
use local_captcha\locallib;

$captchaid = required_param('captchaid', PARAM_TEXT);
$regenerate_captcha = optional_param('regenerate_captcha', false, PARAM_BOOL);
$captcha_data = locallib::get_captcha_data($captchaid, $regenerate_captcha);

$type = optional_param('type', '', PARAM_TEXT);

if ($type == 'audio') {
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

    $phrase = strtolower($captcha_data->phrase);
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
} elseif ($type == 'json') {
    if ($captchaid == $captcha_data->captchaid) {
        header("Content-type: application/json");
        echo json_encode([
            'is_same' => true,
        ]);
        exit;
    }

    $builder = new CaptchaBuilder($captcha_data->phrase, null);
    $builder->build(150, 60, null, $captcha_data->fingerprint);
    ob_start();
    $builder->output();
    $image_data = ob_get_clean();
    $image_data = base64_encode($image_data);

    header("Content-type: application/json");
    echo json_encode([
        'is_same' => false,
        'captchaid' => $captcha_data->captchaid,
        'image_data' => 'data:image/jpeg;base64,' . $image_data,
    ]);
} elseif ($type == 'check') {
    $captcha = required_param('captcha', PARAM_TEXT);
    $isSolved = locallib::test_captcha($captchaid, $captcha);

    header("Content-type: application/json");
    echo json_encode([
        'is_solved' => $isSolved,
        'captchaid' => $isSolved ? $captchaid : locallib::get_captcha_data($captchaid)->captchaid,
    ]);

} else {
    header('Content-type: image/jpeg');
    $builder = new CaptchaBuilder($captcha_data->phrase, null);
    $builder->build(150, 60, null, $captcha_data->fingerprint);
    $builder->output();
}
