<?php

namespace TikiManager\Command\Traits;

use TikiManager\Application\Instance;
use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\VersionControl;

trait InstanceUpgrade
{
    /**
     * Get version to update instance to
     *
     * @param Instance $instance
     * @param bool $onlySupported
     * @param string|null $branch
     * @param Version|null $curVersion The current version (from source instance, when cloning)
     * @return Version|null
     */
    public function getUpgradeVersion(Instance $instance, bool $onlySupported, string $branch = null, Version $curVersion = null): ?Version
    {
        $curVersion = $curVersion ?: $instance->getLatestVersion();
        $versions = $instance->getApplication()->getUpgradableVersions($curVersion, $onlySupported);

        if (empty($versions)) {
            $message = 'No upgrades are available. This is likely because you are already at the latest version';
            $message .= $onlySupported ? ' supported by the server.' : '.';

            $this->io->writeln('<comment>' . $message . '</comment>');

            return null;
        }

        $default = null;
        if ($branch) {
            $vcs = $instance->vcs_type;
            $branch = VersionControl::formatBranch($branch, $vcs);
            $default = Version::buildFake($vcs, $branch);
        }

        $versionsMap = [];
        foreach ($versions as $curVersion) {
            $versionsMap[(string) $curVersion] = $curVersion;
        }

        $choice = $this->io->choice('Which version do you want to upgrade to', array_keys($versionsMap), (string) $default);

        return $versionsMap[$choice];
    }

    public function validateUpgradeVersion(Instance $instance, bool $onlySupported, string $branch, Version $version): bool
    {
        $versions = $instance->getApplication()->getUpgradableVersions($version, $onlySupported);

        $vcs = $instance->vcs_type;
        $branch = VersionControl::formatBranch($branch, $vcs);

        $default = Version::buildFake($vcs, $branch);

        foreach ($versions as $version) {
            if ((string) $version === (string) $default) {
                return true;
            }
        }

        return false;
    }
}
