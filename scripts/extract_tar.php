<?php
/**
 * PEAR, the PHP Extension and Application Repository
 *
 * PEAR class and PEAR_Error class
 *
 * PHP versions 4 and 5
 *
 * @category   pear
 * @package    PEAR
 * @author     Sterling Hughes <sterling@php.net>
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2010 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 0.1
 */

/**#@+
 * ERROR constants
 */
define('PEAR_ERROR_RETURN',     1);
define('PEAR_ERROR_PRINT',      2);
define('PEAR_ERROR_TRIGGER',    4);
define('PEAR_ERROR_DIE',        8);
define('PEAR_ERROR_CALLBACK',  16);
/**
 * WARNING: obsolete
 * @deprecated
 */
define('PEAR_ERROR_EXCEPTION', 32);
/**#@-*/

if (substr(PHP_OS, 0, 3) == 'WIN') {
    define('OS_WINDOWS', true);
    define('OS_UNIX',    false);
    define('PEAR_OS',    'Windows');
} else {
    define('OS_WINDOWS', false);
    define('OS_UNIX',    true);
    define('PEAR_OS',    'Unix'); // blatant assumption
}

$GLOBALS['_PEAR_default_error_mode']     = PEAR_ERROR_RETURN;
$GLOBALS['_PEAR_default_error_options']  = E_USER_NOTICE;
$GLOBALS['_PEAR_destructor_object_list'] = array();
$GLOBALS['_PEAR_shutdown_funcs']         = array();
$GLOBALS['_PEAR_error_handler_stack']    = array();

@ini_set('track_errors', true);

/**
 * Base class for other PEAR classes.  Provides rudimentary
 * emulation of destructors.
 *
 * If you want a destructor in your class, inherit PEAR and make a
 * destructor method called _yourclassname (same name as the
 * constructor, but with a "_" prefix).  Also, in your constructor you
 * have to call the PEAR constructor: $this->PEAR();.
 * The destructor method will be called without parameters.  Note that
 * at in some SAPI implementations (such as Apache), any output during
 * the request shutdown (in which destructors are called) seems to be
 * discarded.  If you need to get any debug information from your
 * destructor, use error_log(), syslog() or something similar.
 *
 * IMPORTANT! To use the emulated destructors you need to create the
 * objects by reference: $obj =& new PEAR_child;
 *
 * @category   pear
 * @package    PEAR
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.10.7
 * @link       http://pear.php.net/package/PEAR
 * @see        PEAR_Error
 * @since      Class available since PHP 4.0.2
 * @link        http://pear.php.net/manual/en/core.pear.php#core.pear.pear
 */
class PEAR
{
    /**
     * Whether to enable internal debug messages.
     *
     * @var     bool
     * @access  private
     */
    var $_debug = false;

    /**
     * Default error mode for this object.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_mode = null;

    /**
     * Default error options used for this object when error mode
     * is PEAR_ERROR_TRIGGER.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_options = null;

    /**
     * Default error handler (callback) for this object, if error mode is
     * PEAR_ERROR_CALLBACK.
     *
     * @var     string
     * @access  private
     */
    var $_default_error_handler = '';

    /**
     * Which class to use for error objects.
     *
     * @var     string
     * @access  private
     */
    var $_error_class = 'PEAR_Error';

    /**
     * An array of expected errors.
     *
     * @var     array
     * @access  private
     */
    var $_expected_errors = array();

    /**
     * List of methods that can be called both statically and non-statically.
     * @var array
     */
    protected static $bivalentMethods = array(
        'setErrorHandling' => true,
        'raiseError' => true,
        'throwError' => true,
        'pushErrorHandling' => true,
        'popErrorHandling' => true,
    );

    /**
     * Constructor.  Registers this object in
     * $_PEAR_destructor_object_list for destructor emulation if a
     * destructor object exists.
     *
     * @param string $error_class  (optional) which class to use for
     *        error objects, defaults to PEAR_Error.
     * @access public
     * @return void
     */
    function __construct($error_class = null)
    {
        $classname = strtolower(get_class($this));
        if ($this->_debug) {
            print "PEAR constructor called, class=$classname\n";
        }

        if ($error_class !== null) {
            $this->_error_class = $error_class;
        }

        while ($classname && strcasecmp($classname, "pear")) {
            $destructor = "_$classname";
            if (method_exists($this, $destructor)) {
                global $_PEAR_destructor_object_list;
                $_PEAR_destructor_object_list[] = $this;
                if (!isset($GLOBALS['_PEAR_SHUTDOWN_REGISTERED'])) {
                    register_shutdown_function("_PEAR_call_destructors");
                    $GLOBALS['_PEAR_SHUTDOWN_REGISTERED'] = true;
                }
                break;
            } else {
                $classname = get_parent_class($classname);
            }
        }
    }

    /**
     * Only here for backwards compatibility.
     * E.g. Archive_Tar calls $this->PEAR() in its constructor.
     *
     * @param string $error_class Which class to use for error objects,
     *                            defaults to PEAR_Error.
     */
    public function PEAR($error_class = null)
    {
        self::__construct($error_class);
    }

    /**
     * Destructor (the emulated type of...).  Does nothing right now,
     * but is included for forward compatibility, so subclass
     * destructors should always call it.
     *
     * See the note in the class desciption about output from
     * destructors.
     *
     * @access public
     * @return void
     */
    function _PEAR() {
        if ($this->_debug) {
            printf("PEAR destructor called, class=%s\n", strtolower(get_class($this)));
        }
    }

    public function __call($method, $arguments)
    {
        if (!isset(self::$bivalentMethods[$method])) {
            trigger_error(
                'Call to undefined method PEAR::' . $method . '()', E_USER_ERROR
            );
        }
        return call_user_func_array(
            array(get_class(), '_' . $method),
            array_merge(array($this), $arguments)
        );
    }

    public static function __callStatic($method, $arguments)
    {
        if (!isset(self::$bivalentMethods[$method])) {
            trigger_error(
                'Call to undefined method PEAR::' . $method . '()', E_USER_ERROR
            );
        }
        return call_user_func_array(
            array(get_class(), '_' . $method),
            array_merge(array(null), $arguments)
        );
    }

    /**
     * If you have a class that's mostly/entirely static, and you need static
     * properties, you can use this method to simulate them. Eg. in your method(s)
     * do this: $myVar = &PEAR::getStaticProperty('myclass', 'myVar');
     * You MUST use a reference, or they will not persist!
     *
     * @param  string $class  The calling classname, to prevent clashes
     * @param  string $var    The variable to retrieve.
     * @return mixed   A reference to the variable. If not set it will be
     *                 auto initialised to NULL.
     */
    public static function &getStaticProperty($class, $var)
    {
        static $properties;
        if (!isset($properties[$class])) {
            $properties[$class] = array();
        }

        if (!array_key_exists($var, $properties[$class])) {
            $properties[$class][$var] = null;
        }

        return $properties[$class][$var];
    }

    /**
     * Use this function to register a shutdown method for static
     * classes.
     *
     * @param  mixed $func  The function name (or array of class/method) to call
     * @param  mixed $args  The arguments to pass to the function
     *
     * @return void
     */
    public static function registerShutdownFunc($func, $args = array())
    {
        // if we are called statically, there is a potential
        // that no shutdown func is registered.  Bug #6445
        if (!isset($GLOBALS['_PEAR_SHUTDOWN_REGISTERED'])) {
            register_shutdown_function("_PEAR_call_destructors");
            $GLOBALS['_PEAR_SHUTDOWN_REGISTERED'] = true;
        }
        $GLOBALS['_PEAR_shutdown_funcs'][] = array($func, $args);
    }

    /**
     * Tell whether a value is a PEAR error.
     *
     * @param   mixed $data   the value to test
     * @param   int   $code   if $data is an error object, return true
     *                        only if $code is a string and
     *                        $obj->getMessage() == $code or
     *                        $code is an integer and $obj->getCode() == $code
     *
     * @return  bool    true if parameter is an error
     */
    public static function isError($data, $code = null)
    {
        if (!is_a($data, 'PEAR_Error')) {
            return false;
        }

        if (is_null($code)) {
            return true;
        } elseif (is_string($code)) {
            return $data->getMessage() == $code;
        }

        return $data->getCode() == $code;
    }

    /**
     * Sets how errors generated by this object should be handled.
     * Can be invoked both in objects and statically.  If called
     * statically, setErrorHandling sets the default behaviour for all
     * PEAR objects.  If called in an object, setErrorHandling sets
     * the default behaviour for that object.
     *
     * @param object $object
     *        Object the method was called on (non-static mode)
     *
     * @param int $mode
     *        One of PEAR_ERROR_RETURN, PEAR_ERROR_PRINT,
     *        PEAR_ERROR_TRIGGER, PEAR_ERROR_DIE,
     *        PEAR_ERROR_CALLBACK or PEAR_ERROR_EXCEPTION.
     *
     * @param mixed $options
     *        When $mode is PEAR_ERROR_TRIGGER, this is the error level (one
     *        of E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *
     *        When $mode is PEAR_ERROR_CALLBACK, this parameter is expected
     *        to be the callback function or method.  A callback
     *        function is a string with the name of the function, a
     *        callback method is an array of two elements: the element
     *        at index 0 is the object, and the element at index 1 is
     *        the name of the method to call in the object.
     *
     *        When $mode is PEAR_ERROR_PRINT or PEAR_ERROR_DIE, this is
     *        a printf format string used when printing the error
     *        message.
     *
     * @access public
     * @return void
     * @see PEAR_ERROR_RETURN
     * @see PEAR_ERROR_PRINT
     * @see PEAR_ERROR_TRIGGER
     * @see PEAR_ERROR_DIE
     * @see PEAR_ERROR_CALLBACK
     * @see PEAR_ERROR_EXCEPTION
     *
     * @since PHP 4.0.5
     */
    protected static function _setErrorHandling(
        $object, $mode = null, $options = null
    ) {
        if ($object !== null) {
            $setmode     = &$object->_default_error_mode;
            $setoptions  = &$object->_default_error_options;
        } else {
            $setmode     = &$GLOBALS['_PEAR_default_error_mode'];
            $setoptions  = &$GLOBALS['_PEAR_default_error_options'];
        }

        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $setmode = $mode;
                $setoptions = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $setmode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $setoptions = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
    }

    /**
     * This method is used to tell which errors you expect to get.
     * Expected errors are always returned with error mode
     * PEAR_ERROR_RETURN.  Expected error codes are stored in a stack,
     * and this method pushes a new element onto it.  The list of
     * expected errors are in effect until they are popped off the
     * stack with the popExpect() method.
     *
     * Note that this method can not be called statically
     *
     * @param mixed $code a single error code or an array of error codes to expect
     *
     * @return int     the new depth of the "expected errors" stack
     * @access public
     */
    function expectError($code = '*')
    {
        if (is_array($code)) {
            array_push($this->_expected_errors, $code);
        } else {
            array_push($this->_expected_errors, array($code));
        }
        return count($this->_expected_errors);
    }

    /**
     * This method pops one element off the expected error codes
     * stack.
     *
     * @return array   the list of error codes that were popped
     */
    function popExpect()
    {
        return array_pop($this->_expected_errors);
    }

    /**
     * This method checks unsets an error code if available
     *
     * @param mixed error code
     * @return bool true if the error code was unset, false otherwise
     * @access private
     * @since PHP 4.3.0
     */
    function _checkDelExpect($error_code)
    {
        $deleted = false;
        foreach ($this->_expected_errors as $key => $error_array) {
            if (in_array($error_code, $error_array)) {
                unset($this->_expected_errors[$key][array_search($error_code, $error_array)]);
                $deleted = true;
            }

            // clean up empty arrays
            if (0 == count($this->_expected_errors[$key])) {
                unset($this->_expected_errors[$key]);
            }
        }

        return $deleted;
    }

