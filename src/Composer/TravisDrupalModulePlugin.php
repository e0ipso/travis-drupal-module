<?php

namespace TravisDrupalModule\Composer;

use \RecursiveDirectoryIterator;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

final class TravisDrupalModulePlugin implements PluginInterface, EventSubscriberInterface {

  const PACKAGE_NAME = 'e0ipso/travis-drupal-module';
  const GLUE = "\n  * ";

  /**
   * @var Composer
   */
  private $composer;

  /**
   * @var IOInterface
   */
  private $io;

  /**
   * @var bool
   */
  private $scheduled = FALSE;

  /**
   * @var string[]
   */
  private $pathMappings;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Attach package installation events:.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'postPackageInstall',
      PackageEvents::POST_PACKAGE_UPDATE => 'postPackageUpdate',
      ScriptEvents::POST_INSTALL_CMD => 'postInstallCmd',
      ScriptEvents::POST_UPDATE_CMD => 'postUpdateCmd',
    ];
  }

  /**
   * When this package is updated, the git hook is also initialized.
   */
  public function postPackageInstall(PackageEvent $event) {
    var_dump(__METHOD__);
    $operation = $event->getOperation();
    assert($operation instanceof InstallOperation);
    $package = $operation->getPackage();

    if (!$this->isCurrentPackage($package)) {
      return;
    }

    // Schedule init when command is completed
    $this->scheduled = TRUE;
  }

  /**
   * When this package is updated, the git hook is also updated.
   */
  public function postPackageUpdate(PackageEvent $event) {
    var_dump(__METHOD__);
    $operation = $event->getOperation();
    assert($operation instanceof UpdateOperation);
    $package = $operation->getTargetPackage();

    if (!$this->isCurrentPackage($package)) {
      return;
    }

    // Schedule init when command is completed
    $this->scheduled = TRUE;
  }

  public function postInstallCmd(Event $event) {
    var_dump(__METHOD__);
    if (!$this->scheduled) {
      return;
    }
    $this->createRequiredFiles($event);
  }

  public function postUpdateCmd(Event $event) {
    var_dump(__METHOD__);
    if (!$this->scheduled) {
      return;
    }
    try {
      $this->createRequiredFiles($event);
    }
    catch (\LogicException $exception) {
      $this->io->writeError('<fg=red>' . $exception->getMessage() . '</fg=red>');
    }
  }

  private function isCurrentPackage(PackageInterface $package): bool {
    return self::PACKAGE_NAME === $package->getName();
  }

  private function createRequiredFiles(Event $event) {
    var_dump(__METHOD__);
    $fs = new Filesystem();

    $vendorDir = $this->composer->getConfig()->get('vendor-dir');
    $rootDir = Path::getDirectory($vendorDir);

    $currentPackage = $this->composer
      ->getRepositoryManager()
      ->findPackage(static::PACKAGE_NAME, "*");
    $packageDir = $this->composer->getInstallationManager()
      ->getInstallPath($currentPackage);

    $this->validateTemplateLocations($fs, $packageDir, $rootDir);
    $this->copyTemplatesToRoot($fs, $packageDir, $rootDir);
    $rootPackage = $this->composer->getPackage();
    assert($rootPackage instanceof RootPackageInterface);
    $msg = static::GLUE . implode(static::GLUE, array_values($this->computePathMappings($packageDir, $rootDir)));
    $this->io->write(sprintf(
      "\n<fg=green>%s successfully copied the following files in %s:%s</fg=green>\n",
      static::PACKAGE_NAME,
      $rootPackage->getName(),
      $msg
    ));
  }

  private function computePathMappings($packageDir, $rootDir) {
    var_dump(__METHOD__);
    if (isset($this->pathMappings)) {
      return $this->pathMappings;
    }
    $templateDir = Path::join($packageDir, 'templates');
    $iterator = new RecursiveDirectoryIterator(
      $templateDir,
      RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::CURRENT_AS_PATHNAME
    );
    $this->pathMappings = [];
    foreach ($iterator as $entry) {
      $destination = Path::join(
        $rootDir,
        Path::makeRelative($entry, $templateDir)
      );
      $this->pathMappings[$entry] = $destination;
    }
    return $this->pathMappings;
  }

  private function validateTemplateLocations(Filesystem $fs, $packageDir, $rootDir) {
    var_dump(__METHOD__);
    $files = array_values($this->computePathMappings($packageDir, $rootDir));
    $existing_files = array_filter($files, function (string $file) use ($fs) {
      return $fs->exists($file);
    });
    if (empty($existing_files)) {
      return;
    }
    $message = 'Unable to proceed the following files already exist. Back them up and delete them to re-generate them:' . static::GLUE . implode(static::GLUE, $existing_files);
    throw new \LogicException($message);
  }

  private function copyTemplatesToRoot(Filesystem $fs, $packageDir, $rootDir) {
    var_dump(__METHOD__);
    foreach ($this->computePathMappings($packageDir, $rootDir) as $source => $destination) {
      $this->copyRecursive($fs, $source, $destination);
    }
  }

  private function copyRecursive(Filesystem $fs, $source, $destination) {
    if (!is_dir($source)) {
      $fs->copy($source, $destination);
      return;
    }
    // Make the destination directory and copy all the things in there.
    $iterator = new RecursiveDirectoryIterator(
      $source,
      RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::CURRENT_AS_PATHNAME
    );
    $fs->mkdir($destination);
    foreach ($iterator as $entry) {
      $new_destination = Path::join(
        $destination,
        Path::makeRelative($entry, $source)
      );
      $this->copyRecursive($fs, $entry, $new_destination);
    }
  }

}
