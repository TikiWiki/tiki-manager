<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Config;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use TikiManager\Application\Info;
use TikiManager\Application\Instance;
use TikiManager\Libs\Database\Database;
use TikiManager\Style\Formatter\HtmlOutputFormatter;
use TikiManager\Style\TikiManagerStyle;

class App
{

    public static function getContainer()
    {
        /** @var ContainerBuilder $container */
        static $container;

        if ($container) {
            return $container;
        }

        $container = new ContainerBuilder();

//        // This service is registered in Environment::setIo()
//        $container->register('io', TikiManagerStyle::class)
//            ->addArgument(new Reference('io.input'))
//            ->addArgument(new Reference('io.output'));

        $container->register('info', Info::class);
        $container->register('instance', Instance::class);

        $container->register('ConsoleHtmlFormatter', HtmlOutputFormatter::class);

        return $container;
    }

    /**
     * @param $name
     * @return Database|Info|TikiManagerStyle
     * @throws \Exception
     */
    public static function get($name)
    {
        return static::getContainer()->get($name);
    }
}