    /**
     * This method deletes all occurrences of the specified element from
     * the expected error codes stack.
     *
     * @param  mixed $error_code error code that should be deleted
     * @return mixed list of error codes that were deleted or error
     * @access public
     * @since PHP 4.3.0
     */
    function delExpect($error_code)
    {
        $deleted = false;
        if ((is_array($error_code) && (0 != count($error_code)))) {
            // $error_code is a non-empty array here; we walk through it trying
            // to unset all values
            foreach ($error_code as $key => $error) {
                $deleted =  $this->_checkDelExpect($error) ? true : false;
            }

            return $deleted ? true : PEAR::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
        } elseif (!empty($error_code)) {
            // $error_code comes alone, trying to unset it
            if ($this->_checkDelExpect($error_code)) {
                return true;
            }

            return PEAR::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
        }

        // $error_code is empty
        return PEAR::raiseError("The expected error you submitted is empty"); // IMPROVE ME
    }

    /**
     * This method is a wrapper that returns an instance of the
     * configured error class with this object's default error
     * handling applied.  If the $mode and $options parameters are not
     * specified, the object's defaults are used.
     *
     * @param mixed $message a text error message or a PEAR error object
     *
     * @param int $code      a numeric error code (it is up to your class
     *                  to define these if you want to use codes)
     *
     * @param int $mode      One of PEAR_ERROR_RETURN, PEAR_ERROR_PRINT,
     *                  PEAR_ERROR_TRIGGER, PEAR_ERROR_DIE,
     *                  PEAR_ERROR_CALLBACK, PEAR_ERROR_EXCEPTION.
     *
     * @param mixed $options If $mode is PEAR_ERROR_TRIGGER, this parameter
     *                  specifies the PHP-internal error level (one of
     *                  E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *                  If $mode is PEAR_ERROR_CALLBACK, this
     *                  parameter specifies the callback function or
     *                  method.  In other error modes this parameter
     *                  is ignored.
     *
     * @param string $userinfo If you need to pass along for example debug
     *                  information, this parameter is meant for that.
     *
     * @param string $error_class The returned error object will be
     *                  instantiated from this class, if specified.
     *
     * @param bool $skipmsg If true, raiseError will only pass error codes,
     *                  the error message parameter will be dropped.
     *
     * @return object   a PEAR error object
     * @see PEAR::setErrorHandling
     * @since PHP 4.0.5
     */
    protected static function _raiseError($object,
        $message = null,
        $code = null,
        $mode = null,
        $options = null,
        $userinfo = null,
        $error_class = null,
        $skipmsg = false)
    {
        // The error is yet a PEAR error object
        if (is_object($message)) {
            $code        = $message->getCode();
            $userinfo    = $message->getUserInfo();
            $error_class = $message->getType();
            $message->error_message_prefix = '';
            $message     = $message->getMessage();
        }

        if (
            $object !== null &&
            isset($object->_expected_errors) &&
            count($object->_expected_errors) > 0 &&
            count($exp = end($object->_expected_errors))
        ) {
            if ($exp[0] == "*" ||
                (is_int(reset($exp)) && in_array($code, $exp)) ||
                (is_string(reset($exp)) && in_array($message, $exp))
            ) {
                $mode = PEAR_ERROR_RETURN;
            }
        }

        // No mode given, try global ones
        if ($mode === null) {
            // Class error handler
            if ($object !== null && isset($object->_default_error_mode)) {
                $mode    = $object->_default_error_mode;
                $options = $object->_default_error_options;
                // Global error handler
            } elseif (isset($GLOBALS['_PEAR_default_error_mode'])) {
                $mode    = $GLOBALS['_PEAR_default_error_mode'];
                $options = $GLOBALS['_PEAR_default_error_options'];
            }
        }

        if ($error_class !== null) {
            $ec = $error_class;
        } elseif ($object !== null && isset($object->_error_class)) {
            $ec = $object->_error_class;
        } else {
            $ec = 'PEAR_Error';
        }

        if ($skipmsg) {
            $a = new $ec($code, $mode, $options, $userinfo);
        } else {
            $a = new $ec($message, $code, $mode, $options, $userinfo);
        }

        return $a;
    }

    /**
     * Simpler form of raiseError with fewer options.  In most cases
     * message, code and userinfo are enough.
     *
     * @param mixed $message a text error message or a PEAR error object
     *
     * @param int $code      a numeric error code (it is up to your class
     *                  to define these if you want to use codes)
     *
     * @param string $userinfo If you need to pass along for example debug
     *                  information, this parameter is meant for that.
     *
     * @return object   a PEAR error object
     * @see PEAR::raiseError
     */
    protected static function _throwError($object, $message = null, $code = null, $userinfo = null)
    {
        if ($object !== null) {
            $a = $object->raiseError($message, $code, null, null, $userinfo);
            return $a;
        }

        $a = PEAR::raiseError($message, $code, null, null, $userinfo);
        return $a;
    }

    public static function staticPushErrorHandling($mode, $options = null)
    {
        $stack       = &$GLOBALS['_PEAR_error_handler_stack'];
        $def_mode    = &$GLOBALS['_PEAR_default_error_mode'];
        $def_options = &$GLOBALS['_PEAR_default_error_options'];
        $stack[] = array($def_mode, $def_options);
        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $def_mode = $mode;
                $def_options = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $def_mode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $def_options = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
        $stack[] = array($mode, $options);
        return true;
    }

    public static function staticPopErrorHandling()
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        $setmode     = &$GLOBALS['_PEAR_default_error_mode'];
        $setoptions  = &$GLOBALS['_PEAR_default_error_options'];
        array_pop($stack);
        list($mode, $options) = $stack[sizeof($stack) - 1];
        array_pop($stack);
        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $setmode = $mode;
                $setoptions = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $setmode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $setoptions = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
        return true;
    }

    /**
     * Push a new error handler on top of the error handler options stack. With this
     * you can easily override the actual error handler for some code and restore
     * it later with popErrorHandling.
     *
     * @param mixed $mode (same as setErrorHandling)
     * @param mixed $options (same as setErrorHandling)
     *
     * @return bool Always true
     *
     * @see PEAR::setErrorHandling
     */
    protected static function _pushErrorHandling($object, $mode, $options = null)
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        if ($object !== null) {
            $def_mode    = &$object->_default_error_mode;
            $def_options = &$object->_default_error_options;
        } else {
            $def_mode    = &$GLOBALS['_PEAR_default_error_mode'];
            $def_options = &$GLOBALS['_PEAR_default_error_options'];
        }
        $stack[] = array($def_mode, $def_options);

        if ($object !== null) {
            $object->setErrorHandling($mode, $options);
        } else {
            PEAR::setErrorHandling($mode, $options);
        }
        $stack[] = array($mode, $options);
        return true;
    }

    /**
     * Pop the last error handler used
     *
     * @return bool Always true
     *
     * @see PEAR::pushErrorHandling
     */
    protected static function _popErrorHandling($object)
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        array_pop($stack);
        list($mode, $options) = $stack[sizeof($stack) - 1];
        array_pop($stack);
        if ($object !== null) {
            $object->setErrorHandling($mode, $options);
        } else {
            PEAR::setErrorHandling($mode, $options);
        }
        return true;
    }

    /**
     * OS independent PHP extension load. Remember to take care
     * on the correct extension name for case sensitive OSes.
     *
     * @param string $ext The extension name
     * @return bool Success or not on the dl() call
     */
    public static function loadExtension($ext)
    {
        if (extension_loaded($ext)) {
            return true;
        }

        // if either returns true dl() will produce a FATAL error, stop that
        if (
            function_exists('dl') === false ||
            ini_get('enable_dl') != 1
        ) {
            return false;
        }

        if (OS_WINDOWS) {
            $suffix = '.dll';
        } elseif (PHP_OS == 'HP-UX') {
            $suffix = '.sl';
        } elseif (PHP_OS == 'AIX') {
            $suffix = '.a';
        } elseif (PHP_OS == 'OSX') {
            $suffix = '.bundle';
        } else {
            $suffix = '.so';
        }

        return @dl('php_'.$ext.$suffix) || @dl($ext.$suffix);
    }
}

function _PEAR_call_destructors()
{
    global $_PEAR_destructor_object_list;
    if (is_array($_PEAR_destructor_object_list) &&
        sizeof($_PEAR_destructor_object_list))
    {
        reset($_PEAR_destructor_object_list);

        $destructLifoExists = PEAR::getStaticProperty('PEAR', 'destructlifo');

        if ($destructLifoExists) {
            $_PEAR_destructor_object_list = array_reverse($_PEAR_destructor_object_list);
        }

        foreach ($_PEAR_destructor_object_list as $k => $objref) {
            $classname = get_class($objref);
            while ($classname) {
                $destructor = "_$classname";
                if (method_exists($objref, $destructor)) {
                    $objref->$destructor();
                    break;
                } else {
                    $classname = get_parent_class($classname);
                }
            }
        }
        // Empty the object list to ensure that destructors are
        // not called more than once.
        $_PEAR_destructor_object_list = array();
    }

    // Now call the shutdown functions
    if (
        isset($GLOBALS['_PEAR_shutdown_funcs']) &&
        is_array($GLOBALS['_PEAR_shutdown_funcs']) &&
        !empty($GLOBALS['_PEAR_shutdown_funcs'])
    ) {
        foreach ($GLOBALS['_PEAR_shutdown_funcs'] as $value) {
            call_user_func_array($value[0], $value[1]);
        }
    }
}

/**
 * Standard PEAR error class for PHP 4
 *
 * This class is supserseded by {@link PEAR_Exception} in PHP 5
 *
 * @category   pear
 * @package    PEAR
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Gregory Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.10.7
 * @link       http://pear.php.net/manual/en/core.pear.pear-error.php
 * @see        PEAR::raiseError(), PEAR::throwError()
 * @since      Class available since PHP 4.0.2
 */
class PEAR_Error
{
    var $error_message_prefix = '';
    var $mode                 = PEAR_ERROR_RETURN;
    var $level                = E_USER_NOTICE;
    var $code                 = -1;
    var $message              = '';
    var $userinfo             = '';
    var $backtrace            = null;

    /**
     * PEAR_Error constructor
     *
     * @param string $message  message
     *
     * @param int $code     (optional) error code
     *
     * @param int $mode     (optional) error mode, one of: PEAR_ERROR_RETURN,
     * PEAR_ERROR_PRINT, PEAR_ERROR_DIE, PEAR_ERROR_TRIGGER,
     * PEAR_ERROR_CALLBACK or PEAR_ERROR_EXCEPTION
     *
     * @param mixed $options   (optional) error level, _OR_ in the case of
     * PEAR_ERROR_CALLBACK, the callback function or object/method
     * tuple.
     *
     * @param string $userinfo (optional) additional user/debug info
     *
     * @access public
     *
     */
    function __construct($message = 'unknown error', $code = null,
        $mode = null, $options = null, $userinfo = null)
    {
        if ($mode === null) {
            $mode = PEAR_ERROR_RETURN;
        }
        $this->message   = $message;
        $this->code      = $code;
        $this->mode      = $mode;
        $this->userinfo  = $userinfo;

        $skiptrace = PEAR::getStaticProperty('PEAR_Error', 'skiptrace');

        if (!$skiptrace) {
            $this->backtrace = debug_backtrace();
            if (isset($this->backtrace[0]) && isset($this->backtrace[0]['object'])) {
                unset($this->backtrace[0]['object']);
            }
        }

        if ($mode & PEAR_ERROR_CALLBACK) {
            $this->level = E_USER_NOTICE;
            $this->callback = $options;
        } else {
            if ($options === null) {
                $options = E_USER_NOTICE;
            }

            $this->level = $options;
            $this->callback = null;
        }

        if ($this->mode & PEAR_ERROR_PRINT) {
            if (is_null($options) || is_int($options)) {
                $format = "%s";
            } else {
                $format = $options;
            }

            printf($format, $this->getMessage());
        }

        if ($this->mode & PEAR_ERROR_TRIGGER) {
            trigger_error($this->getMessage(), $this->level);
        }

        if ($this->mode & PEAR_ERROR_DIE) {
            $msg = $this->getMessage();
            if (is_null($options) || is_int($options)) {
                $format = "%s";
                if (substr($msg, -1) != "\n") {
                    $msg .= "\n";
                }
            } else {
                $format = $options;
            }
            printf($format, $msg);
            exit($code);
        }

        if ($this->mode & PEAR_ERROR_CALLBACK && is_callable($this->callback)) {
            call_user_func($this->callback, $this);
        }

        if ($this->mode & PEAR_ERROR_EXCEPTION) {
            trigger_error("PEAR_ERROR_EXCEPTION is obsolete, use class PEAR_Exception for exceptions", E_USER_WARNING);
            eval('$e = new Exception($this->message, $this->code);throw($e);');
        }
    }

