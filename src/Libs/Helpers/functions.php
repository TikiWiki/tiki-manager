<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

define('DS', DIRECTORY_SEPARATOR);

require_once dirname(__DIR__) . '/Requirements/Requirements.php';
require_once dirname(__DIR__) . '/Requirements/LinuxRequirements.php';
require_once dirname(__DIR__) . '/Requirements/WindowsRequirements.php';

use TikiManager\Config\App;
use TikiManager\Libs\Helpers\LoggerManager;

if (! function_exists('readline')) {
    /**
     * @deprecated
     */
    function readline($prompt)
    {
        echo $prompt;
        $fp = fopen('php://stdin', 'r');
        $line = rtrim(fgets($fp, 1024));
        return $line;
    }
}

/**
 * @deprecated
 */
function color($string, $color)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return $string;
    }

    $avail = [
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'cyan' => 36,
        'pink' => '1;35',
    ];

    if (!isset($avail[$color])) {
        return $string;
    }

    return "\033[{$avail[$color]}m$string\033[0m";
}

function getPassword($stars = false)
{
    // Get current style
    $oldStyle = shell_exec('stty -g');

    if ($stars === false) {
        shell_exec('stty -echo');
        $password = rtrim(fgets(STDIN), "\n");
    } else {
        shell_exec('stty -icanon -echo min 1 time 0');
        $password = '';

        while (true) {
            $char = fgetc(STDIN);

            if ($char == "\n") {
                break;
            } elseif (ord($char) == 127) {
                if (strlen($password) > 0) {
                    fwrite(STDOUT, "\x08 \x08");
                    $password = substr($password, 0, -1);
                }
            } else {
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

function promptPassword($prompt = "Password", $stars = true, $allowEmpty = false)
{
    $password = '';
    while (empty($password) && !$allowEmpty) {
        print "{$prompt}: ";
        $password = getPassword($stars);
        echo PHP_EOL;
    }
    return $password;
}

function prefix($text, $prefix)
{
    if (!is_string($text)) {
        return $text;
    }
    if (is_string($prefix) && !empty($prefix)) {
        return preg_replace('/^/m', "{$prefix} \$1", $text);
    }
    return $text;
}

function stringfy($sub)
{
    if (is_string($sub)) {
        return $sub;
    }
    return var_export($sub, true);
}

/**
 * @deprecated
 */
function appendFlush()
{
    // By default php-fpm uses 4096B buffers.
    // This forces the buffer to get enough data to output.
    return PHP_SAPI != 'cli' ? str_pad('', 4 * 1024) : '';
}

/**
 * @deprecated
 */
function info($text, $prefix = null)
{
    echo color("$text\n", 'cyan') . appendFlush();
    return $text;
}

/**
 * @deprecated
 */
function warning($text, $prefix = null)
{
    echo color("$text\n", 'yellow') . appendFlush();
    return $text;
}

/**
 * @deprecated
 */
function error($text, $prefix = null)
{
    echo color("$text\n", 'red') . appendFlush();
    return $text;
}

function debug($text, $prefix = null, $hr = '')
{
    if (isset($_ENV['TRIM_DEBUG']) && $_ENV['TRIM_DEBUG'] === true) {
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

function get_username_by_id($id)
{
    $passwd = fopen('/etc/passwd', 'r');
    while (false !== ($line = fgets($passwd))) {
        list($name, $pass, $uid, $comment, $home, $shell) = explode(':', $line);

        if ($uid == "$id") {
            fclose($passwd);
            return $name;
        }
    }
    fclose($passwd);
}

function get_groupname_by_id($id)
{
    $groups = fopen('/etc/group', 'r');
    while (false !== ($line = fgets($groups))) {
        list($name, $pass, $gid, $users) = explode(':', $line);

        if ($gid == "$id") {
            fclose($groups);
            return $name;
        }
    }
    fclose($groups);
}

function secure_trim_data($should_set = false)
{
    $modes = ['---', '--x', '-w-', '-wx', 'r--', 'r-x', 'rw-', 'rwx'];
    $stat = stat($_ENV['TRIM_DATA']);

    $cur_mode = $stat['mode'];
    $exp_mode = (($cur_mode >> 6) << 6) | 0b111000000;

    $owner_name = get_username_by_id($stat['uid']);
    $group_name = get_groupname_by_id($stat['gid']);

    if ($cur_mode & 0b111111) {
        $chmod_success = $should_set && chmod($_ENV['TRIM_DATA'], $exp_mode);

        if (!$chmod_success) {
            $message = 'Your Tiki Manager data is unsafe!'.PHP_EOL;
            $message .= sprintf(
                '  Currently it is: d%s%s%s	%s:%s	%s',
                $modes[($cur_mode >> 6) & 0b111],
                $modes[($cur_mode >> 3) & 0b111],
                $modes[$cur_mode & 0b111],
                $owner_name,
                $group_name,
                $_ENV['TRIM_DATA']
            ) . PHP_EOL;
            $message .= sprintf(
                '  Should be like:  drwx------	%s:%s	%s',
                $owner_name,
                $group_name,
                $_ENV['TRIM_DATA']
            );
            App::get('io')->warning($message);
        }
    }
}

function isWindows()
{
    return substr(PHP_OS, 0, 3) == 'WIN';
}

function query($query, $params = null)
{
    if (is_null($params)) {
        $params = [];
    }
    foreach ($params as $key => $value) {
        if (is_null($value)) {
            $query = str_replace($key, 'NULL', $query);
        } elseif (is_int($value)) {
            $query = str_replace($key, (int)$value, $query);
        } elseif (is_array($value)) {
            error("Unsupported query parameter type: array\n");
            printf("Query\n\"%s\"\nParameters:\n", $query);
            var_dump($params);
            printf("Backtrace:\n");
            debug_print_backtrace();
            exit(1);
        } else {
            $query = str_replace($key, "'$value'", $query);
        }
    }

    global $db;
    $ret = $db->query($query);

    // TODO: log error
    // if (! $ret) echo "$query\n";

    return $ret;
}

function rowid()
{
    global $db;
    return $db->lastInsertId();
}

// Tools
function findDigits($selection)
{
    // Accept ranges of type 2-10
    $selection = preg_replace_callback(
        '/(\d+)-(\d+)/',
        function ($matches) {
            return implode(' ', range($matches[1], $matches[2]));
        },
        $selection
    );

    preg_match_all('/\d+/', $selection, $matches, PREG_PATTERN_ORDER);
    return $matches[0];
}

function getEntries($list, $selection)
{
    if (!is_array($selection)) {
        $selection = findDigits($selection);
    }

    $output = [];
    foreach ($selection as $index) {
        if (array_key_exists($index, $list)) {
            $output[] = $list[$index];
        }
    }

    return $output;
}

/**
 * Ask the user to select one or more instances to perform
 * an action.
 *
 * @param array $instances - list of Instance objects
 * @param $selectionQuestion - message displayed to the user before the list of available instances
 * @return array|string - one or more instances objects
 */
function selectInstances(array $instances, $selectionQuestion)
{
    echo $selectionQuestion;

    printInstances($instances);

    $selection = readline('>>> ');
    $selection = getEntries($instances, $selection);

    return $selection;
}

/**
 * Print a list of instances to the user for selection.
 *
 * @param array $instances - list of Instance objects
 */
function printInstances(array $instances)
{
    foreach ($instances as $key => $i) {
        $name = substr($i->name, 0, 18);
        $weburl = substr($i->weburl, 0, 38);
        $branch = isset($i->branch) ? $i->branch : '';

        echo "[$i->id] " . str_pad($name, 20) . str_pad($weburl, 40) . str_pad($i->contact, 30) . str_pad($branch, 20) . "\n";
    }
}

/**
 * Prompt for a user value.
 *
 * @param string $prompt Prompt text.
 * @param string $default Default value.
 * @param string $values Acceptable values.
 * @return string User-supplied value.
 *
 * @deprecated
 */

function promptUser($prompt, $default = false, $values = [])
{
    if (!$_ENV['INTERACTIVE']) {
        return $default;
    }

    if (is_array($values) && count($values)) {
        $prompt .= ' (' . implode(', ', $values) . ')';
    }
    if ($default !== false && strlen($default)) {
        $prompt .= " [$default]";
    }

    do {
        $answer = trim(readline($prompt . ' : '));
        if (!strlen($answer)) {
            $answer = $default;
        }

        if (is_array($values) && count($values)) {
            if (in_array($answer, $values)) {
                return $answer;
            }
        } elseif (!is_bool($default)) {
            return $answer;
        } elseif (strlen($answer)) {
            return $answer;
        }

        error("Invalid response.\n");
    } while (true);
}

function php()
{

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $paths = `where php`;
    } else {
        $paths = `whereis php 2>> logs/trim.output`;
    }
    $phps = explode(' ', $paths);

    // Check different versions
    $valid = [];
    foreach ($phps as $interpreter) {
        if (!in_array(basename($interpreter), ['php', 'php5'])) {
            continue;
        }

        if (!@is_executable($interpreter)) {
            continue;
        }

        if (@is_dir($interpreter)) {
            continue;
        }

        $versionInfo = `$interpreter -v`;
        if (preg_match('/PHP (\d+\.\d+\.\d+)/', $versionInfo, $matches)) {
            $valid[$matches[1]] = $interpreter;
        }
    }

    // Handle easy cases
    if (count($valid) == 0) {
        return null;
    }
    if (count($valid) == 1) {
        return reset($valid);
    }

    // List available options for user
    krsort($valid);
    return reset($valid);
}

function setupPhar()
{
    $pharPath = Phar::running(false);

    $phar = new Phar($pharPath);
    //extract scripts
    if (!file_exists($_ENV['SCRIPTS_FOLDER'])) {
        mkdir($_ENV['SCRIPTS_FOLDER']);
    }
    $result = $phar->extractTo($_ENV['TRIM_ROOT'], explode(',', $_ENV['EXECUTABLE_SCRIPT']), true);
}

function trim_output($output, $context = [])
{
    $logger = LoggerManager::getInstance();
    $logger->logInfo($output, $context);
}

function trim_debug($output)
{
    if ($_ENV['TRIM_DEBUG']) {
        trim_output($output);
    }
}

function cache_folder($app, $version)
{
    $key = sprintf('%s-%s-%s', $app->getName(), $version->type, $version->branch);
    $key = str_replace('/', '_', $key);
    $folder = $_ENV['CACHE_FOLDER'] . "/$key";

    return $folder;
}

/**
 * @param string $dir
 * @param int $version The channel version of composer (1 or 2)
 * @return string The composer phar path
 * @throws Exception
 */
function installComposer(string $dir, int $version = 2): string
{
    $expectedSig = \trim(\file_get_contents('https://composer.github.io/installer.sig'));
    $installerURL = 'https://getcomposer.org/installer';
    $setupFile = 'composer-setup.php';

    try {
        if (!\copy($installerURL, $setupFile)) {
            throw new \Exception('Failed to copy file to ' . $setupFile);
        }
    } catch (\Exception $e) {
        $message = \sprintf('Unable to download composer installer from %s', $installerURL);
        $message .= PHP_EOL . $e->getMessage();
        throw new Exception($message);
    }

    $actualSig = \hash_file('SHA384', $setupFile);

    if ($expectedSig !== $actualSig) {
        \unlink($setupFile);
        throw new Exception('Invalid composer installer signature.');
    }

    $command = sprintf('%s %s --%d --quiet --install-dir=%s', PHP_BINARY, $setupFile, $version, escapeshellarg($dir));
    exec($command, $output, $exitCode);
    \unlink($setupFile);

    if ($exitCode !== 0) {
        throw new Exception('There was a problem when installing Composer.');
    }

    return rtrim($dir, DS) . DS . 'composer.phar';
}

/**
 * Detect the composer path based on a directory
 * @param $dir
 * @return string|null
 */
function detectComposer($dir)
{
    $composerPhar = rtrim($dir, DS) . DS . 'composer.phar';

    if (file_exists($composerPhar)) {
        return $composerPhar;
    }

    $requirementsInstance = TikiManager\Libs\Requirements\Requirements::getInstance();
    $composerPath = $requirementsInstance->getDependencyPath('composer');

    return $composerPath ?: null;
}

/**
 * Install composer dependencies within a directory
 * @param string $dir
 * @param string $composerBin Composer Binary path
 * @throws Exception
 */
function installComposerDependencies(string $dir, string $composerBin)
{
    if (!is_executable($composerBin)) {
        $composerBin = PHP_BINARY . ' ' . $composerBin;
    }

    $command = sprintf('%s install --prefer-dist --no-interaction --no-progress --no-dev --working-dir=%s', $composerBin, $dir);
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        throw new Exception('There was a problem when installing Tiki Manager dependencies.');
    }
}
