<?php
declare(strict_types=1);

namespace Phlexus\Libraries\Theme;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Class ThemeInstaller
 */
class ThemeInstaller
{
    /**
     * Theme folder name
     *
     * @var string
     */
    protected $themeName;

    /**
     * Install Theme path
     *
     * @var string
     */
    protected $themePath;

    /**
     * Themes path
     *
     * @var string
     */
    protected $themesPath;

    /**
     * Assets path
     *
     * @var string
     */
    protected $assetsPath;

    /**
     * Theme zip url
     *
     * TODO: SOLID!
     *
     * @var string
     */
    protected $url;

    /**
     * ThemeManager constructor.
     *
     * @param string $url
     * @param string $themeName
     * @param string $themesPath
     * @param string $assetsPath
     */
    public function __construct(string $url, string $themeName, string $themesPath, string $assetsPath)
    {
        $this->themeName = $themeName;
        $this->themePath = $themesPath . DIRECTORY_SEPARATOR . $themeName;
        $this->themesPath = $themesPath;
        $this->assetsPath = $assetsPath;
        $this->url = $url;
    }

    /**
     * Install theme
     */
    public function install(): void
    {
        $randomName = $this->getRandomName();
        $randomZip = '/tmp/' . $randomName . '.zip';
        $randomFolder = '/tmp/' . $randomName;

        mkdir($randomFolder);

        $this->copyFromUrl($randomZip);
        $zipRoot = $this->extractZipTo($randomZip, $randomFolder);
        $randomFolder = join(DIRECTORY_SEPARATOR, [$randomFolder, $zipRoot]);

        /**
         * Copy Assets
         */
        $this->recursiveCopy(
            $randomFolder . DIRECTORY_SEPARATOR . 'assets',
            $this->assetsPath . DIRECTORY_SEPARATOR . $this->themeName
        );

        /**
         * Copy Views
         */
        $this->recursiveCopy(
            $randomFolder . DIRECTORY_SEPARATOR . 'views',
            $this->themePath
        );

        unlink($randomZip);
    }

    /**
     * Uninstall theme
     *
     * There are checks if directories exists.
     * Depending how it is removing: composer, UI, etc.
     *
     * @return void
     */
    public function uninstall(): void
    {
        $themeAssetsPath = $this->assetsPath . DIRECTORY_SEPARATOR . $this->themeName;
        if (is_dir($themeAssetsPath)) {
            $this->removeDirectory($themeAssetsPath);
        }

        if (is_dir($this->themePath)) {
            $this->removeDirectory($this->themePath);
        }
    }

    protected function recursiveCopy(string $source, string $destination): void
    {
        if (!file_exists($destination)) {
            mkdir($destination);
        }

        $directoryIterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $asset) {
            $path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            $exists = file_exists($path);

            if ($asset->isDir()) {
                // Must be inside isDir() condition
                if (!$exists) {
                    mkdir($path);
                }
            } else {
                // In case if file was updated
                if ($exists) {
                    unlink($path);
                }

                copy($asset->getPathName(), $path);
            }
        }
    }

    /**
     * Remove theme directory
     *
     * Recursive removal of install theme directory
     *
     * @param string $path
     * @return void
     */
    protected function removeDirectory(string $path): void
    {
        $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }

    /**
     * @return string
     */
    protected function getRandomName(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(10));
    }

    /**
     * @param string $filename
     * @return bool
     */
    protected function copyFromUrl(string $filename): bool
    {
        $contents = file_get_contents($this->url);

        return file_put_contents($filename, $contents) !== false;
    }

    /**
     * @param string $filename
     * @param string $destination
     * @return string
     */
    protected function extractZipTo(string $filename, string $destination): string
    {
        $zip = new ZipArchive();
        $zip->open($filename);

        $nameIndex0 = $zip->getNameIndex(0);
        $root = $nameIndex0 !== 'assets' ? $nameIndex0 : '';

        $zip->extractTo($destination);
        $zip->close();

        return $root;
    }
}
