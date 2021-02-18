<?php


namespace TikiManager\Manager\WebInterface;

use TikiManager\Style\TikiManagerStyle;

abstract class Config
{

    /**
     * Detects if this configurations match with the system
     * @return boolean
     */
    abstract public function isAvailable();


    public function showMessage(TikiManagerStyle $io)
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

    abstract public function getExampleDomainDirectory();

    abstract public function getExampleDataDirectory();

    abstract public function getExampleURL();

    abstract public function getExamplePermissionDirectory();

    /**
     * @return GenericConfig
     */
    public static function detect(): Config
    {
        $configs = [
            VirtualminConfig::class,
            GenericConfig::class
        ];
        foreach ($configs as $item) {
            $conf = new $item();
            if ($conf->isAvailable()) {
                return $conf;
            }
        }
    }

    abstract public function getUserWebRoot($webRoot);

    abstract public function getGroupWebRoot($webRoot);
}
