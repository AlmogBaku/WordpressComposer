<?php

namespace AlmogBaku\WordpressComposer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    /**
     * Composer plugin default behaviour
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Subscribe to package changed events
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate'
        ];
    }

    /**
     * Run this command as post-install-package
     * @param PackageEvent $event - Composer automatically tells information about itself for custom scripts
     */
    public function onPackageInstall(PackageEvent $event)
    {
        //Get information about the package that was just installed
        $package = $event->getOperation()->getPackage();

        $this->processInstallation($package);
    }

    /**
     * Run this command as post-install-package
     * @param PackageEvent $event - Composer automatically tells information about itself for custom scripts
     */
    public function onPackageUpdate(PackageEvent $event)
    {
        //TODO: Keep record of moved files and delete them on updates and in package deletion
        //$package = $event->getOperation()->getInitialPackage(); //Do something for these.
        //Maybe symlinking/copying files would be better than moving.

        $package = $event->getOperation()->getTargetPackage();

        //For now just Ignore what happend earlier and assume that new files will replace earlier
        $this->processInstallation($package);
    }

    public function processInstallation(PackageInterface $package)
    {
        if ($package->getType() != "wordpress-core") return;

        $installationDir = false;
        $prettyName = $package->getPrettyName();
        if ($this->composer->getPackage()) {
            $topExtra = $this->composer->getPackage()->getExtra();
            if (!empty($topExtra['wordpress-install-dir'])) {
                $installationDir = $topExtra['wordpress-install-dir'];
                if (is_array($installationDir)) {
                    $installationDir = empty($installationDir[$prettyName]) ? false : $installationDir[$prettyName];
                }
            }
        }
        if (!$installationDir) {
            $installationDir = 'wordpress';
        }

        $htfile = getcwd() . "/" . $installationDir . "/.htaccess";
        if (!file_exists($htfile)) {
            file_put_contents($htfile, $this->getHtaccess());
        }
    }

    private function getHtaccess()
    {
        return <<<HTACCESS
# BEGIN WordPress
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS;
    }
}