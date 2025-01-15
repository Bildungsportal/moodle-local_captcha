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

$string['pluginname'] = 'Captcha';


$string['captcha'] = 'Captcha';
$string['captcha:incorrect'] = 'Captcha was not valid!';

$string['play_captcha_audio'] = 'Play audio';
$string['privacy:metadata'] = 'No personal data is stored with this plugin.';

$string['reload_captcha'] = 'Reload Captcha';

$string['settings:audio_files'] = 'Mp3-files upload';
$string['settings:audio_files:description'] = 'Format: lang_char.mp3 (eg. "en_a.mp3", "en_b.mp3", ...) or subdirectories: lang/char/some.mp3 (randomly selects a file from the directory)';
$string['settings:audio_files_directory'] = 'MP3-files path';
$string['settings:audio_files_directory:description'] = 'As an alternative to the file upload, files can be placed in a local directory. Please enter a valid absolute path to use these files instead of the upload.';
