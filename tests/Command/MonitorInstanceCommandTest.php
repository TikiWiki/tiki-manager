<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use TikiManager\Application\Instance;
use TikiManager\Command\MonitorInstanceCommand;
use TikiManager\Tests\Helpers\Tests;

class MonitorInstanceCommandTest extends TestCase
{
    protected static $updater;

    public static function setUpBeforeClass(): void
    {
        static::$updater = new MonitorInstanceCommand();
    }

    public function testMonitorInstances()
    {
        $mockInstance1 = $this->createMock(Instance::class);
        $mockInstance1->method('getId')->willReturn(1);
        $mockInstance1->last_action = 'update';
        $mockInstance1->state = 'success';

        $mockInstance2 = $this->createMock(Instance::class);
        $mockInstance2->method('getId')->willReturn(2);
        $mockInstance2->last_action = 'backup';
        $mockInstance2->state = 'failure';

        $results = Tests::invokeMethod(static::$updater, 'monitorInstances', [[$mockInstance1, $mockInstance2]]);

        list($instanceResults, $hasFailures) = $results;

        $this->assertCount(2, $instanceResults);
        $this->assertTrue($hasFailures);
        $this->assertEquals('success', $instanceResults[0]['Result']);
        $this->assertEquals('failure', $instanceResults[1]['Result']);
    }
}
