<?php

namespace TikiManager\Command\Traits;

use TikiManager\Application\Instance;
use TikiManager\Application\Version;
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
    public function getUpgradeVersion(Instance $instance, bool $onlySupported, ?string $branch = null, ?Version $curVersion = null): ?Version
    {
        $curVersion = $curVersion ?: $instance->getLatestVersion();
        if ($instance->getApplication()) {
            $versions = $instance->getApplication()->getUpgradableVersions($curVersion, $onlySupported);
        } else {
            $versions = $instance->getCompatibleVersions(false);
        }

        if (empty($versions)) {
            $message = 'No upgrades are available. This is likely because you are already at the latest version';
            $message .= $onlySupported ? ' supported by the server.' : '.';

            $this->io->writeln('<comment>' . $message . '</comment>');

            return null;
        }

        $versionsMap = [];
        foreach ($versions as $curVersion) {
            $versionsMap[(string) $curVersion] = $curVersion;
        }

        $default = null;
        if ($branch) {
            $vcs = $instance->vcs_type;
            if (empty($vcs)) {
                foreach ($versions as $curVersion) {
                    if (strstr((string) $curVersion, $branch)) {
                        $vcs = $curVersion->getType();
                        break;
                    }
                }
            }
            $branch = VersionControl::formatBranch($branch, $vcs);
            $default = Version::buildFake($vcs, $branch);
        }

        if ($default == null) {
            $choice = $this->io->choice('Which version do you want to upgrade to', array_keys($versionsMap), (string) $default);
            return $versionsMap[$choice];
        }

        return $default;
    }

    public function validateUpgradeVersion(Instance $instance, bool $onlySupported, string $branch, Version $version): bool
    {
        if ($instance->getApplication()) {
            $versions = $instance->getApplication()->getUpgradableVersions($version, $onlySupported);
        } else {
            $versions = $instance->getCompatibleVersions(false);
        }

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