    /**
     * Only here for backwards compatibility.
     *
     * Class "Cache_Error" still uses it, among others.
     *
     * @param string $message  Message
     * @param int    $code     Error code
     * @param int    $mode     Error mode
     * @param mixed  $options  See __construct()
     * @param string $userinfo Additional user/debug info
     */
    public function PEAR_Error(
        $message = 'unknown error', $code = null, $mode = null,
        $options = null, $userinfo = null
    ) {
        self::__construct($message, $code, $mode, $options, $userinfo);
    }

    /**
     * Get the error mode from an error object.
     *
     * @return int error mode
     * @access public
     */
    function getMode()
    {
        return $this->mode;
    }

    /**
     * Get the callback function/method from an error object.
     *
     * @return mixed callback function or object/method array
     * @access public
     */
    function getCallback()
    {
        return $this->callback;
    }

    /**
     * Get the error message from an error object.
     *
     * @return  string  full error message
     * @access public
     */
    function getMessage()
    {
        return ($this->error_message_prefix . $this->message);
    }

    /**
     * Get error code from an error object
     *
     * @return int error code
     * @access public
     */
    function getCode()
    {
        return $this->code;
    }

    /**
     * Get the name of this error/exception.
     *
     * @return string error/exception name (type)
     * @access public
     */
    function getType()
    {
        return get_class($this);
    }

    /**
     * Get additional user-supplied information.
     *
     * @return string user-supplied information
     * @access public
     */
    function getUserInfo()
    {
        return $this->userinfo;
    }

    /**
     * Get additional debug information supplied by the application.
     *
     * @return string debug information
     * @access public
     */
    function getDebugInfo()
    {
        return $this->getUserInfo();
    }

    /**
     * Get the call backtrace from where the error was generated.
     * Supported with PHP 4.3.0 or newer.
     *
     * @param int $frame (optional) what frame to fetch
     * @return array Backtrace, or NULL if not available.
     * @access public
     */
    function getBacktrace($frame = null)
    {
        if (defined('PEAR_IGNORE_BACKTRACE')) {
            return null;
        }
        if ($frame === null) {
            return $this->backtrace;
        }
        return $this->backtrace[$frame];
    }

    function addUserInfo($info)
    {
        if (empty($this->userinfo)) {
            $this->userinfo = $info;
        } else {
            $this->userinfo .= " ** $info";
        }
    }

    function __toString()
    {
        return $this->getMessage();
    }

    /**
     * Make a string representation of this object.
     *
     * @return string a string with an object summary
     * @access public
     */
    function toString()
    {
        $modes = array();
        $levels = array(E_USER_NOTICE  => 'notice',
            E_USER_WARNING => 'warning',
            E_USER_ERROR   => 'error');
        if ($this->mode & PEAR_ERROR_CALLBACK) {
            if (is_array($this->callback)) {
                $callback = (is_object($this->callback[0]) ?
                        strtolower(get_class($this->callback[0])) :
                        $this->callback[0]) . '::' .
                    $this->callback[1];
            } else {
                $callback = $this->callback;
            }
            return sprintf('[%s: message="%s" code=%d mode=callback '.
                'callback=%s prefix="%s" info="%s"]',
                strtolower(get_class($this)), $this->message, $this->code,
                $callback, $this->error_message_prefix,
                $this->userinfo);
        }
        if ($this->mode & PEAR_ERROR_PRINT) {
            $modes[] = 'print';
        }
        if ($this->mode & PEAR_ERROR_TRIGGER) {
            $modes[] = 'trigger';
        }
        if ($this->mode & PEAR_ERROR_DIE) {
            $modes[] = 'die';
        }
        if ($this->mode & PEAR_ERROR_RETURN) {
            $modes[] = 'return';
        }
        return sprintf('[%s: message="%s" code=%d mode=%s level=%s '.
            'prefix="%s" info="%s"]',
            strtolower(get_class($this)), $this->message, $this->code,
            implode("|", $modes), $levels[$this->level],
            $this->error_message_prefix,
            $this->userinfo);
    }
}

/*
 * Local Variables:
 * mode: php
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */
?>
<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * File::CSV
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 1997-2008,
 * Vincent Blavet <vincent@phpconcept.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  File_Formats
 * @package   Archive_Tar
 * @author    Vincent Blavet <vincent@phpconcept.net>
 * @copyright 1997-2010 The Authors
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Archive_Tar
 */

// If the PEAR class cannot be loaded via the autoloader,
// then try to require_once it from the PHP include path.
if (!class_exists('PEAR')) {
    require_once 'PEAR.php';
}

define('ARCHIVE_TAR_ATT_SEPARATOR', 90001);
define('ARCHIVE_TAR_END_BLOCK', pack("a512", ''));

if (!function_exists('gzopen') && function_exists('gzopen64')) {
    function gzopen($filename, $mode, $use_include_path = 0)
    {
        return gzopen64($filename, $mode, $use_include_path);
    }
}

if (!function_exists('gztell') && function_exists('gztell64')) {
    function gztell($zp)
    {
        return gztell64($zp);
    }
}

if (!function_exists('gzseek') && function_exists('gzseek64')) {
    function gzseek($zp, $offset, $whence = SEEK_SET)
    {
        return gzseek64($zp, $offset, $whence);
    }
}

/**
 * Creates a (compressed) Tar archive
 *
 * @package Archive_Tar
 * @author  Vincent Blavet <vincent@phpconcept.net>
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version 1.4.3
 */
class Archive_Tar extends PEAR
{
    /**
     * @var string Name of the Tar
     */
    public $_tarname = '';

    /**
     * @var boolean if true, the Tar file will be gzipped
     */
    public $_compress = false;

    /**
     * @var string Type of compression : 'none', 'gz', 'bz2' or 'lzma2'
     */
    public $_compress_type = 'none';

    /**
     * @var string Explode separator
     */
    public $_separator = ' ';

    /**
     * @var file descriptor
     */
    public $_file = 0;

    /**
     * @var string Local Tar name of a remote Tar (http:// or ftp://)
     */
    public $_temp_tarname = '';

    /**
     * @var string regular expression for ignoring files or directories
     */
    public $_ignore_regexp = '';

    /**
     * @var object PEAR_Error object
     */
    public $error_object = null;

    /**
     * Format for data extraction
     *
     * @var string
     */
    public $_fmt ='';
    /**
     * Archive_Tar Class constructor. This flavour of the constructor only
     * declare a new Archive_Tar object, identifying it by the name of the
     * tar file.
     * If the compress argument is set the tar will be read or created as a
     * gzip or bz2 compressed TAR file.
     *
     * @param string $p_tarname The name of the tar archive to create
     * @param string $p_compress can be null, 'gz', 'bz2' or 'lzma2'. This
     *               parameter indicates if gzip, bz2 or lzma2 compression
     *               is required.  For compatibility reason the
     *               boolean value 'true' means 'gz'.
     *
     * @return bool
     */
    public function __construct($p_tarname, $p_compress = null)
    {
        parent::__construct();

        $this->_compress = false;
        $this->_compress_type = 'none';
        if (($p_compress === null) || ($p_compress == '')) {
            if (@file_exists($p_tarname)) {
                if ($fp = @fopen($p_tarname, "rb")) {
                    // look for gzip magic cookie
                    $data = fread($fp, 2);
                    fclose($fp);
                    if ($data == "\37\213") {
                        $this->_compress = true;
                        $this->_compress_type = 'gz';
                        // No sure it's enought for a magic code ....
                    } elseif ($data == "BZ") {
                        $this->_compress = true;
                        $this->_compress_type = 'bz2';
                    } elseif (file_get_contents($p_tarname, false, null, 1, 4) == '7zXZ') {
                        $this->_compress = true;
                        $this->_compress_type = 'lzma2';
                    }
                }
            } else {
                // probably a remote file or some file accessible
                // through a stream interface
                if (substr($p_tarname, -2) == 'gz') {
                    $this->_compress = true;
                    $this->_compress_type = 'gz';
                } elseif ((substr($p_tarname, -3) == 'bz2') ||
                    (substr($p_tarname, -2) == 'bz')
                ) {
                    $this->_compress = true;
                    $this->_compress_type = 'bz2';
                } else {
                    if (substr($p_tarname, -2) == 'xz') {
                        $this->_compress = true;
                        $this->_compress_type = 'lzma2';
                    }
                }
            }
        } else {
            if (($p_compress === true) || ($p_compress == 'gz')) {
                $this->_compress = true;
                $this->_compress_type = 'gz';
            } else {
                if ($p_compress == 'bz2') {
                    $this->_compress = true;
                    $this->_compress_type = 'bz2';
                } else {
                    if ($p_compress == 'lzma2') {
                        $this->_compress = true;
                        $this->_compress_type = 'lzma2';
                    } else {
                        $this->_error(
                            "Unsupported compression type '$p_compress'\n" .
                            "Supported types are 'gz', 'bz2' and 'lzma2'.\n"
                        );
                        return false;
                    }
                }
            }
        }
        $this->_tarname = $p_tarname;
        if ($this->_compress) { // assert zlib or bz2 or xz extension support
            if ($this->_compress_type == 'gz') {
                $extname = 'zlib';
            } else {
                if ($this->_compress_type == 'bz2') {
                    $extname = 'bz2';
                } else {
                    if ($this->_compress_type == 'lzma2') {
                        $extname = 'xz';
                    }
                }
            }

            if (!extension_loaded($extname)) {
                PEAR::loadExtension($extname);
            }
            if (!extension_loaded($extname)) {
                $this->_error(
                    "The extension '$extname' couldn't be found.\n" .
                    "Please make sure your version of PHP was built " .
                    "with '$extname' support.\n"
                );
                return false;
            }
        }


        if (version_compare(PHP_VERSION, "5.5.0-dev") < 0) {
            $this->_fmt = "a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/" .
                "a8checksum/a1typeflag/a100link/a6magic/a2version/" .
                "a32uname/a32gname/a8devmajor/a8devminor/a131prefix";
        } else {
            $this->_fmt = "Z100filename/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/" .
                "Z8checksum/Z1typeflag/Z100link/Z6magic/Z2version/" .
                "Z32uname/Z32gname/Z8devmajor/Z8devminor/Z131prefix";
        }


    }

    public function __destruct()
    {
        $this->_close();
        // ----- Look for a local copy to delete
        if ($this->_temp_tarname != '') {
            @unlink($this->_temp_tarname);
        }
    }

