<?php

namespace PajuranCodes\Configuration;

use function is_dir;
use function is_string;
use function file_exists;
use function array_merge;
use Symfony\Component\Finder\Finder;

/**
 * A component for finding configuration files
 * in a list of configuration directories.
 * 
 * The filenames of the searched files are filtered by certain patterns.
 * 
 * Dependent on the provided active application environment, either all 
 * files found in the directories list are returned, or just the ones 
 * found in the subdirectories named after the active environment.
 * 
 * This class uses Symfony's Finder component.
 * 
 * @link https://symfony.com/doc/current/components/finder.html The Finder Component
 * 
 * @author pajurancodes
 */
class FilesFinder {

    /**
     * A list of configuration directories.
     * 
     * @var string[]
     */
    private readonly array $configDirs;

    /**
     * A list of allowed application environments.
     * 
     * It contains an associative or an indexed array.
     * 
     *  [
     *      'production',
     *      'development',
     *      'test',
     *      '...'
     *  ]
     * 
     * @var string[]
     */
    private readonly array $allowedEnvironments;

    /**
     * An active application environment (production|development|...).
     * 
     * @var string
     */
    private readonly string $activeEnvironment;

    /**
     * A list of filenames used for searching matched configuration files.
     * 
     * It contains an array (associative or indexed) 
     * of patterns (regexp, globs, or strings).
     * 
     *  [
     *      '*.php',
     *      '/\.php$/',
     *      'test.php',
     *      '...'
     *  ]
     * 
     * @var string[]
     */
    private readonly array $filenamesFilter;

    /**
     * A list of configuration files found 
     * in the configuration directories.
     * 
     * @var string[]
     */
    private array $configFiles;

    /**
     * 
     * @param string|string[] $configDirs A list of configuration directories 
     * (a relative path, an absolute path, or an array of paths).
     * @param string[] $allowedEnvironments (optional) A list of allowed application environments.
     * It can be an associative or an indexed array.
     * @param string $activeEnvironment (optional) An active application environment (production|development|...).
     * @param string|string[] $filenamesFilter (optional) A list of filenames used for searching matched 
     * configuration files. It can be a pattern (a regexp, a glob, or a string) or an array of patterns.
     */
    public function __construct(
        string|array $configDirs,
        array $allowedEnvironments = [],
        string $activeEnvironment = '',
        string|array $filenamesFilter = '*.php'
    ) {
        $this->configDirs = $this->buildConfigDirs($configDirs);
        $this->allowedEnvironments = $this->buildAllowedEnvironments($allowedEnvironments);
        $this->activeEnvironment = $this->buildActiveEnvironment($activeEnvironment);
        $this->filenamesFilter = $this->buildFilenamesFilter($filenamesFilter);
    }

    /**
     * Find configuration files in the provided configuration directories.
     * 
     * @return string[] The list of configuration files
     * found in the configuration directories.
     */
    public function findConfigFiles(): array {
        if (isset($this->configFiles)) {
            return $this->configFiles;
        }

        $this->configFiles = [];

        foreach ($this->configDirs as $configDir) {
            $this->configFiles = empty($this->activeEnvironment) ?
                array_merge(
                    $this->configFiles,
                    $this->findAllFiles($configDir)
                ) :
                array_merge(
                    $this->configFiles,
                    $this->findFilesExceptInSubdirsNamedAfterAllowedEnvironments($configDir),
                    $this->findFilesInSubdirsNamedAfterActiveEnvironment($configDir)
            );
        }

        return $this->configFiles;
    }

