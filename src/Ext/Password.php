<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace TikiManager\Ext;

/**
 * Class to create passwords
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Text
 * @package    Text_Password
 * @author     Martin Jansen <mj@php.net>
 * @author     Olivier Vanhoucke <olivier@php.net>
 * @copyright  2004-2005 Martin Jansen, Olivier Vanhoucke
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Text_Password
 */

/**
 * Number of possible characters in the password
 */
$_Text_Password_NumberOfPossibleCharacters = 0;

/**
 * Main class for the Text_Password package
 *
 * @category   Text
 * @package    Text_Password
 * @author     Martin Jansen <mj@php.net>
 * @author     Olivier Vanhoucke <olivier@php.net>
 * @copyright  2004-2005 Martin Jansen, Olivier Vanhoucke
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Text_Password
 */
class Password
{

    /**
     * Create a single password.
     *
     * @access public
     * @param  integer Length of the password.
     * @param  string  Type of password (pronounceable, unpronounceable)
     * @param  string  Character which could be use in the
     *                 unpronounceable password ex : 'A,B,C,D,E,F,G'
     *                 or numeric, alphabetical or alphanumeric.
     * @return string  Returns the generated password.
     */
    public static function create($length = 10, $type = 'pronounceable', $chars = '')
    {
        switch ($type) {
            case 'unpronounceable':
                return Password::createUnpronounceable($length, $chars);

            case 'pronounceable':
            default:
                return Password::createPronounceable($length);
        }
    }

    /**
     * Create multiple, different passwords
     *
     * Method to create a list of different passwords which are
     * all different.
     *
     * @access public
     * @param  integer Number of different password
     * @param  integer Length of the password
     * @param  string  Type of password (pronounceable, unpronounceable)
     * @param  string  Character which could be use in the
     *                 unpronounceable password ex : 'A,B,C,D,E,F,G'
     *                 or numeric, alphabetical or alphanumeric.
     * @return array   Array containing the passwords
     */
    public static function createMultiple($number, $length = 10, $type = 'pronounceable', $chars = '')
    {
        $passwords = [];

        while ($number > 0) {
            while (true) {
                $password = Password::create($length, $type, $chars);
                if (!in_array($password, $passwords)) {
                    $passwords[] = $password;
                    break;
                }
            }
            $number--;
        }
        return $passwords;
    }

    /**
     * Create password from login
     *
     * Method to create password from login
     *
     * @access public
     * @param  string  Login
     * @param  string  Type
     * @param  integer Key
     * @return string
     */
    public static function createFromLogin($login, $type, $key = 0)
    {
        switch ($type) {
            case 'reverse':
                return strrev($login);

            case 'shuffle':
                return Password::shuffle($login);

            case 'xor':
                return Password::xor($login, $key);

            case 'rot13':
                return str_rot13($login);

            case 'rotx':
                return Password::rotx($login, $key);

            case 'rotx++':
                return Password::rotxpp($login, $key);

            case 'rotx--':
                return Password::rotxmm($login, $key);

            case 'ascii_rotx':
                return Password::asciiRotx($login, $key);

            case 'ascii_rotx++':
                return Password::asciiRotxpp($login, $key);

            case 'ascii_rotx--':
                return Password::asciiRotxmm($login, $key);
        }

        return '';
    }