    /**
     * This method creates the archive file and add the files / directories
     * that are listed in $p_filelist.
     * If a file with the same name exist and is writable, it is replaced
     * by the new tar.
     * The method return false and a PEAR error text.
     * The $p_filelist parameter can be an array of string, each string
     * representing a filename or a directory name with their path if
     * needed. It can also be a single string with names separated by a
     * single blank.
     * For each directory added in the archive, the files and
     * sub-directories are also added.
     * See also createModify() method for more details.
     *
     * @param array $p_filelist An array of filenames and directory names, or a
     *              single string with names separated by a single
     *              blank space.
     *
     * @return true on success, false on error.
     * @see    createModify()
     */
    public function create($p_filelist)
    {
        return $this->createModify($p_filelist, '', '');
    }

    /**
     * This method add the files / directories that are listed in $p_filelist in
     * the archive. If the archive does not exist it is created.
     * The method return false and a PEAR error text.
     * The files and directories listed are only added at the end of the archive,
     * even if a file with the same name is already archived.
     * See also createModify() method for more details.
     *
     * @param array $p_filelist An array of filenames and directory names, or a
     *              single string with names separated by a single
     *              blank space.
     *
     * @return true on success, false on error.
     * @see    createModify()
     * @access public
     */
    public function add($p_filelist)
    {
        return $this->addModify($p_filelist, '', '');
    }

    /**
     * @param string $p_path
     * @param bool $p_preserve
     * @return bool
     */
    public function extract($p_path = '', $p_preserve = false)
    {
        return $this->extractModify($p_path, '', $p_preserve);
    }

    /**
     * @return array|int
     */
    public function listContent()
    {
        $v_list_detail = array();

        if ($this->_openRead()) {
            if (!$this->_extractList('', $v_list_detail, "list", '', '')) {
                unset($v_list_detail);
                $v_list_detail = 0;
            }
            $this->_close();
        }

        return $v_list_detail;
    }

    /**
     * This method creates the archive file and add the files / directories
     * that are listed in $p_filelist.
     * If the file already exists and is writable, it is replaced by the
     * new tar. It is a create and not an add. If the file exists and is
     * read-only or is a directory it is not replaced. The method return
     * false and a PEAR error text.
     * The $p_filelist parameter can be an array of string, each string
     * representing a filename or a directory name with their path if
     * needed. It can also be a single string with names separated by a
     * single blank.
     * The path indicated in $p_remove_dir will be removed from the
     * memorized path of each file / directory listed when this path
     * exists. By default nothing is removed (empty path '')
     * The path indicated in $p_add_dir will be added at the beginning of
     * the memorized path of each file / directory listed. However it can
     * be set to empty ''. The adding of a path is done after the removing
     * of path.
     * The path add/remove ability enables the user to prepare an archive
     * for extraction in a different path than the origin files are.
     * See also addModify() method for file adding properties.
     *
     * @param array $p_filelist An array of filenames and directory names,
     *                             or a single string with names separated by
     *                             a single blank space.
     * @param string $p_add_dir A string which contains a path to be added
     *                             to the memorized path of each element in
     *                             the list.
     * @param string $p_remove_dir A string which contains a path to be
     *                             removed from the memorized path of each
     *                             element in the list, when relevant.
     *
     * @return boolean true on success, false on error.
     * @see addModify()
     */
    public function createModify($p_filelist, $p_add_dir, $p_remove_dir = '')
    {
        $v_result = true;

        if (!$this->_openWrite()) {
            return false;
        }

        if ($p_filelist != '') {
            if (is_array($p_filelist)) {
                $v_list = $p_filelist;
            } elseif (is_string($p_filelist)) {
                $v_list = explode($this->_separator, $p_filelist);
            } else {
                $this->_cleanFile();
                $this->_error('Invalid file list');
                return false;
            }

            $v_result = $this->_addList($v_list, $p_add_dir, $p_remove_dir);
        }

        if ($v_result) {
            $this->_writeFooter();
            $this->_close();
        } else {
            $this->_cleanFile();
        }

        return $v_result;
    }

    /**
     * This method add the files / directories listed in $p_filelist at the
     * end of the existing archive. If the archive does not yet exists it
     * is created.
     * The $p_filelist parameter can be an array of string, each string
     * representing a filename or a directory name with their path if
     * needed. It can also be a single string with names separated by a
     * single blank.
     * The path indicated in $p_remove_dir will be removed from the
     * memorized path of each file / directory listed when this path
     * exists. By default nothing is removed (empty path '')
     * The path indicated in $p_add_dir will be added at the beginning of
     * the memorized path of each file / directory listed. However it can
     * be set to empty ''. The adding of a path is done after the removing
     * of path.
     * The path add/remove ability enables the user to prepare an archive
     * for extraction in a different path than the origin files are.
     * If a file/dir is already in the archive it will only be added at the
     * end of the archive. There is no update of the existing archived
     * file/dir. However while extracting the archive, the last file will
     * replace the first one. This results in a none optimization of the
     * archive size.
     * If a file/dir does not exist the file/dir is ignored. However an
     * error text is send to PEAR error.
     * If a file/dir is not readable the file/dir is ignored. However an
     * error text is send to PEAR error.
     *
     * @param array $p_filelist An array of filenames and directory
     *                             names, or a single string with names
     *                             separated by a single blank space.
     * @param string $p_add_dir A string which contains a path to be
     *                             added to the memorized path of each
     *                             element in the list.
     * @param string $p_remove_dir A string which contains a path to be
     *                             removed from the memorized path of
     *                             each element in the list, when
     *                             relevant.
     *
     * @return true on success, false on error.
     */
    public function addModify($p_filelist, $p_add_dir, $p_remove_dir = '')
    {
        $v_result = true;

        if (!$this->_isArchive()) {
            $v_result = $this->createModify(
                $p_filelist,
                $p_add_dir,
                $p_remove_dir
            );
        } else {
            if (is_array($p_filelist)) {
                $v_list = $p_filelist;
            } elseif (is_string($p_filelist)) {
                $v_list = explode($this->_separator, $p_filelist);
            } else {
                $this->_error('Invalid file list');
                return false;
            }

            $v_result = $this->_append($v_list, $p_add_dir, $p_remove_dir);
        }

        return $v_result;
    }

    /**
     * This method add a single string as a file at the
     * end of the existing archive. If the archive does not yet exists it
     * is created.
     *
     * @param string $p_filename A string which contains the full
     *                           filename path that will be associated
     *                           with the string.
     * @param string $p_string The content of the file added in
     *                           the archive.
     * @param bool|int $p_datetime A custom date/time (unix timestamp)
     *                           for the file (optional).
     * @param array $p_params An array of optional params:
     *                               stamp => the datetime (replaces
     *                                   datetime above if it exists)
     *                               mode => the permissions on the
     *                                   file (600 by default)
     *                               type => is this a link?  See the
     *                                   tar specification for details.
     *                                   (default = regular file)
     *                               uid => the user ID of the file
     *                                   (default = 0 = root)
     *                               gid => the group ID of the file
     *                                   (default = 0 = root)
     *
     * @return true on success, false on error.
     */
    public function addString($p_filename, $p_string, $p_datetime = false, $p_params = array())
    {
        $p_stamp = @$p_params["stamp"] ? $p_params["stamp"] : ($p_datetime ? $p_datetime : time());
        $p_mode = @$p_params["mode"] ? $p_params["mode"] : 0600;
        $p_type = @$p_params["type"] ? $p_params["type"] : "";
        $p_uid = @$p_params["uid"] ? $p_params["uid"] : "";
        $p_gid = @$p_params["gid"] ? $p_params["gid"] : "";
        $v_result = true;

        if (!$this->_isArchive()) {
            if (!$this->_openWrite()) {
                return false;
            }
            $this->_close();
        }

        if (!$this->_openAppend()) {
            return false;
        }

        // Need to check the get back to the temporary file ? ....
        $v_result = $this->_addString($p_filename, $p_string, $p_datetime, $p_params);

        $this->_writeFooter();

        $this->_close();

        return $v_result;
    }

    /**
     * This method extract all the content of the archive in the directory
     * indicated by $p_path. When relevant the memorized path of the
     * files/dir can be modified by removing the $p_remove_path path at the
     * beginning of the file/dir path.
     * While extracting a file, if the directory path does not exists it is
     * created.
     * While extracting a file, if the file already exists it is replaced
     * without looking for last modification date.
     * While extracting a file, if the file already exists and is write
     * protected, the extraction is aborted.
     * While extracting a file, if a directory with the same name already
     * exists, the extraction is aborted.
     * While extracting a directory, if a file with the same name already
     * exists, the extraction is aborted.
     * While extracting a file/directory if the destination directory exist
     * and is write protected, or does not exist but can not be created,
     * the extraction is aborted.
     * If after extraction an extracted file does not show the correct
     * stored file size, the extraction is aborted.
     * When the extraction is aborted, a PEAR error text is set and false
     * is returned. However the result can be a partial extraction that may
     * need to be manually cleaned.
     *
     * @param string $p_path The path of the directory where the
     *                               files/dir need to by extracted.
     * @param string $p_remove_path Part of the memorized path that can be
     *                               removed if present at the beginning of
     *                               the file/dir path.
     * @param boolean $p_preserve Preserve user/group ownership of files
     *
     * @return boolean true on success, false on error.
     * @see    extractList()
     */
    public function extractModify($p_path, $p_remove_path, $p_preserve = false)
    {
        $v_result = true;
        $v_list_detail = array();

        if ($v_result = $this->_openRead()) {
            $v_result = $this->_extractList(
                $p_path,
                $v_list_detail,
                "complete",
                0,
                $p_remove_path,
                $p_preserve
            );
            $this->_close();
        }

        return $v_result;
    }

    /**
     * This method extract from the archive one file identified by $p_filename.
     * The return value is a string with the file content, or NULL on error.
     *
     * @param string $p_filename The path of the file to extract in a string.
     *
     * @return a string with the file content or NULL.
     */
    public function extractInString($p_filename)
    {
        if ($this->_openRead()) {
            $v_result = $this->_extractInString($p_filename);
            $this->_close();
        } else {
            $v_result = null;
        }

        return $v_result;
    }

    /**
     * This method extract from the archive only the files indicated in the
     * $p_filelist. These files are extracted in the current directory or
     * in the directory indicated by the optional $p_path parameter.
     * If indicated the $p_remove_path can be used in the same way as it is
     * used in extractModify() method.
     *
     * @param array $p_filelist An array of filenames and directory names,
     *                               or a single string with names separated
     *                               by a single blank space.
     * @param string $p_path The path of the directory where the
     *                               files/dir need to by extracted.
     * @param string $p_remove_path Part of the memorized path that can be
     *                               removed if present at the beginning of
     *                               the file/dir path.
     * @param boolean $p_preserve Preserve user/group ownership of files
     *
     * @return true on success, false on error.
     * @see    extractModify()
     */
    public function extractList($p_filelist, $p_path = '', $p_remove_path = '', $p_preserve = false)
    {
        $v_result = true;
        $v_list_detail = array();

        if (is_array($p_filelist)) {
            $v_list = $p_filelist;
        } elseif (is_string($p_filelist)) {
            $v_list = explode($this->_separator, $p_filelist);
        } else {
            $this->_error('Invalid string list');
            return false;
        }

        if ($v_result = $this->_openRead()) {
            $v_result = $this->_extractList(
                $p_path,
                $v_list_detail,
                "partial",
                $v_list,
                $p_remove_path,
                $p_preserve
            );
            $this->_close();
        }

        return $v_result;
    }

