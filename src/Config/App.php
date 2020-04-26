<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Config;

use TikiManager\Style\TikiManagerStyle;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

        $container
            ->register('io', TikiManagerStyle::class)
            ->addArgument(new Reference('io.input'))
            ->addArgument(new Reference('io.output'));

        return $container;
    }

    public static function get($name)
    {
        return static::getContainer()->get($name);
    }
}
