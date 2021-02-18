<?php


namespace TikiManager\Manager\WebInterface;

use TikiManager\Manager\WebInterface\Config\ClearOS;
use TikiManager\Manager\WebInterface\Config\Generic;
use TikiManager\Manager\WebInterface\Config\Linux;
use TikiManager\Manager\WebInterface\Config\Virtualmin;
use TikiManager\Style\TikiManagerStyle;

abstract class Config
{
    /**
     * @param TikiManagerStyle $io
     */
    public function showMessage(TikiManagerStyle $io):void
    {
        $io->info('Tiki Manager web administration files are located in the Tiki Manager directory. In order to
make the interface available externally, the files will be copied to a web
accessible location.');

        $io->comment(sprintf(
            'For example, if your web root is %s
* Files will be copied to %s
* Tiki Manager web administration will be accessible from:
    %s
* You must have write access in %s',
            $this->getExampleDomainDirectory(),
            $this->getExampleDataDirectory(),
            $this->getExampleURL(),
            $this->getExamplePermissionDirectory()
        ));

        $io->info('Simple authentication will be used. However, it is possible to restrict
access to the administration panel to local users (safer).');

        $io->caution('Permissions on the data folder will be changed to allow the web server to
access the files.');
    }

    /**
     * @return Generic
     */
    public static function detect(): Config
    {
        $configs = [
            Virtualmin::class,
            ClearOS::class,
            Linux::class,
            Generic::class
        ];

        foreach ($configs as $item) {
            $conf = new $item();
            if ($conf->isAvailable()) {
                return $conf;
            }
        }
    }

    /**
     * Detects if this configurations match with the system
     * @return boolean
     */
    abstract public function isAvailable(): bool;

    abstract public function getExampleDomainDirectory(): string;

    abstract public function getExampleDataDirectory(): string;

    abstract public function getExampleURL(): string;

    abstract public function getExamplePermissionDirectory(): string;

    abstract public function getUserWebRoot($webRoot): string;

    abstract public function getGroupWebRoot($webRoot): string;
}
