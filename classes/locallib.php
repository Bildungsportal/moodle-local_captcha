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

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

require_once(__DIR__ . '/../inc.php');

class locallib {
    const CAPTCHA_TIMEOUT = 60 * 60 * 24; // 1 day

    public static function get_captcha_data(string $captchaid, bool $regenerate = false): object {
        global $SESSION;

        if (!$regenerate && ($SESSION->local_captcha_data[$captchaid]->valid_until ?? 0) >= time()) {
            // captcha ok
        } else {
            // delete old captcha
            static::clear_captcha_data($captchaid);

            // create new captcha
            $captchaid = uniqid('', true);
            $phraseBuilder = new PhraseBuilder(6, 'abcdefghijklmnpqrstuvwxyz123456789');
            $builder = new CaptchaBuilder(null, $phraseBuilder);

            // this creates the fingerprint
            $builder->build(150, 60);

            $SESSION->local_captcha_data = $SESSION->local_captcha_data ?? [];
            $SESSION->local_captcha_data[$captchaid] = (object)[
                'phrase' => $builder->getPhrase(),
                'captchaid' => $captchaid,
                'valid_until' => time() + self::CAPTCHA_TIMEOUT,
                'fingerprint' => $builder->getFingerprint(),
                'tries' => 0,
            ];
        }

        return $SESSION->local_captcha_data[$captchaid];
    }

    public static function clear_captcha_data(string $captchaid): void {
        global $SESSION;
        unset($SESSION->local_captcha_data[$captchaid]);
    }

    public static function test_captcha(string $captchaid, string $value): bool {
        global $SESSION;

        $captcha_data = static::get_captcha_data($captchaid);
        $builder = new \Gregwar\Captcha\CaptchaBuilder($captcha_data->phrase);
        // testPhrase() also fuzzy-compares 0 and o and 1 and l (lowercase L)
        // lowercase, because the captcha is lowercase and so we can do an case-insensitive compare
        $isValid = $builder->testPhrase(strtolower($value));

        if ($isValid) {
            $SESSION->local_captcha_data[$captchaid]->is_solved = true;
            // $SESSION->local_captcha_data[$captchaid]->solved_phrase = $value;
            $SESSION->local_captcha_data[$captchaid]->valid_until = time() + 60 * 60 * 24 * 365;
        } elseif (isset($SESSION->local_captcha_data[$captchaid])) {
            $SESSION->local_captcha_data[$captchaid]->tries++;
            if ($SESSION->local_captcha_data[$captchaid]->tries >= 15) {
                // invalidate after 5 tries imut if
                static::clear_captcha_data($captchaid);
            }
        }

        return $isValid;
    }
}