    /**
     * This method set specific attributes of the archive. It uses a variable
     * list of parameters, in the format attribute code + attribute values :
     * $arch->setAttribute(ARCHIVE_TAR_ATT_SEPARATOR, ',');
     *
     * @return true on success, false on error.
     */
    public function setAttribute()
    {
        $v_result = true;

        // ----- Get the number of variable list of arguments
        if (($v_size = func_num_args()) == 0) {
            return true;
        }

        // ----- Get the arguments
        $v_att_list = func_get_args();

        // ----- Read the attributes
        $i = 0;
        while ($i < $v_size) {

            // ----- Look for next option
            switch ($v_att_list[$i]) {
                // ----- Look for options that request a string value
                case ARCHIVE_TAR_ATT_SEPARATOR :
                    // ----- Check the number of parameters
                    if (($i + 1) >= $v_size) {
                        $this->_error(
                            'Invalid number of parameters for '
                            . 'attribute ARCHIVE_TAR_ATT_SEPARATOR'
                        );
                        return false;
                    }

                    // ----- Get the value
                    $this->_separator = $v_att_list[$i + 1];
                    $i++;
                    break;

                default :
                    $this->_error('Unknown attribute code ' . $v_att_list[$i] . '');
                    return false;
            }

            // ----- Next attribute
            $i++;
        }

        return $v_result;
    }

    /**
     * This method sets the regular expression for ignoring files and directories
     * at import, for example:
     * $arch->setIgnoreRegexp("#CVS#");
     *
     * @param string $regexp regular expression defining which files or directories to ignore
     */
    public function setIgnoreRegexp($regexp)
    {
        $this->_ignore_regexp = $regexp;
    }

    /**
     * This method sets the regular expression for ignoring all files and directories
     * matching the filenames in the array list at import, for example:
     * $arch->setIgnoreList(array('CVS', 'bin/tool'));
     *
     * @param array $list a list of file or directory names to ignore
     *
     * @access public
     */
    public function setIgnoreList($list)
    {
        $regexp = str_replace(array('#', '.', '^', '$'), array('\#', '\.', '\^', '\$'), $list);
        $regexp = '#/' . join('$|/', $list) . '#';
        $this->setIgnoreRegexp($regexp);
    }

    /**
     * @param string $p_message
     */
    public function _error($p_message)
    {
        $this->error_object = $this->raiseError($p_message);
    }

    /**
     * @param string $p_message
     */
    public function _warning($p_message)
    {
        $this->error_object = $this->raiseError($p_message);
    }

    /**
     * @param string $p_filename
     * @return bool
     */
    public function _isArchive($p_filename = null)
    {
        if ($p_filename == null) {
            $p_filename = $this->_tarname;
        }
        clearstatcache();
        return @is_file($p_filename) && !@is_link($p_filename);
    }

