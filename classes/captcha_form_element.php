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

namespace local_captcha;

global $CFG;

require_once($CFG->libdir . '/form/static.php');
require_once(__DIR__ . '/../inc.php');

if (class_exists('\HTML_QuickForm')) {
    \HTML_QuickForm::registerRule('captchavalidated', 'callback', '_validate', '\local_captcha\captcha_form_element');
}

/**
 * captcha type form element
 *
 * HTML class for a captcha type element
 *
 */
class captcha_form_element extends \MoodleQuickForm_static {
    /**
     * @var bool|null bool: value is valid, null: not yet validated
     */
    protected bool|null $_isValid = null;
    protected $_form = null;
    protected string $_value = '';
    protected string $captchaid = '';

    /**
     * @var bool|mixed should the captcha be invalidated automatically, or by by the caller after the $form->get_data()
     */
    protected bool $_setCaptchaUsed = true;

    /**
     * constructor
     *
     * @param string $elementName (optional) name of the captcha element
     * @param string $elementLabel (optional) label for captcha element
     * @param mixed $options (optional) Either a typical HTML attribute string
     *              or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $options = null) {
        if (!$elementName) {
            $elementName = 'captcha_element';
        }
        if (!$elementLabel) {
            $elementLabel = get_string('captcha', 'local_captcha');
        }

        if (isset($options['set_captcha_used'])) {
            $this->_setCaptchaUsed = $options['set_captcha_used'];
        }

        $this->captchaid = optional_param('captchaid', '', PARAM_TEXT);

        parent::__construct($elementName, $elementLabel, '');
    }

    public function toHtml(): string {
        global $OUTPUT, $PAGE;

        $hasError = $this->_isValid === false;
        if (!$hasError && $this->_form) {
            $hasError = !empty($this->_form->_errors[$this->getName()]);
        }

        $language = current_language();
        // strip off the country code
        $language = preg_replace('![_-].*!', '', $language);

        // check if there are audiofiles to show the audio play button
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'local_captcha', 'audio_files', 0, 'itemid', false);
        if (!$files) {
            $audio_files_directory = get_config('local_captcha', 'audio_files_directory');
            if ($audio_files_directory) {
                // with language and char directory
                $files = glob("{$audio_files_directory}/{$language}/a/*.mp3")
                    // Fallback to English if necessary
                    ?: glob("{$audio_files_directory}/en/a/*.mp3");
            }
        }

        $with_audio = !!$files;

        $captcha_data = locallib::get_captcha_data($this->captchaid);
        $this->captchaid = $captcha_data->captchaid;

        $PAGE->requires->js_call_amd('local_captcha/captcha', 'init', [[
            'is_solved' => $captcha_data->is_solved ?? false,
            'captchaid' => $this->captchaid,
            'strings' => [
                'captcha:incorrect' => get_string('captcha:incorrect', 'local_captcha'),
            ]
        ]]);

        $params = [
            'element' => [
                'id' => $this->getAttribute('id'),
                'name' => $this->getName(),
                // only use the existing value, if it was correct
                'value' => $this->_value, // ?: ($captcha_data->solved_phrase ?? ''),
                // mark as required for screeen readers
                'attributes' => 'required="" aria-label="Captcha"',
            ],
            'captchaid' => $this->captchaid,
            'captcha_url' => (new \moodle_url('/local/captcha/captcha.php', ['captchaid' => $this->captchaid, 'rand' => time()]))->out(false),
            'with_audio' => $with_audio,
            'error' => $hasError,
        ];

        return $OUTPUT->render_from_template('local_captcha/captcha', $params);
    }

    public static function setCaptchaUsed() {
        locallib::clear_captcha_data(optional_param('captchaid', '', PARAM_TEXT));
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element.
     * Adds necessary rules to the element and checks that coorenct instance of gradingform_instance
     * is passed in attributes
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param object $caller calling object
     * @return bool
     * @throws moodle_exception
     */
    public function onQuickFormEvent($event, $arg, &$caller) {
        // remember the form for later
        $this->_form = $caller;

        $caller->setType($this->getName(), PARAM_TEXT);

        $name = $this->getName();
        if ($name && $caller->elementExists($name)) {
            if (empty($caller->_rules[$this->getName()])) {
                // rule wasn't already added
                $caller->addRule($name, get_string('required'), 'required', null, 'client');
                $caller->addRule($name, get_string('captcha:incorrect', 'local_captcha'), 'captchavalidated', [
                    // 'form' => $caller,
                    'element' => $this,
                ]);
            }
        }

        return parent::onQuickFormEvent($event, $arg, $caller);
    }

    /**
     * Function registered as rule for this element and is called when this element is being validated.
     * This is a wrapper to pass the validation to the method gradingform_instance::validate_grading_element
     *
     * @param mixed $elementValue value of element to be validated
     * @param array $attributes element attributes
     */
    public static function _validate($elementValue, $attributes = null): bool {
        // $attributes is filled in "addRule()" above
        return $attributes['element']->validate($elementValue);
    }

    public function validate(string $elementValue): bool {
        $elementValue = trim($elementValue);

        if (empty($elementValue)) {
            // kein user input
            return $this->_isValid = false;
        }

        $_isValid = locallib::test_captcha($this->captchaid, $elementValue);

        if (!$_isValid) {
            if ($elementValue) {
                // regenerate phrase on incorrect input
                locallib::clear_captcha_data($this->captchaid);
            }

            return $this->_isValid = false;
        }

        if ($this->_setCaptchaUsed) {
            locallib::clear_captcha_data($this->captchaid);
        }

        $this->_value = $elementValue;
        return $this->_isValid = true;
    }
}