    /**
     * Find ALL configuration files in the provided configuration directory.
     * 
     * @param string $configDir A configuration directory.
     * @return string[] The list of found configuration files.
     */
    private function findAllFiles(string $configDir): array {
        $configFiles = [];

        $finder = new Finder();

        $finder->files()->in($configDir)->name($this->filenamesFilter);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $configFiles[] = $file->getRealPath();
            }
        }

        return $configFiles;
    }

    /**
     * Find all configuration files in the provided configuration directory, 
     * EXCEPTING the subdirectories whose names are defined in the list of 
     * allowed application environments.
     * 
     * @param string $configDir A configuration directory.
     * @return string[] The list of found configuration files.
     */
    private function findFilesExceptInSubdirsNamedAfterAllowedEnvironments(
        string $configDir
    ): array {
        $configFiles = [];

        $finder = new Finder();

        $finder->files()->in($configDir)->exclude($this->allowedEnvironments)->name($this->filenamesFilter);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $configFiles[] = $file->getRealPath();
            }
        }

        return $configFiles;
    }

    /**
     * Find all configuration files in the provided configuration directory, 
     * RESTRICTED to the subdirectories named after the active environment.
     * 
     * These configuration files MUST be loaded after all other
     * files are loaded, because their configuration settings 
     * MUST overwrite the settings found in the other files.
     * 
     * This method uses a regex pattern for matching the 
     * directory names. It is built from two capturing groups:
     * 
     *  - "(^[\/]*trial\/)": matches "dev/" or "/dev/" at the beginning of the path.
     *  - "(\/trial\/)+":    matches "/dev/" at least once, at any position in the path.
     * 
     * @param string $configDir A configuration directory.
     * @return string[] The list of found configuration files.
     */
    private function findFilesInSubdirsNamedAfterActiveEnvironment(
        string $configDir
    ): array {
        $configFiles = [];
        $subdirsPattern = '/(^[\/]*' . $this->activeEnvironment . '\/)|(\/' . $this->activeEnvironment . '\/)+/';

        $finder = new Finder();

        $finder->files()->in($configDir)->path($subdirsPattern)->name($this->filenamesFilter);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $configFiles[] = $file->getRealPath();
            }
        }

        return $configFiles;
    }

    /**
     * Build the list of configuration directories.
     *
     * @param string|string[] $configDirs A list of configuration directories 
     * (a relative path, an absolute path, or an array of paths).
     * @return string[] The list of configuration directories.
     * @throws \InvalidArgumentException The list of configuration directories is empty.
     * @throws \UnexpectedValueException An empty value in the list of configuration directories.
     * @throws \UnexpectedValueException One of the provided directories is not a string.
     * @throws \UnexpectedValueException One of the provided directories points to a non-existent location.
     * @throws \UnexpectedValueException One of the provided directories is not a directory, but a file.
     */
    private function buildConfigDirs(string|array $configDirs): array {
        /*
         * Validate the list of configuration directories.
         */
        if (empty($configDirs)) {
            throw new \InvalidArgumentException(
                    'A list of configuration directories must be provided.'
            );
        }

        if (is_string($configDirs)) {
            $configDirs = [$configDirs];
        }

        /*
         * Validate each directory in the list of configuration directories.
         */
        foreach ($configDirs as $key => $configDir) {
            if (empty($configDir)) {
                throw new \UnexpectedValueException(
                        'A directory must be provided at the key "' . $key . '" '
                        . 'of the configuration directories list.'
                );
            }

            if (!is_string($configDir)) {
                throw new \UnexpectedValueException(
                        'The directory at the key "' . $key . '" of the '
                        . 'configuration directories list must be a string.'
                );
            }

            if (!file_exists($configDir)) {
                throw new \UnexpectedValueException(
                        'The directory "' . $configDir . '" at the key "' . $key . '" of the '
                        . 'configuration directories list points to a non-existent location.'
                );
            }

            if (!is_dir($configDir)) {
                throw new \UnexpectedValueException(
                        '"' . $configDir . '" is not a directory, but a file.'
                );
            }
        }

        return $configDirs;
    }

    /**
     * Build the list of allowed application environments.
     *
     * @param string[] $allowedEnvironments A list of allowed application environments.
     * @return string[] The list of allowed application environments.
     * @throws \UnexpectedValueException One of the provided application environments is empty.
     * @throws \UnexpectedValueException One of the provided application environments is not a string.
     */
    private function buildAllowedEnvironments(array $allowedEnvironments): array {
        foreach ($allowedEnvironments as $key => $allowedEnvironment) {
            if (empty($allowedEnvironment)) {
                throw new \UnexpectedValueException(
                        'An application environment must be provided at the key "' . $key . '" '
                        . 'of the list of allowed application environments.'
                );
            }

            if (!is_string($allowedEnvironment)) {
                throw new \UnexpectedValueException(
                        'The application environment at the key "' . $key . '" of the '
                        . 'list of allowed application environments must be a string.'
                );
            }
        }

        return $allowedEnvironments;
    }

    /**
     * Build the active application environment.
     * 
     * @param string $activeEnvironment An active application environment.
     * @return string The active application environment.
     * @throws \UnexpectedValueException The provided application environment is empty, even if a 
     * list of allowed application environments was already provided.
     * @throws \UnexpectedValueException The provided application environment could not be found in 
     * the list of allowed application environments.
     */
    private function buildActiveEnvironment(string $activeEnvironment): string {
        if (empty($activeEnvironment)) {
            if ($this->allowedEnvironments) {
                throw new \UnexpectedValueException(
                        'If the list of allowed application environments is not empty, '
                        . 'then an active application environment must be provided.'
                );
            }
        } else {
            if (!$this->activeEnvironmentExistsInAllowedEnvironments($activeEnvironment)) {
                throw new \UnexpectedValueException(
                        'The active application environment "' . $activeEnvironment . '" could '
                        . 'not be found in the list of allowed application environments.'
                );
            }
        }

        return $activeEnvironment;
    }

    /**
     * Check if the provided active application environment 
     * exists in the list of allowed application environments.
     * 
     * @param string $activeEnvironment An active application environment.
     * @return bool True if the active application environment is found, or false otherwise.
     */
    private function activeEnvironmentExistsInAllowedEnvironments(string $activeEnvironment): bool {
        $found = false;

        foreach ($this->allowedEnvironments as $allowedEnvironment) {
            if ($allowedEnvironment === $activeEnvironment) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * Build the list of filenames used for searching matched configuration files.
     * 
     * @param string|string[] $filenamesFilter A list of filenames used for searching 
     * matched configuration files.
     * @return string[] The list of filenames used for searching matched configuration files.
     * @throws \UnexpectedValueException One of the provided filenames is empty.
     * @throws \UnexpectedValueException One of the provided filenames is not a string.
     */
    private function buildFilenamesFilter(string|array $filenamesFilter): array {
        /*
         * Validate the list of filenames.
         */
        if (empty($filenamesFilter)) {
            return [];
        }

        if (is_string($filenamesFilter)) {
            $filenamesFilter = [$filenamesFilter];
        }

        /*
         * Validate each filename in the filenames list.
         */
        foreach ($filenamesFilter as $key => $filename) {
            if (empty($filename)) {
                throw new \UnexpectedValueException(
                        'A filename must be provided at the key "' . $key . '" of the list '
                        . 'of filenames used for searching matched configuration files.'
                );
            }

            if (!is_string($filename)) {
                throw new \UnexpectedValueException(
                        'The filename at the key "' . $key . '" of the list of filenames '
                        . 'used for searching matched configuration files must be a string.'
                );
            }
        }

        return $filenamesFilter;
    }

    /**
     * Get the list of configuration directories.
     *
     * @return string[]
     */
    public function getConfigDirs(): array {
        return $this->configDirs;
    }

    /**
     * Get the list of allowed application environments.
     * 
     * @return string[]
     */
    public function getAllowedEnvironments(): array {
        return $this->allowedEnvironments;
    }

    /**
     * Get the active application environment.
     * 
     * @return string
     */
    public function getActiveEnvironment(): string {
        return $this->activeEnvironment;
    }

    /**
     * Get the list of filenames used for searching matched configuration files.
     * 
     * @return string[]
     */
    public function getFilenamesFilter(): array {
        return $this->filenamesFilter;
    }

}