    /**
     * @return bool
     */
    public function _openWrite()
    {
        if ($this->_compress_type == 'gz' && function_exists('gzopen')) {
            $this->_file = @gzopen($this->_tarname, "wb9");
        } else {
            if ($this->_compress_type == 'bz2' && function_exists('bzopen')) {
                $this->_file = @bzopen($this->_tarname, "w");
            } else {
                if ($this->_compress_type == 'lzma2' && function_exists('xzopen')) {
                    $this->_file = @xzopen($this->_tarname, 'w');
                } else {
                    if ($this->_compress_type == 'none') {
                        $this->_file = @fopen($this->_tarname, "wb");
                    } else {
                        $this->_error(
                            'Unknown or missing compression type ('
                            . $this->_compress_type . ')'
                        );
                        return false;
                    }
                }
            }
        }

        if ($this->_file == 0) {
            $this->_error(
                'Unable to open in write mode \''
                . $this->_tarname . '\''
            );
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _openRead()
    {
        if (strtolower(substr($this->_tarname, 0, 7)) == 'http://') {

            // ----- Look if a local copy need to be done
            if ($this->_temp_tarname == '') {
                $this->_temp_tarname = uniqid('tar') . '.tmp';
                if (!$v_file_from = @fopen($this->_tarname, 'rb')) {
                    $this->_error(
                        'Unable to open in read mode \''
                        . $this->_tarname . '\''
                    );
                    $this->_temp_tarname = '';
                    return false;
                }
                if (!$v_file_to = @fopen($this->_temp_tarname, 'wb')) {
                    $this->_error(
                        'Unable to open in write mode \''
                        . $this->_temp_tarname . '\''
                    );
                    $this->_temp_tarname = '';
                    return false;
                }
                while ($v_data = @fread($v_file_from, 1024)) {
                    @fwrite($v_file_to, $v_data);
                }
                @fclose($v_file_from);
                @fclose($v_file_to);
            }

            // ----- File to open if the local copy
            $v_filename = $this->_temp_tarname;
        } else {
            // ----- File to open if the normal Tar file

            $v_filename = $this->_tarname;
        }

        if ($this->_compress_type == 'gz' && function_exists('gzopen')) {
            $this->_file = @gzopen($v_filename, "rb");
        } else {
            if ($this->_compress_type == 'bz2' && function_exists('bzopen')) {
                $this->_file = @bzopen($v_filename, "r");
            } else {
                if ($this->_compress_type == 'lzma2' && function_exists('xzopen')) {
                    $this->_file = @xzopen($v_filename, "r");
                } else {
                    if ($this->_compress_type == 'none') {
                        $this->_file = @fopen($v_filename, "rb");
                    } else {
                        $this->_error(
                            'Unknown or missing compression type ('
                            . $this->_compress_type . ')'
                        );
                        return false;
                    }
                }
            }
        }

        if ($this->_file == 0) {
            $this->_error('Unable to open in read mode \'' . $v_filename . '\'');
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _openReadWrite()
    {
        if ($this->_compress_type == 'gz') {
            $this->_file = @gzopen($this->_tarname, "r+b");
        } else {
            if ($this->_compress_type == 'bz2') {
                $this->_error(
                    'Unable to open bz2 in read/write mode \''
                    . $this->_tarname . '\' (limitation of bz2 extension)'
                );
                return false;
            } else {
                if ($this->_compress_type == 'lzma2') {
                    $this->_error(
                        'Unable to open lzma2 in read/write mode \''
                        . $this->_tarname . '\' (limitation of lzma2 extension)'
                    );
                    return false;
                } else {
                    if ($this->_compress_type == 'none') {
                        $this->_file = @fopen($this->_tarname, "r+b");
                    } else {
                        $this->_error(
                            'Unknown or missing compression type ('
                            . $this->_compress_type . ')'
                        );
                        return false;
                    }
                }
            }
        }

        if ($this->_file == 0) {
            $this->_error(
                'Unable to open in read/write mode \''
                . $this->_tarname . '\''
            );
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _close()
    {
        //if (isset($this->_file)) {
        if (is_resource($this->_file)) {
            if ($this->_compress_type == 'gz') {
                @gzclose($this->_file);
            } else {
                if ($this->_compress_type == 'bz2') {
                    @bzclose($this->_file);
                } else {
                    if ($this->_compress_type == 'lzma2') {
                        @xzclose($this->_file);
                    } else {
                        if ($this->_compress_type == 'none') {
                            @fclose($this->_file);
                        } else {
                            $this->_error(
                                'Unknown or missing compression type ('
                                . $this->_compress_type . ')'
                            );
                        }
                    }
                }
            }

            $this->_file = 0;
        }

        // ----- Look if a local copy need to be erase
        // Note that it might be interesting to keep the url for a time : ToDo
        if ($this->_temp_tarname != '') {
            @unlink($this->_temp_tarname);
            $this->_temp_tarname = '';
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _cleanFile()
    {
        $this->_close();

        // ----- Look for a local copy
        if ($this->_temp_tarname != '') {
            // ----- Remove the local copy but not the remote tarname
            @unlink($this->_temp_tarname);
            $this->_temp_tarname = '';
        } else {
            // ----- Remove the local tarname file
            @unlink($this->_tarname);
        }
        $this->_tarname = '';

        return true;
    }

    /**
     * @param mixed $p_binary_data
     * @param integer $p_len
     * @return bool
     */
    public function _writeBlock($p_binary_data, $p_len = null)
    {
        if (is_resource($this->_file)) {
            if ($p_len === null) {
                if ($this->_compress_type == 'gz') {
                    @gzputs($this->_file, $p_binary_data);
                } else {
                    if ($this->_compress_type == 'bz2') {
                        @bzwrite($this->_file, $p_binary_data);
                    } else {
                        if ($this->_compress_type == 'lzma2') {
                            @xzwrite($this->_file, $p_binary_data);
                        } else {
                            if ($this->_compress_type == 'none') {
                                @fputs($this->_file, $p_binary_data);
                            } else {
                                $this->_error(
                                    'Unknown or missing compression type ('
                                    . $this->_compress_type . ')'
                                );
                            }
                        }
                    }
                }
            } else {
                if ($this->_compress_type == 'gz') {
                    @gzputs($this->_file, $p_binary_data, $p_len);
                } else {
                    if ($this->_compress_type == 'bz2') {
                        @bzwrite($this->_file, $p_binary_data, $p_len);
                    } else {
                        if ($this->_compress_type == 'lzma2') {
                            @xzwrite($this->_file, $p_binary_data, $p_len);
                        } else {
                            if ($this->_compress_type == 'none') {
                                @fputs($this->_file, $p_binary_data, $p_len);
                            } else {
                                $this->_error(
                                    'Unknown or missing compression type ('
                                    . $this->_compress_type . ')'
                                );
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @return null|string
     */
    public function _readBlock()
    {
        $v_block = null;
        if (is_resource($this->_file)) {
            if ($this->_compress_type == 'gz') {
                $v_block = @gzread($this->_file, 512);
            } else {
                if ($this->_compress_type == 'bz2') {
                    $v_block = @bzread($this->_file, 512);
                } else {
                    if ($this->_compress_type == 'lzma2') {
                        $v_block = @xzread($this->_file, 512);
                    } else {
                        if ($this->_compress_type == 'none') {
                            $v_block = @fread($this->_file, 512);
                        } else {
                            $this->_error(
                                'Unknown or missing compression type ('
                                . $this->_compress_type . ')'
                            );
                        }
                    }
                }
            }
        }
        return $v_block;
    }

    /**
     * @param null $p_len
     * @return bool
     */
    public function _jumpBlock($p_len = null)
    {
        if (is_resource($this->_file)) {
            if ($p_len === null) {
                $p_len = 1;
            }

            if ($this->_compress_type == 'gz') {
                @gzseek($this->_file, gztell($this->_file) + ($p_len * 512));
            } else {
                if ($this->_compress_type == 'bz2') {
                    // ----- Replace missing bztell() and bzseek()
                    for ($i = 0; $i < $p_len; $i++) {
                        $this->_readBlock();
                    }
                } else {
                    if ($this->_compress_type == 'lzma2') {
                        // ----- Replace missing xztell() and xzseek()
                        for ($i = 0; $i < $p_len; $i++) {
                            $this->_readBlock();
                        }
                    } else {
                        if ($this->_compress_type == 'none') {
                            @fseek($this->_file, $p_len * 512, SEEK_CUR);
                        } else {
                            $this->_error(
                                'Unknown or missing compression type ('
                                . $this->_compress_type . ')'
                            );
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    public function _writeFooter()
    {
        if (is_resource($this->_file)) {
            // ----- Write the last 0 filled block for end of archive
            $v_binary_data = pack('a1024', '');
            $this->_writeBlock($v_binary_data);
        }
        return true;
    }

    /**
     * @param array $p_list
     * @param string $p_add_dir
     * @param string $p_remove_dir
     * @return bool
     */
    public function _addList($p_list, $p_add_dir, $p_remove_dir)
    {
        $v_result = true;
        $v_header = array();

        // ----- Remove potential windows directory separator
        $p_add_dir = $this->_translateWinPath($p_add_dir);
        $p_remove_dir = $this->_translateWinPath($p_remove_dir, false);

        if (!$this->_file) {
            $this->_error('Invalid file descriptor');
            return false;
        }

        if (sizeof($p_list) == 0) {
            return true;
        }

        foreach ($p_list as $v_filename) {
            if (!$v_result) {
                break;
            }

            // ----- Skip the current tar name
            if ($v_filename == $this->_tarname) {
                continue;
            }

            if ($v_filename == '') {
                continue;
            }

            // ----- ignore files and directories matching the ignore regular expression
            if ($this->_ignore_regexp && preg_match($this->_ignore_regexp, '/' . $v_filename)) {
                $this->_warning("File '$v_filename' ignored");
                continue;
            }

            if (!file_exists($v_filename) && !is_link($v_filename)) {
                $this->_warning("File '$v_filename' does not exist");
                continue;
            }

            // ----- Add the file or directory header
            if (!$this->_addFile($v_filename, $v_header, $p_add_dir, $p_remove_dir)) {
                return false;
            }

            if (@is_dir($v_filename) && !@is_link($v_filename)) {
                if (!($p_hdir = opendir($v_filename))) {
                    $this->_warning("Directory '$v_filename' can not be read");
                    continue;
                }
                while (false !== ($p_hitem = readdir($p_hdir))) {
                    if (($p_hitem != '.') && ($p_hitem != '..')) {
                        if ($v_filename != ".") {
                            $p_temp_list[0] = $v_filename . '/' . $p_hitem;
                        } else {
                            $p_temp_list[0] = $p_hitem;
                        }

                        $v_result = $this->_addList(
                            $p_temp_list,
                            $p_add_dir,
                            $p_remove_dir
                        );
                    }
                }

                unset($p_temp_list);
                unset($p_hdir);
                unset($p_hitem);
            }
        }

        return $v_result;
    }

    /**
     * @param string $p_filename
     * @param mixed $p_header
     * @param string $p_add_dir
     * @param string $p_remove_dir
     * @param null $v_stored_filename
     * @return bool
     */
    public function _addFile($p_filename, &$p_header, $p_add_dir, $p_remove_dir, $v_stored_filename = null)
    {
        if (!$this->_file) {
            $this->_error('Invalid file descriptor');
            return false;
        }

        if ($p_filename == '') {
            $this->_error('Invalid file name');
            return false;
        }

        if (is_null($v_stored_filename)) {
            // ----- Calculate the stored filename
            $p_filename = $this->_translateWinPath($p_filename, false);
            $v_stored_filename = $p_filename;

            if (strcmp($p_filename, $p_remove_dir) == 0) {
                return true;
            }

            if ($p_remove_dir != '') {
                if (substr($p_remove_dir, -1) != '/') {
                    $p_remove_dir .= '/';
                }

                if (substr($p_filename, 0, strlen($p_remove_dir)) == $p_remove_dir) {
                    $v_stored_filename = substr($p_filename, strlen($p_remove_dir));
                }
            }

            $v_stored_filename = $this->_translateWinPath($v_stored_filename);
            if ($p_add_dir != '') {
                if (substr($p_add_dir, -1) == '/') {
                    $v_stored_filename = $p_add_dir . $v_stored_filename;
                } else {
                    $v_stored_filename = $p_add_dir . '/' . $v_stored_filename;
                }
            }

            $v_stored_filename = $this->_pathReduction($v_stored_filename);
        }

        if ($this->_isArchive($p_filename)) {
            if (($v_file = @fopen($p_filename, "rb")) == 0) {
                $this->_warning(
                    "Unable to open file '" . $p_filename
                    . "' in binary read mode"
                );
                return true;
            }

            if (!$this->_writeHeader($p_filename, $v_stored_filename)) {
                return false;
            }

            while (($v_buffer = fread($v_file, 512)) != '') {
                $v_binary_data = pack("a512", "$v_buffer");
                $this->_writeBlock($v_binary_data);
            }

            fclose($v_file);
        } else {
            // ----- Only header for dir
            if (!$this->_writeHeader($p_filename, $v_stored_filename)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $p_filename
     * @param string $p_string
     * @param bool $p_datetime
     * @param array $p_params
     * @return bool
     */
    public function _addString($p_filename, $p_string, $p_datetime = false, $p_params = array())
    {
        $p_stamp = @$p_params["stamp"] ? $p_params["stamp"] : ($p_datetime ? $p_datetime : time());
        $p_mode = @$p_params["mode"] ? $p_params["mode"] : 0600;
        $p_type = @$p_params["type"] ? $p_params["type"] : "";
        $p_uid = @$p_params["uid"] ? $p_params["uid"] : 0;
        $p_gid = @$p_params["gid"] ? $p_params["gid"] : 0;
        if (!$this->_file) {
            $this->_error('Invalid file descriptor');
            return false;
        }

        if ($p_filename == '') {
            $this->_error('Invalid file name');
            return false;
        }

        // ----- Calculate the stored filename
        $p_filename = $this->_translateWinPath($p_filename, false);

        // ----- If datetime is not specified, set current time
        if ($p_datetime === false) {
            $p_datetime = time();
        }

        if (!$this->_writeHeaderBlock(
            $p_filename,
            strlen($p_string),
            $p_stamp,
            $p_mode,
            $p_type,
            $p_uid,
            $p_gid
        )
        ) {
            return false;
        }

        $i = 0;
        while (($v_buffer = substr($p_string, (($i++) * 512), 512)) != '') {
            $v_binary_data = pack("a512", $v_buffer);
            $this->_writeBlock($v_binary_data);
        }

        return true;
    }

    /**
     * @param string $p_filename
     * @param string $p_stored_filename
     * @return bool
     */
    public function _writeHeader($p_filename, $p_stored_filename)
    {
        if ($p_stored_filename == '') {
            $p_stored_filename = $p_filename;
        }
        $v_reduce_filename = $this->_pathReduction($p_stored_filename);

        if (strlen($v_reduce_filename) > 99) {
            if (!$this->_writeLongHeader($v_reduce_filename)) {
                return false;
            }
        }

        $v_info = lstat($p_filename);
        $v_uid = sprintf("%07s", DecOct($v_info[4]));
        $v_gid = sprintf("%07s", DecOct($v_info[5]));
        $v_perms = sprintf("%07s", DecOct($v_info['mode'] & 000777));

        $v_mtime = sprintf("%011s", DecOct($v_info['mtime']));

        $v_linkname = '';

        if (@is_link($p_filename)) {
            $v_typeflag = '2';
            $v_linkname = readlink($p_filename);
            $v_size = sprintf("%011s", DecOct(0));
        } elseif (@is_dir($p_filename)) {
            $v_typeflag = "5";
            $v_size = sprintf("%011s", DecOct(0));
        } else {
            $v_typeflag = '0';
            clearstatcache();
            $v_size = sprintf("%011s", DecOct($v_info['size']));
        }

        $v_magic = 'ustar ';

        $v_version = ' ';

        if (function_exists('posix_getpwuid')) {
            $userinfo = posix_getpwuid($v_info[4]);
            $groupinfo = posix_getgrgid($v_info[5]);

            $v_uname = $userinfo['name'];
            $v_gname = $groupinfo['name'];
        } else {
            $v_uname = '';
            $v_gname = '';
        }

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack(
            "a100a8a8a8a12a12",
            $v_reduce_filename,
            $v_perms,
            $v_uid,
            $v_gid,
            $v_size,
            $v_mtime
        );
        $v_binary_data_last = pack(
            "a1a100a6a2a32a32a8a8a155a12",
            $v_typeflag,
            $v_linkname,
            $v_magic,
            $v_version,
            $v_uname,
            $v_gname,
            $v_devmajor,
            $v_devminor,
            $v_prefix,
            ''
        );

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i = 0; $i < 148; $i++) {
            $v_checksum += ord(substr($v_binary_data_first, $i, 1));
        }
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i = 148; $i < 156; $i++) {
            $v_checksum += ord(' ');
        }
        // ..... Last part of the header
        for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
            $v_checksum += ord(substr($v_binary_data_last, $j, 1));
        }

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%06s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        return true;
    }

    /**
     * @param string $p_filename
     * @param int $p_size
     * @param int $p_mtime
     * @param int $p_perms
     * @param string $p_type
     * @param int $p_uid
     * @param int $p_gid
     * @return bool
     */
    public function _writeHeaderBlock(
        $p_filename,
        $p_size,
        $p_mtime = 0,
        $p_perms = 0,
        $p_type = '',
        $p_uid = 0,
        $p_gid = 0
    ) {
        $p_filename = $this->_pathReduction($p_filename);

        if (strlen($p_filename) > 99) {
            if (!$this->_writeLongHeader($p_filename)) {
                return false;
            }
        }

        if ($p_type == "5") {
            $v_size = sprintf("%011s", DecOct(0));
        } else {
            $v_size = sprintf("%011s", DecOct($p_size));
        }

        $v_uid = sprintf("%07s", DecOct($p_uid));
        $v_gid = sprintf("%07s", DecOct($p_gid));
        $v_perms = sprintf("%07s", DecOct($p_perms & 000777));

        $v_mtime = sprintf("%11s", DecOct($p_mtime));

        $v_linkname = '';

        $v_magic = 'ustar ';

        $v_version = ' ';

        if (function_exists('posix_getpwuid')) {
            $userinfo = posix_getpwuid($p_uid);
            $groupinfo = posix_getgrgid($p_gid);

            $v_uname = $userinfo['name'];
            $v_gname = $groupinfo['name'];
        } else {
            $v_uname = '';
            $v_gname = '';
        }

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack(
            "a100a8a8a8a12A12",
            $p_filename,
            $v_perms,
            $v_uid,
            $v_gid,
            $v_size,
            $v_mtime
        );
        $v_binary_data_last = pack(
            "a1a100a6a2a32a32a8a8a155a12",
            $p_type,
            $v_linkname,
            $v_magic,
            $v_version,
            $v_uname,
            $v_gname,
            $v_devmajor,
            $v_devminor,
            $v_prefix,
            ''
        );

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i = 0; $i < 148; $i++) {
            $v_checksum += ord(substr($v_binary_data_first, $i, 1));
        }
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i = 148; $i < 156; $i++) {
            $v_checksum += ord(' ');
        }
        // ..... Last part of the header
        for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
            $v_checksum += ord(substr($v_binary_data_last, $j, 1));
        }

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%06s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        return true;
    }

    /**
     * @param string $p_filename
     * @return bool
     */
    public function _writeLongHeader($p_filename)
    {
        $v_size = sprintf("%11s ", DecOct(strlen($p_filename)));

        $v_typeflag = 'L';

        $v_linkname = '';

        $v_magic = '';

        $v_version = '';

        $v_uname = '';

        $v_gname = '';

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack(
            "a100a8a8a8a12a12",
            '././@LongLink',
            0,
            0,
            0,
            $v_size,
            0
        );
        $v_binary_data_last = pack(
            "a1a100a6a2a32a32a8a8a155a12",
            $v_typeflag,
            $v_linkname,
            $v_magic,
            $v_version,
            $v_uname,
            $v_gname,
            $v_devmajor,
            $v_devminor,
            $v_prefix,
            ''
        );

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i = 0; $i < 148; $i++) {
            $v_checksum += ord(substr($v_binary_data_first, $i, 1));
        }
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i = 148; $i < 156; $i++) {
            $v_checksum += ord(' ');
        }
        // ..... Last part of the header
        for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
            $v_checksum += ord(substr($v_binary_data_last, $j, 1));
        }

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%06s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        // ----- Write the filename as content of the block
        $i = 0;
        while (($v_buffer = substr($p_filename, (($i++) * 512), 512)) != '') {
            $v_binary_data = pack("a512", "$v_buffer");
            $this->_writeBlock($v_binary_data);
        }

        return true;
    }

    /**
     * @param mixed $v_binary_data
     * @param mixed $v_header
     * @return bool
     */
    public function _readHeader($v_binary_data, &$v_header)
    {
        if (strlen($v_binary_data) == 0) {
            $v_header['filename'] = '';
            return true;
        }

        if (strlen($v_binary_data) != 512) {
            $v_header['filename'] = '';
            $this->_error('Invalid block size : ' . strlen($v_binary_data));
            return false;
        }

        if (!is_array($v_header)) {
            $v_header = array();
        }
        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        $v_binary_split = str_split($v_binary_data);
        $v_checksum += array_sum(array_map('ord', array_slice($v_binary_split, 0, 148)));
        $v_checksum += array_sum(array_map('ord', array(' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',)));
        $v_checksum += array_sum(array_map('ord', array_slice($v_binary_split, 156, 512)));


        $v_data = unpack($this->_fmt, $v_binary_data);

        if (strlen($v_data["prefix"]) > 0) {
            $v_data["filename"] = "$v_data[prefix]/$v_data[filename]";
        }

        // ----- Extract the checksum
        $v_header['checksum'] = OctDec(trim($v_data['checksum']));
        if ($v_header['checksum'] != $v_checksum) {
            $v_header['filename'] = '';

            // ----- Look for last block (empty block)
            if (($v_checksum == 256) && ($v_header['checksum'] == 0)) {
                return true;
            }

            $this->_error(
                'Invalid checksum for file "' . $v_data['filename']
                . '" : ' . $v_checksum . ' calculated, '
                . $v_header['checksum'] . ' expected'
            );
            return false;
        }

        // ----- Extract the properties
        $v_header['filename'] = rtrim($v_data['filename'], "\0");
        if ($this->_maliciousFilename($v_header['filename'])) {
            $this->_error(
                'Malicious .tar detected, file "' . $v_header['filename'] .
                '" will not install in desired directory tree'
            );
            return false;
        }
        $v_header['mode'] = OctDec(trim($v_data['mode']));
        $v_header['uid'] = OctDec(trim($v_data['uid']));
        $v_header['gid'] = OctDec(trim($v_data['gid']));
        $v_header['size'] = $this->_tarRecToSize($v_data['size']);
        $v_header['mtime'] = OctDec(trim($v_data['mtime']));
        if (($v_header['typeflag'] = $v_data['typeflag']) == "5") {
            $v_header['size'] = 0;
        }
        $v_header['link'] = trim($v_data['link']);
        /* ----- All these fields are removed form the header because
        they do not carry interesting info
        $v_header[magic] = trim($v_data[magic]);
        $v_header[version] = trim($v_data[version]);
        $v_header[uname] = trim($v_data[uname]);
        $v_header[gname] = trim($v_data[gname]);
        $v_header[devmajor] = trim($v_data[devmajor]);
        $v_header[devminor] = trim($v_data[devminor]);
        */

        return true;
    }

    /**
     * Convert Tar record size to actual size
     *
     * @param string $tar_size
     * @return size of tar record in bytes
     */
    private function _tarRecToSize($tar_size)
    {
        /*
         * First byte of size has a special meaning if bit 7 is set.
         *
         * Bit 7 indicates base-256 encoding if set.
         * Bit 6 is the sign bit.
         * Bits 5:0 are most significant value bits.
         */
        $ch = ord($tar_size[0]);
        if ($ch & 0x80) {
            // Full 12-bytes record is required.
            $rec_str = $tar_size . "\x00";

            $size = ($ch & 0x40) ? -1 : 0;
            $size = ($size << 6) | ($ch & 0x3f);

            for ($num_ch = 1; $num_ch < 12; ++$num_ch) {
                $size = ($size * 256) + ord($rec_str[$num_ch]);
            }

            return $size;

        } else {
            return OctDec(trim($tar_size));
        }
    }

    /**
     * Detect and report a malicious file name
     *
     * @param string $file
     *
     * @return bool
     */
    private function _maliciousFilename($file)
    {
        if (strpos($file, '/../') !== false) {
            return true;
        }
        if (strpos($file, '../') === 0) {
            return true;
        }
        return false;
    }

    /**
     * @param $v_header
     * @return bool
     */
    public function _readLongHeader(&$v_header)
    {
        $v_filename = '';
        $v_filesize = $v_header['size'];
        $n = floor($v_header['size'] / 512);
        for ($i = 0; $i < $n; $i++) {
            $v_content = $this->_readBlock();
            $v_filename .= $v_content;
        }
        if (($v_header['size'] % 512) != 0) {
            $v_content = $this->_readBlock();
            $v_filename .= $v_content;
        }

        // ----- Read the next header
        $v_binary_data = $this->_readBlock();

        if (!$this->_readHeader($v_binary_data, $v_header)) {
            return false;
        }

        $v_filename = rtrim(substr($v_filename, 0, $v_filesize), "\0");
        $v_header['filename'] = $v_filename;
        if ($this->_maliciousFilename($v_filename)) {
            $this->_error(
                'Malicious .tar detected, file "' . $v_filename .
                '" will not install in desired directory tree'
            );
            return false;
        }

        return true;
    }

    /**
     * This method extract from the archive one file identified by $p_filename.
     * The return value is a string with the file content, or null on error.
     *
     * @param string $p_filename The path of the file to extract in a string.
     *
     * @return a string with the file content or null.
     */
    private function _extractInString($p_filename)
    {
        $v_result_str = "";

        while (strlen($v_binary_data = $this->_readBlock()) != 0) {
            if (!$this->_readHeader($v_binary_data, $v_header)) {
                return null;
            }

            if ($v_header['filename'] == '') {
                continue;
            }

            // ----- Look for long filename
            if ($v_header['typeflag'] == 'L') {
                if (!$this->_readLongHeader($v_header)) {
                    return null;
                }
            }

            if ($v_header['filename'] == $p_filename) {
                if ($v_header['typeflag'] == "5") {
                    $this->_error(
                        'Unable to extract in string a directory '
                        . 'entry {' . $v_header['filename'] . '}'
                    );
                    return null;
                } else {
                    $n = floor($v_header['size'] / 512);
                    for ($i = 0; $i < $n; $i++) {
                        $v_result_str .= $this->_readBlock();
                    }
                    if (($v_header['size'] % 512) != 0) {
                        $v_content = $this->_readBlock();
                        $v_result_str .= substr(
                            $v_content,
                            0,
                            ($v_header['size'] % 512)
                        );
                    }
                    return $v_result_str;
                }
            } else {
                $this->_jumpBlock(ceil(($v_header['size'] / 512)));
            }
        }

        return null;
    }

    /**
     * @param string $p_path
     * @param string $p_list_detail
     * @param string $p_mode
     * @param string $p_file_list
     * @param string $p_remove_path
     * @param bool $p_preserve
     * @return bool
     */
    public function _extractList(
        $p_path,
        &$p_list_detail,
        $p_mode,
        $p_file_list,
        $p_remove_path,
        $p_preserve = false
    ) {
        $v_result = true;
        $v_nb = 0;
        $v_extract_all = true;
        $v_listing = false;

        $p_path = $this->_translateWinPath($p_path, false);
        if ($p_path == '' || (substr($p_path, 0, 1) != '/'
                && substr($p_path, 0, 3) != "../" && !strpos($p_path, ':'))
        ) {
            $p_path = "./" . $p_path;
        }
        $p_remove_path = $this->_translateWinPath($p_remove_path);

        // ----- Look for path to remove format (should end by /)
        if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/')) {
            $p_remove_path .= '/';
        }
        $p_remove_path_size = strlen($p_remove_path);

        switch ($p_mode) {
            case "complete" :
                $v_extract_all = true;
                $v_listing = false;
                break;
            case "partial" :
                $v_extract_all = false;
                $v_listing = false;
                break;
            case "list" :
                $v_extract_all = false;
                $v_listing = true;
                break;
            default :
                $this->_error('Invalid extract mode (' . $p_mode . ')');
                return false;
        }

        clearstatcache();

        while (strlen($v_binary_data = $this->_readBlock()) != 0) {
            $v_extract_file = false;
            $v_extraction_stopped = 0;

            if (!$this->_readHeader($v_binary_data, $v_header)) {
                return false;
            }

            if ($v_header['filename'] == '') {
                continue;
            }

            // ----- Look for long filename
            if ($v_header['typeflag'] == 'L') {
                if (!$this->_readLongHeader($v_header)) {
                    return false;
                }
            }

            // ignore extended / pax headers
            if ($v_header['typeflag'] == 'x' || $v_header['typeflag'] == 'g') {
                $this->_jumpBlock(ceil(($v_header['size'] / 512)));
                continue;
            }

            if ((!$v_extract_all) && (is_array($p_file_list))) {
                // ----- By default no unzip if the file is not found
                $v_extract_file = false;

                for ($i = 0; $i < sizeof($p_file_list); $i++) {
                    // ----- Look if it is a directory
                    if (substr($p_file_list[$i], -1) == '/') {
                        // ----- Look if the directory is in the filename path
                        if ((strlen($v_header['filename']) > strlen($p_file_list[$i]))
                            && (substr($v_header['filename'], 0, strlen($p_file_list[$i]))
                                == $p_file_list[$i])
                        ) {
                            $v_extract_file = true;
                            break;
                        }
                    } // ----- It is a file, so compare the file names
                    elseif ($p_file_list[$i] == $v_header['filename']) {
                        $v_extract_file = true;
                        break;
                    }
                }
            } else {
                $v_extract_file = true;
            }

            // ----- Look if this file need to be extracted
            if (($v_extract_file) && (!$v_listing)) {
                if (($p_remove_path != '')
                    && (substr($v_header['filename'] . '/', 0, $p_remove_path_size)
                        == $p_remove_path)
                ) {
                    $v_header['filename'] = substr(
                        $v_header['filename'],
                        $p_remove_path_size
                    );
                    if ($v_header['filename'] == '') {
                        continue;
                    }
                }
                if (($p_path != './') && ($p_path != '/')) {
                    while (substr($p_path, -1) == '/') {
                        $p_path = substr($p_path, 0, strlen($p_path) - 1);
                    }

                    if (substr($v_header['filename'], 0, 1) == '/') {
                        $v_header['filename'] = $p_path . $v_header['filename'];
                    } else {
                        $v_header['filename'] = $p_path . '/' . $v_header['filename'];
                    }
                }
                if (file_exists($v_header['filename'])) {
                    if ((@is_dir($v_header['filename']))
                        && ($v_header['typeflag'] == '')
                    ) {
                        $this->_error(
                            'File ' . $v_header['filename']
                            . ' already exists as a directory'
                        );
                        return false;
                    }
                    if (($this->_isArchive($v_header['filename']))
                        && ($v_header['typeflag'] == "5")
                    ) {
                        $this->_error(
                            'Directory ' . $v_header['filename']
                            . ' already exists as a file'
                        );
                        return false;
                    }
                    if (!is_writeable($v_header['filename'])) {
                        $this->_error(
                            'File ' . $v_header['filename']
                            . ' already exists and is write protected'
                        );
                        return false;
                    }
                    if (filemtime($v_header['filename']) > $v_header['mtime']) {
                        // To be completed : An error or silent no replace ?
                    }
                } // ----- Check the directory availability and create it if necessary
                elseif (($v_result
                        = $this->_dirCheck(
                        ($v_header['typeflag'] == "5"
                            ? $v_header['filename']
                            : dirname($v_header['filename']))
                    )) != 1
                ) {
                    $this->_error('Unable to create path for ' . $v_header['filename']);
                    return false;
                }

                if ($v_extract_file) {
                    if ($v_header['typeflag'] == "5") {
                        if (!@file_exists($v_header['filename'])) {
                            if (!@mkdir($v_header['filename'], 0777)) {
                                $this->_error(
                                    'Unable to create directory {'
                                    . $v_header['filename'] . '}'
                                );
                                return false;
                            }
                        }
                    } elseif ($v_header['typeflag'] == "2") {
                        if (@file_exists($v_header['filename'])) {
                            @unlink($v_header['filename']);
                        }
                        if (!@symlink($v_header['link'], $v_header['filename'])) {
                            $this->_error(
                                'Unable to extract symbolic link {'
                                . $v_header['filename'] . '}'
                            );
                            return false;
                        }
                    } else {
                        if (($v_dest_file = @fopen($v_header['filename'], "wb")) == 0) {
                            $this->_error(
                                'Error while opening {' . $v_header['filename']
                                . '} in write binary mode'
                            );
                            return false;
                        } else {
                            $n = floor($v_header['size'] / 512);
                            for ($i = 0; $i < $n; $i++) {
                                $v_content = $this->_readBlock();
                                fwrite($v_dest_file, $v_content, 512);
                            }
                            if (($v_header['size'] % 512) != 0) {
                                $v_content = $this->_readBlock();
                                fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
                            }

                            @fclose($v_dest_file);

                            if ($p_preserve) {
                                @chown($v_header['filename'], $v_header['uid']);
                                @chgrp($v_header['filename'], $v_header['gid']);
                            }

                            // ----- Change the file mode, mtime
                            @touch($v_header['filename'], $v_header['mtime']);
                            if ($v_header['mode'] & 0111) {
                                // make file executable, obey umask
                                $mode = fileperms($v_header['filename']) | (~umask() & 0111);
                                @chmod($v_header['filename'], $mode);
                            }
                        }

                        // ----- Check the file size
                        clearstatcache();
                        if (!is_file($v_header['filename'])) {
                            $this->_error(
                                'Extracted file ' . $v_header['filename']
                                . 'does not exist. Archive may be corrupted.'
                            );
                            return false;
                        }

                        $filesize = filesize($v_header['filename']);
                        if ($filesize != $v_header['size']) {
                            $this->_error(
                                'Extracted file ' . $v_header['filename']
                                . ' does not have the correct file size \''
                                . $filesize
                                . '\' (' . $v_header['size']
                                . ' expected). Archive may be corrupted.'
                            );
                            return false;
                        }
                    }
                } else {
                    $this->_jumpBlock(ceil(($v_header['size'] / 512)));
                }
            } else {
                $this->_jumpBlock(ceil(($v_header['size'] / 512)));
            }

            /* TBC : Seems to be unused ...
            if ($this->_compress)
              $v_end_of_file = @gzeof($this->_file);
            else
              $v_end_of_file = @feof($this->_file);
              */

            if ($v_listing || $v_extract_file || $v_extraction_stopped) {
                // ----- Log extracted files
                if (($v_file_dir = dirname($v_header['filename']))
                    == $v_header['filename']
                ) {
                    $v_file_dir = '';
                }
                if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == '')) {
                    $v_file_dir = '/';
                }

                $p_list_detail[$v_nb++] = $v_header;
                if (is_array($p_file_list) && (count($p_list_detail) == count($p_file_list))) {
                    return true;
                }
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _openAppend()
    {
        if (filesize($this->_tarname) == 0) {
            return $this->_openWrite();
        }

        if ($this->_compress) {
            $this->_close();

            if (!@rename($this->_tarname, $this->_tarname . ".tmp")) {
                $this->_error(
                    'Error while renaming \'' . $this->_tarname
                    . '\' to temporary file \'' . $this->_tarname
                    . '.tmp\''
                );
                return false;
            }

            if ($this->_compress_type == 'gz') {
                $v_temp_tar = @gzopen($this->_tarname . ".tmp", "rb");
            } elseif ($this->_compress_type == 'bz2') {
                $v_temp_tar = @bzopen($this->_tarname . ".tmp", "r");
            } elseif ($this->_compress_type == 'lzma2') {
                $v_temp_tar = @xzopen($this->_tarname . ".tmp", "r");
            }


            if ($v_temp_tar == 0) {
                $this->_error(
                    'Unable to open file \'' . $this->_tarname
                    . '.tmp\' in binary read mode'
                );
                @rename($this->_tarname . ".tmp", $this->_tarname);
                return false;
            }

            if (!$this->_openWrite()) {
                @rename($this->_tarname . ".tmp", $this->_tarname);
                return false;
            }

            if ($this->_compress_type == 'gz') {
                $end_blocks = 0;

                while (!@gzeof($v_temp_tar)) {
                    $v_buffer = @gzread($v_temp_tar, 512);
                    if ($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0) {
                        $end_blocks++;
                        // do not copy end blocks, we will re-make them
                        // after appending
                        continue;
                    } elseif ($end_blocks > 0) {
                        for ($i = 0; $i < $end_blocks; $i++) {
                            $this->_writeBlock(ARCHIVE_TAR_END_BLOCK);
                        }
                        $end_blocks = 0;
                    }
                    $v_binary_data = pack("a512", $v_buffer);
                    $this->_writeBlock($v_binary_data);
                }

                @gzclose($v_temp_tar);
            } elseif ($this->_compress_type == 'bz2') {
                $end_blocks = 0;

                while (strlen($v_buffer = @bzread($v_temp_tar, 512)) > 0) {
                    if ($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0) {
                        $end_blocks++;
                        // do not copy end blocks, we will re-make them
                        // after appending
                        continue;
                    } elseif ($end_blocks > 0) {
                        for ($i = 0; $i < $end_blocks; $i++) {
                            $this->_writeBlock(ARCHIVE_TAR_END_BLOCK);
                        }
                        $end_blocks = 0;
                    }
                    $v_binary_data = pack("a512", $v_buffer);
                    $this->_writeBlock($v_binary_data);
                }

                @bzclose($v_temp_tar);
            } elseif ($this->_compress_type == 'lzma2') {
                $end_blocks = 0;

                while (strlen($v_buffer = @xzread($v_temp_tar, 512)) > 0) {
                    if ($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0) {
                        $end_blocks++;
                        // do not copy end blocks, we will re-make them
                        // after appending
                        continue;
                    } elseif ($end_blocks > 0) {
                        for ($i = 0; $i < $end_blocks; $i++) {
                            $this->_writeBlock(ARCHIVE_TAR_END_BLOCK);
                        }
                        $end_blocks = 0;
                    }
                    $v_binary_data = pack("a512", $v_buffer);
                    $this->_writeBlock($v_binary_data);
                }

                @xzclose($v_temp_tar);
            }

            if (!@unlink($this->_tarname . ".tmp")) {
                $this->_error(
                    'Error while deleting temporary file \''
                    . $this->_tarname . '.tmp\''
                );
            }
        } else {
            // ----- For not compressed tar, just add files before the last
            //       one or two 512 bytes block
            if (!$this->_openReadWrite()) {
                return false;
            }

            clearstatcache();
            $v_size = filesize($this->_tarname);

            // We might have zero, one or two end blocks.
            // The standard is two, but we should try to handle
            // other cases.
            fseek($this->_file, $v_size - 1024);
            if (fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK) {
                fseek($this->_file, $v_size - 1024);
            } elseif (fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK) {
                fseek($this->_file, $v_size - 512);
            }
        }

        return true;
    }

    /**
     * @param $p_filelist
     * @param string $p_add_dir
     * @param string $p_remove_dir
     * @return bool
     */
    public function _append($p_filelist, $p_add_dir = '', $p_remove_dir = '')
    {
        if (!$this->_openAppend()) {
            return false;
        }

        if ($this->_addList($p_filelist, $p_add_dir, $p_remove_dir)) {
            $this->_writeFooter();
        }

        $this->_close();

        return true;
    }

    /**
     * Check if a directory exists and create it (including parent
     * dirs) if not.
     *
     * @param string $p_dir directory to check
     *
     * @return bool true if the directory exists or was created
     */
    public function _dirCheck($p_dir)
    {
        clearstatcache();
        if ((@is_dir($p_dir)) || ($p_dir == '')) {
            return true;
        }

        $p_parent_dir = dirname($p_dir);

        if (($p_parent_dir != $p_dir) &&
            ($p_parent_dir != '') &&
            (!$this->_dirCheck($p_parent_dir))
        ) {
            return false;
        }

        if (!@mkdir($p_dir, 0777)) {
            $this->_error("Unable to create directory '$p_dir'");
            return false;
        }

        return true;
    }

    /**
     * Compress path by changing for example "/dir/foo/../bar" to "/dir/bar",
     * rand emove double slashes.
     *
     * @param string $p_dir path to reduce
     *
     * @return string reduced path
     */
    private function _pathReduction($p_dir)
    {
        $v_result = '';

        // ----- Look for not empty path
        if ($p_dir != '') {
            // ----- Explode path by directory names
            $v_list = explode('/', $p_dir);

            // ----- Study directories from last to first
            for ($i = sizeof($v_list) - 1; $i >= 0; $i--) {
                // ----- Look for current path
                if ($v_list[$i] == ".") {
                    // ----- Ignore this directory
                    // Should be the first $i=0, but no check is done
                } else {
                    if ($v_list[$i] == "..") {
                        // ----- Ignore it and ignore the $i-1
                        $i--;
                    } else {
                        if (($v_list[$i] == '')
                            && ($i != (sizeof($v_list) - 1))
                            && ($i != 0)
                        ) {
                            // ----- Ignore only the double '//' in path,
                            // but not the first and last /
                        } else {
                            $v_result = $v_list[$i] . ($i != (sizeof($v_list) - 1) ? '/'
                                    . $v_result : '');
                        }
                    }
                }
            }
        }

        if (defined('OS_WINDOWS') && OS_WINDOWS) {
            $v_result = strtr($v_result, '\\', '/');
        }

        return $v_result;
    }

    /**
     * @param $p_path
     * @param bool $p_remove_disk_letter
     * @return string
     */
    public function _translateWinPath($p_path, $p_remove_disk_letter = true)
    {
        if (defined('OS_WINDOWS') && OS_WINDOWS) {
            // ----- Look for potential disk letter
            if (($p_remove_disk_letter)
                && (($v_position = strpos($p_path, ':')) != false)
            ) {
                $p_path = substr($p_path, $v_position + 1);
            }
            // ----- Change potential windows directory separator
            if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\')) {
                $p_path = strtr($p_path, '\\', '/');
            }
        }
        return $p_path;
    }
}

if (! function_exists('scandir')) {
    function scandir($dir, $sortorder = 0)
    {
        if (is_dir($dir)) {
            $dirlist = opendir($dir);

            while (($file = readdir($dirlist)) !== false)
                $files[] = $file;

            // arsort was replaced with rsort
            ($sortorder == 0) ? asort($files) : rsort($files);

            return $files;
        }
        else
            return true;
    }
}

function change_ownership($location, $user, $group)
{
    $files = scandir($location);

    foreach ($files as $child) {
        if (in_array($child, array('.', '..', 'db')))
            continue;

        $full = "$location/$child";
        $full = realpath($full);

        if (is_dir($full))
            change_ownership($full, $user, $group);

        @chgrp($full, $group);
        @chown($full, $user);
    }
}

set_time_limit(0);

$archive = new Archive_Tar($_GET[1]);
$archive->extract($_GET[2]);

$group = filegroup(dirname(__FILE__));
$user = fileowner(dirname(__FILE__));

change_ownership(dirname(__FILE__), $user, $group);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
