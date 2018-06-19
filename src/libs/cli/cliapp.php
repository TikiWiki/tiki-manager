<?php
namespace trim\cli;

$app = new \Symfony\Component\Console\Application();
$dir = getcwd();

chdir(dirname(__FILE__));
foreach (glob('*Command.php') as $filename) {
    include($filename);
    $classname = preg_replace('/\.php$/', '', $filename);
    $classname = __NAMESPACE__ . '\\command\\' . $classname;
    class_exists($classname) && $app->add(new $classname());
}

chdir($dir);
$app->run();
