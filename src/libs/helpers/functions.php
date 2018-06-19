<?php

if (! function_exists('readline')) {
    function readline($prompt)
    {
        echo $prompt;
        $fp = fopen('php://stdin', 'r');
        $line = rtrim(fgets($fp, 1024));
        return $line;
    }
}

function color($string, $color)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        return;

    $avail = array(
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'cyan' => 36,
        'pink' => '1;35',
    );

    if (! isset($avail[$color]))
        return $string;

    return "\033[{$avail[$color]}m$string\033[0m";
}

function getPassword($stars = false)
{
    // Get current style
    $oldStyle = shell_exec('stty -g');

    if ($stars === false) {
        shell_exec('stty -echo');
        $password = rtrim(fgets(STDIN), "\n");
    }
    else {
        shell_exec('stty -icanon -echo min 1 time 0');
        $password = '';

        while (true) {
            $char = fgetc(STDIN);

            if ($char == "\n")
                break;
            else if (ord($char) == 127) {
                if (strlen($password) > 0) {
                    fwrite(STDOUT, "\x08 \x08");
                    $password = substr($password, 0, -1);
                }
            }
            else {
                fwrite(STDOUT, "*");
                $password .= $char;
            }
        }
    }

    // Reset old style
    shell_exec("stty $oldStyle");

    // Return the password
    return $password;
}

function promptPassword($prompt="Password", $stars=true, $allowEmpty=false)
{
    $password = '';
    while(empty($password) && !$allowEmpty) {
        print "{$prompt}: ";
        $password = getPassword($stars);
        echo PHP_EOL;
    }
    return $password;
}

function prefix($text, $prefix)
{
    if(!is_string($text)) {
        return $text;
    }
    if(is_string($prefix) && !empty($prefix)) {
        return preg_replace('/^/m', "{$prefix} \$1", $text);
    }
    return $text;
}

function stringfy($sub)
{
    if(is_string($sub)) {
        return $sub;
    }
    return var_export($sub, true);
}

function info($text, $prefix=null)
{
    $output = prefix(stringfy($text), $prefix) . "\n";
    echo color("$text\n", 'cyan');
    return $text;
}

function warning($text, $prefix=null)
{
    $output = prefix(stringfy($text), $prefix) . "\n";
    echo color("$text\n", 'yellow');
    return $text;
}

function error($text, $prefix=null)
{
    $output = prefix(stringfy($text), $prefix) . "\n";
    echo color("$text\n", 'red');
    return $text;
}

function debug($text, $prefix=null, $hr='')
{
    if(TRIM_DEBUG) {
        $prefix = '[' . date('Y-m-d H:i:s') . '][debug]:' . ($prefix ? " {$prefix}" : '');
        $output = "\n";

        if (getenv('TRIM_DEBUG_TRACE') === 'true') {
            ob_start();
            debug_print_backtrace();
            $output .= prefix(ob_get_clean(), $prefix) . "\n";
        }

        $output .= prefix(stringfy($text), $prefix) . "\n";
        echo color($output, 'pink');

        if (is_string($hr) && !empty($hr)) {
            echo "$hr";
        }

        if (getenv('TRIM_DEBUG_LOG')) {
            file_put_contents(getenv('TRIM_DEBUG_LOG'), "$output\n", FILE_APPEND);
        }
    }
    return $text;
}

function get_username_by_id($id) {
    $passwd = fopen('/etc/passwd', 'r');
    while(false !== ($line = fgets($passwd))) {
        list($name, $pass, $uid, $comment, $home, $shell) = explode(':', $line);

        if($uid == "$id") {
            fclose($passwd);
            return $name;
        }
    }
    fclose($passwd);
}

function get_groupname_by_id($id) {
    $groups = fopen('/etc/group', 'r');
    while(false !== ($line = fgets($groups))) {
        list($name, $pass, $gid, $users) = explode(':', $line);

        if($gid == "$id") {
            fclose($groups);
            return $name;
        }
    }
    fclose($groups);
}

function secure_trim_data($should_set=false) {
    $modes = array('---', '--x', '-w-', '-wx', 'r--', 'r-x', 'rw-', 'rwx');
    $stat = stat(TRIM_DATA);

    $cur_mode = $stat['mode'];
    $exp_mode = (($cur_mode >> 6) << 6) | 0b111000000;

    $owner_name = get_username_by_id($stat['uid']);
    $group_name = get_groupname_by_id($stat['gid']);

    if ($cur_mode & 0b111111) {
        $chmod_success = $should_set && chmod(TRIM_DATA, $exp_mode);

        if (!$chmod_success) {
            error("Your TRIM data is unsafe! ");
            error(sprintf(
                '  Currently it is: d%s%s%s	%s:%s	%s',
                $modes[ ($cur_mode >> 6) & 0b111 ],
                $modes[ ($cur_mode >> 3) & 0b111 ],
                $modes[ $cur_mode        & 0b111 ],
                $owner_name,
                $group_name,
                TRIM_DATA
            ));
            error(sprintf(
                '  Should be like:  drwx------	%s:%s	%s',
                $owner_name,
                $group_name,
                TRIM_DATA
            ));
        }
    }
}

function array_flatten ($array, $objectFlat=false)
{
    $result = array();
    $visited = array();
    $queue = array();
    $current = null;

    if (is_array($array) || ($objectFlat && is_object($array))) {
        $queue[] = $array;
    }

    while (!empty($queue)) {
        $current = array_shift($queue);
        if ($objectFlat && is_object($current)) {
            $current = get_object_vars($current);
        }
        if (!is_array($current)) {
            $result[] = $current;
            continue;
        }
        foreach ($current as $key => $value) {
            if (is_array($value) || ($objectFlat && is_object($value))) {
                $queue[] = $value;
            }
            else {
                $result[] = $value;
            }
        }
    }

    return $result;
}

/**
 * @author http://php.net/manual/pt_BR/function.realpath.php#84012
 */
function get_absolute_path($path) {
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $parts = array_filter($parts, 'strlen');

    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) {
            continue;
        }
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }

    if(substr($path, 0, 1) === DIRECTORY_SEPARATOR) {
        $path = DIRECTORY_SEPARATOR;
    } else {
        $path = '';
    }

    return $path . implode(DIRECTORY_SEPARATOR, $absolutes);
}


function run_composer_install()
{
    $autoload = TRIM_ROOT . '/vendor/autoload.php';
    $composer = TRIM_ROOT . '/tmp/composer';

    if (file_exists($autoload)) {
        return true;
    }

    if (!file_exists($composer)) {
        info("Downloading composer into '{$composer}'");
        copy('https://getcomposer.org/composer.phar', $composer);
        chmod($composer, 0755);
    }

    if (!file_exists($composer)) {
        error("Failed to download composer");
        exit(1);
    }

    $result = shell_exec(implode(' ', array(
        PHP_BINARY,
        escapeshellarg($composer),
        'install'
    )));

    return !empty($result);
}