    /**
     * Create multiple, different passwords from an array of login
     *
     * Method to create a list of different password from login
     *
     * @access public
     * @param  array   Login
     * @param  string  Type
     * @param  integer Key
     * @return array   Array containing the passwords
     */
    public static function createMultipleFromLogin($login, $type, $key = 0)
    {
        $passwords = [];
        $number    = count($login);
        $save      = $number;

        while ($number > 0) {
            while (true) {
                $password = Password::createFromLogin($login[$save - $number], $type, $key);
                if (!in_array($password, $passwords)) {
                    $passwords[] = $password;
                    break;
                }
            }
            $number--;
        }
        return $passwords;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function xor($login, $key)
    {
        $tmp = '';

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++) {
            $next = ord($login[$i]) ^ $key;
            if ($next > 255) {
                $next -= 255;
            } elseif ($next < 0) {
                $next += 255;
            }
            $tmp .= chr($next);
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     * lowercase only
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function rotx($login, $key)
    {
        $tmp = '';
        $login = strtolower($login);

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++) {
            if ((ord($login[$i]) >= 97) && (ord($login[$i]) <= 122)) { // 65, 90 for uppercase
                $next = ord($login[$i]) + $key;
                if ($next > 122) {
                    $next -= 26;
                } elseif ($next < 97) {
                    $next += 26;
                }
                $tmp .= chr($next);
            } else {
                $tmp .= $login[$i];
            }
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     * lowercase only
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function rotxpp($login, $key)
    {
        $tmp = '';
        $login = strtolower($login);

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++, $key++) {
            if ((ord($login[$i]) >= 97) && (ord($login[$i]) <= 122)) { // 65, 90 for uppercase
                $next = ord($login[$i]) + $key;
                if ($next > 122) {
                    $next -= 26;
                } elseif ($next < 97) {
                    $next += 26;
                }
                $tmp .= chr($next);
            } else {
                $tmp .= $login[$i];
            }
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     * lowercase only
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function rotxmm($login, $key)
    {
        $tmp = '';
        $login = strtolower($login);

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++, $key--) {
            if ((ord($login[$i]) >= 97) && (ord($login[$i]) <= 122)) { // 65, 90 for uppercase
                $next = ord($login[$i]) + $key;
                if ($next > 122) {
                    $next -= 26;
                } elseif ($next < 97) {
                    $next += 26;
                }
                $tmp .= chr($next);
            } else {
                $tmp .= $login[$i];
            }
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function asciiRotx($login, $key)
    {
        $tmp = '';

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++) {
            $next = ord($login[$i]) + $key;
            if ($next > 255) {
                $next -= 255;
            } elseif ($next < 0) {
                $next += 255;
            }
            switch ($next) { // delete white space
                case 0x09:
                case 0x20:
                case 0x0A:
                case 0x0D:
                    $next++;
            }
            $tmp .= chr($next);
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function asciiRotxpp($login, $key)
    {
        $tmp = '';

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++, $key++) {
            $next = ord($login[$i]) + $key;
            if ($next > 255) {
                $next -= 255;
            } elseif ($next < 0) {
                $next += 255;
            }
            switch ($next) { // delete white space
                case 0x09:
                case 0x20:
                case 0x0A:
                case 0x0D:
                    $next++;
            }
            $tmp .= chr($next);
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     *
     * @access private
     * @param  string  Login
     * @param  integer Key
     * @return string
     */
    private static function asciiRotxmm($login, $key)
    {
        $tmp = '';

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++, $key--) {
            $next = ord($login[$i]) + $key;
            if ($next > 255) {
                $next -= 255;
            } elseif ($next < 0) {
                $next += 255;
            }
            switch ($next) { // delete white space
                case 0x09:
                case 0x20:
                case 0x0A:
                case 0x0D:
                    $next++;
            }
            $tmp .= chr($next);
        }

        return $tmp;
    }

    /**
     * Helper method to create password
     *
     * Method to create a password from a login
     *
     * @access private
     * @param  string  Login
     * @return string
     */
    private static function shuffle($login)
    {
        $tmp = [];

        for ($i = 0, $tmp_count = strlen($login); $i < $tmp_count; $i++) {
            $tmp[] = $login[$i];
        }

        shuffle($tmp);

        return implode($tmp, '');
    }

    /**
     * Create pronounceable password
     *
     * This method creates a string that consists of
     * vowels and consonats.
     *
     * @access private
     * @param  integer Length of the password
     * @return string  Returns the password
     */
    private static function createPronounceable($length)
    {

        global $_Text_Password_NumberOfPossibleCharacters;
        $retVal = '';

        /**
         * List of vowels and vowel sounds
         */
        $v = ['a', 'e', 'i', 'o', 'u', 'ae', 'ou', 'io',
                   'ea', 'ou', 'ia', 'ai'
                   ];

        /**
         * List of consonants and consonant sounds
         */
        $c = ['b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm',
                   'n', 'p', 'r', 's', 't', 'u', 'v', 'w',
                   'tr', 'cr', 'fr', 'dr', 'wr', 'pr', 'th',
                   'ch', 'ph', 'st', 'sl', 'cl'
                   ];

        $v_count = 12;
        $c_count = 29;

        $_Text_Password_NumberOfPossibleCharacters = $v_count + $c_count;

        for ($i = 0; $i < $length; $i++) {
            $retVal .= $c[mt_rand(0, $c_count-1)] . $v[mt_rand(0, $v_count-1)];
        }

        return substr($retVal, 0, $length);
    }

    /**
     * Create unpronounceable password
     *
     * This method creates a random unpronounceable password
     *
     * @access private
     * @param  integer Length of the password
     * @param  string  Character which could be use in the
     *                 unpronounceable password ex : 'ABCDEFG'
     *                 or numeric, alphabetical or alphanumeric.
     * @return string  Returns the password
     */
    private static function createUnpronounceable($length, $chars)
    {
        global $_Text_Password_NumberOfPossibleCharacters;

        $password = '';

        /**
         * List of character which could be use in the password
         */
        switch ($chars) {
            case 'alphanumeric':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                $_Text_Password_NumberOfPossibleCharacters = 62;
                break;

            case 'alphabetical':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                $_Text_Password_NumberOfPossibleCharacters = 52;
                break;

            case 'numeric':
                $chars = '0123456789';
                $_Text_Password_NumberOfPossibleCharacters = 10;
                break;

            case '':
                $chars = '_#@%&ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                $_Text_Password_NumberOfPossibleCharacters = 67;
                break;

            default:
                /**
                 * Some characters shouldn't be used
                 */
                $chars = trim($chars);
                $chars = str_replace(['+', '|', '$', '^', '/', '\\', ','], '', $chars);

                $_Text_Password_NumberOfPossibleCharacters = strlen($chars);
        }

         /**
          * Generate password
          */
        for ($i = 0; $i < $length; $i++) {
            $num = mt_rand(0, $_Text_Password_NumberOfPossibleCharacters - 1);
            $password .= $chars[$num];
        }

         /**
          * Return password
          */
         return $password;
    }
}
