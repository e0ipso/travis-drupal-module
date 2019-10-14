<?php

/**
 * Implements a composer plugin.
 * php version 7.1
 *
 * @file     Composer plugin implementation file.
 * @category Composer
 * @package  TravisDrupalModule
 * @author   Mateu Aguiló Bosch (e0ipso) <mateu@mateuaguilo.com>
 * @license  https://choosealicense.com/licenses/gpl-2.0/ GNU/GPL-v2
 * @link     https://mateuaguilo.com
 */

namespace TravisDrupalModule\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use \LogicException;
use \RecursiveDirectoryIterator;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Composer plugin that copies the templates to their right location.
 *
 * @category Composer
 * @package  TravisDrupalModule
 * @author   Mateu Aguiló Bosch (e0ipso) <mateu@mateuaguilo.com>
 * @license  https://choosealicense.com/licenses/gpl-2.0/ GNU/GPL-v2
 * @link     https://mateuaguilo.com
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final class TravisDrupalModulePlugin implements
  PluginInterface,
  EventSubscriberInterface
{

    const PACKAGE_NAME = 'e0ipso/travis-drupal-module';

    const GLUE = "\n  * ";

    /**
     * The composer API.
     *
     * @var Composer
     */
    private $_composer;

    /**
     * IO interface to log messages to the terminal.
     *
     * @var IOInterface
     */
    private $_io;

    /**
     * Keeps state of weather or not the operations are scheduled.
     *
     * @var bool
     */
    private $_scheduled = false;

    /**
     * Key value store for path mappings between origin and destination paths.
     *
     * @var string[]
     */
    private $_pathMappings;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer The composer API.
     * @param IOInterface $theIo    IO interface to log to the terminal.
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $theIo)
    {
        $this->_composer = $composer;
        $this->_io = $theIo;
    }

    /**
     * Attach package installation events:.
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
        PackageEvents::POST_PACKAGE_INSTALL => 'postChangeInstall',
        PackageEvents::POST_PACKAGE_UPDATE => 'postChangeInstall',
        ScriptEvents::POST_INSTALL_CMD => 'postChangeCmd',
        ScriptEvents::POST_UPDATE_CMD => 'postChangeCmd',
        ];
    }

    /**
     * When this package is changed, the files are also initialized.
     *
     * @param \Composer\Installer\PackageEvent $event The composer event.
     *
     * @return void
     */
    public function postChangeInstall(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $package = $event instanceof UpdateOperation
            ? $operation->getInitialPackage()
            : $operation->getPackage();

        if (!$this->_isCurrentPackage($package)) {
            return;
        }

        // Schedule init when command is completed
        $this->_scheduled = true;
    }

    /**
     * After running a command on this app we may need to initialize.
     *
     * @return void
     */
    public function postChangeCmd()
    {
        if (!$this->_scheduled) {
            return;
        }
        $this->_createRequiredFiles();
    }

    /**
     * Checks if the provided package is the current package.
     *
     * @param \Composer\Package\PackageInterface $package The package object.
     *
     * @return bool
     *   TRUE if the package is the current one.
     */
    private function _isCurrentPackage(PackageInterface $package): bool
    {
        return self::PACKAGE_NAME === $package->getName();
    }

    /**
     * Creates the necessary files from the templates directory.
     *
     * @return void
     */
    private function _createRequiredFiles()
    {
        $fsystem = new Filesystem();

        $vendorDir = $this->_composer->getConfig()->get('vendor-dir');
        $rootDir = Path::getDirectory($vendorDir);

        $currentPackage = $this->_composer
            ->getRepositoryManager()
            ->findPackage(static::PACKAGE_NAME, "*");
        $packageDir = $this->_composer->getInstallationManager()
            ->getInstallPath($currentPackage);

        // The GrumPHP file is generated on phppro/grumphp installation, we don't
        // want that one. We want our own version.
        $grumphpFilename = Path::join($rootDir, 'grumphp.yml');
        if ($fsystem->exists($grumphpFilename)) {
            $fsystem->remove($grumphpFilename);
            $message = 'Preexisting grumphp.yml file has been replaced.';
            $this->_io->write('<fg=yellow>' . $message . '</fg=yellow>');
        }
        $this->_copyTemplatesToRoot($fsystem, $packageDir, $rootDir);

        // Leave a message of what we have done.
        $rootPackage = $this->_composer->getPackage();
        assert($rootPackage instanceof RootPackageInterface);
        $msg = static::GLUE . implode(
            static::GLUE, array_values(
                $this->_computePathMappings($packageDir, $rootDir)
            )
        );
        $tpl  = "\n<fg=green>%s successfully copied the following files in ";
        $tpl .= "%s:%s</fg=green>\n";
        $this->_io->write(
            sprintf(
                $tpl,
                static::PACKAGE_NAME,
                $rootPackage->getName(),
                $msg
            )
        );
    }

    /**
     * Given a source dir and a destination create the mappings of files to copy.
     *
     * @param string $packageDir The absolute path for this package.
     * @param string $rootDir    The absolute path for the root directory.
     *
     * @return string[]
     *   The keys are the origin paths and the values the destination paths.
     */
    private function _computePathMappings($packageDir, $rootDir)
    {
        if (isset($this->_pathMappings)) {
            return $this->_pathMappings;
        }
        $templateDir = Path::join($packageDir, 'templates');
        $flags = RecursiveDirectoryIterator::SKIP_DOTS
          | RecursiveDirectoryIterator::CURRENT_AS_PATHNAME;
        $iterator = new RecursiveDirectoryIterator(
            $templateDir,
            $flags
        );
        $this->_pathMappings = [];
        foreach ($iterator as $entry) {
            $destination = Path::join(
                $rootDir,
                Path::makeRelative($entry, $templateDir)
            );
            $this->_pathMappings[$entry] = $destination;
        }
        return $this->_pathMappings;
    }

    /**
     * Ensures that there are no conflicts when copying files.
     *
     * @param Filesystem $fsystem    The filesystem component.
     * @param string     $packageDir The absolute path for the this package.
     * @param string     $rootDir    The absolute path for the root directory.
     *
     * @return string[]
     *   Files to copy.
     */
    private function _getValidTemplateLocations(
        Filesystem $fsystem,
        $packageDir,
        $rootDir
    ) {
        $files = array_values($this->_computePathMappings($packageDir, $rootDir));
        $existing_files = array_filter(
            $files, function (string $file) use ($fsystem) {
                return $fsystem->exists($file);
            }
        );
        if (empty($existing_files)) {
            return $files;
        }
        $message = 'Some files already exist and will not be overwritten:';
        $message .= static::GLUE . implode(static::GLUE, $existing_files);
        $this->_io->write('<fg=yellow>' . $message . '</fg=yellow>');
        return array_diff($files, $existing_files);
    }

    /**
     * Copies all files from the templates folder to the root directory.
     *
     * @param Filesystem $fsystem    The filesystem component.
     * @param string     $packageDir The absolute path for the this package.
     * @param string     $rootDir    The absolute path for the root directory.
     *
     * @return void
     */
    private function _copyTemplatesToRoot(Filesystem $fsystem, $packageDir, $rootDir)
    {
        $mappings = $this->_getValidTemplateLocations(
            $fsystem,
            $packageDir,
            $rootDir
        );
        foreach ($mappings as $source => $destination) {
            $this->_copyRecursive($fsystem, $source, $destination);
        }
    }

    /**
     * Copies a source to a destination.
     *
     * If the source is a directory, then everything in there is copied as well.
     *
     * @param Filesystem $fsystem     The filesystem component.
     * @param string     $source      The absolute path for the source.
     * @param string     $destination The absolute path for the destination.
     *
     * @return void
     */
    private function _copyRecursive(Filesystem $fsystem, $source, $destination)
    {
        if (!is_dir($source)) {
            $fsystem->copy($source, $destination);
            return;
        }
        // Make the destination directory and copy all the things in there.
        $flags = RecursiveDirectoryIterator::SKIP_DOTS
        | RecursiveDirectoryIterator::CURRENT_AS_PATHNAME;
        $iterator = new RecursiveDirectoryIterator(
            $source,
            $flags
        );
        $fsystem->mkdir($destination);
        foreach ($iterator as $entry) {
            $new_destination = Path::join(
                $destination,
                Path::makeRelative($entry, $source)
            );
            $this->_copyRecursive($fsystem, $entry, $new_destination);
        }
    }

}
