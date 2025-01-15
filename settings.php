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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_captcha', get_string('pluginname', 'local_captcha'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configstoredfile(
            'local_captcha/audio_files',
            get_string('settings:audio_files', 'local_captcha'),
            get_string('settings:audio_files:description', 'local_captcha'),
            'audio_files',
            0,
            [
                'maxfiles' => 1000,
                'accepted_types' => ['mp3'],
                'subdirs' => 1,
            ]
        ));

        $settings->add(new \admin_setting_configfile(
            "local_captcha/audio_files_directory",
            get_string('settings:audio_files_directory', 'local_captcha'),
            get_string('settings:audio_files_directory:description', 'local_captcha'),
            '',
        ));
    }
}
