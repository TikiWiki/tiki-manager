<?php

namespace TikiManager\Tests\Application\Tiki\Versions;

use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use PHPUnit\Framework\TestCase;
use TikiManager\Config\Environment;

class TikiRequirementsHelperTest extends TestCase
{
    /**
     * @covers \TikiManager\Application\Tiki\Versions\TikiRequirementsHelper::findByBranchName
     * @dataProvider providerForTestFindByBranchName
     * @return void
     */
    public function testFindByBranchName(string $message, string $branch, TikiRequirementsHelper $helper, string $expectedVersion): void
    {
        $requirement = $helper->findByBranchName($branch);
        $this->assertEquals($expectedVersion, $requirement->getVersion(), $message);
    }

    public function providerForTestFindByBranchName(): array
    {
        $fixturesFolder = Environment::get('TRIM_ROOT') . DS . 'tests/' . DS . 'fixtures' . DS . 'TikiRequirementsYaml';

        $helperOnlyNumeric = new TikiRequirementsHelper(new YamlFetcher($fixturesFolder . DS . 'only_numeric_versions.yml'));
        $helperWithMaster = new TikiRequirementsHelper(new YamlFetcher($fixturesFolder . DS . 'numeric_and_master.yml'));
        $helperWithMinor = new TikiRequirementsHelper(new YamlFetcher($fixturesFolder . DS . 'numeric_with_minor.yml'));
        $helperShuffled = new TikiRequirementsHelper(new YamlFetcher($fixturesFolder . DS . 'versions_out_of_order.yml'));

        return [
            // check when the file does not have master
            ['Test 1.1', '22.x', $helperOnlyNumeric, '22'], // branch version that exists
            ['Test 1.2', '22.1', $helperOnlyNumeric, '22'], // tag for version that exists
            ['Test 1.3', 'master', $helperOnlyNumeric, '26'], // git: master (that does not exist)
            ['Test 1.4', 'trunk', $helperOnlyNumeric, '26'], // svn: trunk (that does not exist)
            ['Test 1.5', '999999999999999.x', $helperOnlyNumeric, '26'], // branch version newer that all in the file
            ['Test 1.6', '999999999999999.9', $helperOnlyNumeric, '26'], // tag version newer that all in the file
            ['Test 1.7', '1.x', $helperOnlyNumeric, '12'], // branch version older that all in the file
            ['Test 1.8', '1.1', $helperOnlyNumeric, '12'], // tag version older that all in the file
            ['Test 1.9', 'some-branch', $helperOnlyNumeric, '26'], // any other branch name

            // check when the file has master
            ['Test 2.1', '22.x', $helperWithMaster, '22'], // branch version that exists
            ['Test 2.2', '22.1', $helperWithMaster, '22'], // tag for version that exists
            ['Test 2.3', 'master', $helperWithMaster, 'master'], // git: master (that does not exist)
            ['Test 2.4', 'trunk', $helperWithMaster, 'master'], // svn: trunk (that does not exist)
            ['Test 2.5', '999999999999999.x', $helperWithMaster, '26'], // branch version newer that all in the file
            ['Test 2.6', '999999999999999.9', $helperWithMaster, '26'], // tag version newer that all in the file
            ['Test 2.7', '1.x', $helperWithMaster, '12'], // branch version older that all in the file
            ['Test 2.8', '1.1', $helperWithMaster, '12'], // tag version older that all in the file
            ['Test 2.9', 'some-branch', $helperOnlyNumeric, '26'], // any other branch name

            // check when the file has minor versions
            ['Test 3.1', '22.x', $helperWithMinor, '22'], // branch version that exists
            ['Test 3.2', '22.1', $helperWithMinor, '22.1'], // tag for version that exists
            ['Test 3.3', 'master', $helperWithMinor, '26'], // git: master (that does not exist)
            ['Test 3.4', 'trunk', $helperWithMinor, '26'], // svn: trunk (that does not exist)
            ['Test 3.5', '999999999999999.x', $helperWithMinor, '26'], // branch version newer that all in the file
            ['Test 3.6', '999999999999999.9', $helperWithMinor, '26'], // tag version newer that all in the file
            ['Test 3.7', '1.x', $helperWithMinor, '12'], // branch version older that all in the file
            ['Test 3.8', '1.1', $helperWithMinor, '12'], // tag version older that all in the file
            ['Test 3.9', 'some-branch', $helperOnlyNumeric, '26'], // any other branch name

            // When the requirements file is shuffled
            ['Test 4.1', '22.x', $helperShuffled, '22'], // branch version that exists
            ['Test 4.2', '22.1', $helperShuffled, '22'], // tag for version that exists
            ['Test 4.3', 'master', $helperShuffled, 'master'], // git: master (that does not exist)
            ['Test 4.4', 'trunk', $helperShuffled, 'master'], // svn: trunk (that does not exist)
            ['Test 4.5', '999999999999999.x', $helperShuffled, '26'], // branch version newer that all in the file
            ['Test 4.6', '999999999999999.9', $helperShuffled, '26'], // tag version newer that all in the file
            ['Test 4.7', '1.x', $helperShuffled, '12'], // branch version older that all in the file
            ['Test 4.8', '1.1', $helperShuffled, '12'], // tag version older that all in the file
            ['Test 4.9', 'some-branch', $helperOnlyNumeric, '26'], // any other branch name
        ];
    }
}
