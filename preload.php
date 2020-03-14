<?php

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Class Preloader
 *
 * Taken from (and modified):
 * @see https://stitcher.io/blog/preloading-in-php-74
 */
class Preloader
{
    private array $ignoredClasses = [];

    private static int $count = 0;

    private array $paths;

    private array $fileMap;

    private array $ignoredPaths = [];

    public function __construct(string ...$paths)
    {
        $this->paths = $paths;

        // We'll use composer's classmap
        // to easily find which classes to autoload,
        // based on their filename
        $classMap = require __DIR__ . '/vendor/composer/autoload_classmap.php';

        $this->fileMap = array_flip($classMap);
    }

    public function paths(string ...$paths): Preloader
    {
        $this->paths = array_merge(
            $this->paths,
            $paths
        );

        return $this;
    }

    public function ignoreClasses(string ...$names): Preloader
    {
        $this->ignoredClasses = array_merge(
            $this->ignoredClasses,
            $names
        );

        return $this;
    }

    public function ignorePaths(string ...$paths): Preloader
    {
        $this->ignoredPaths = array_merge(
            $this->ignoredPaths,
            $paths
        );

        return $this;
    }

    public function load(): void
    {
        // We'll loop over all registered paths
        // and load them one by one
        foreach ($this->paths as $path) {
            $this->loadPath(rtrim($path, '/'));
        }

        $count = self::$count;

        echo "[Preloader] Preloaded {$count} classes" . PHP_EOL;
    }

    private function loadPath(string $path): void
    {
        // If the current path is a directory,
        // we'll load all files in it
        if (is_dir($path)) {
            $this->loadDir($path);

            return;
        }

        // Otherwise we'll just load this one file
        $this->loadFile($path);
    }

    private function loadDir(string $path): void
    {
        $handle = opendir($path);

        // We'll loop over all files and directories
        // in the current path,
        // and load them one by one
        while ($file = readdir($handle)) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $this->loadPath("{$path}/{$file}");
        }

        closedir($handle);
    }

    private function loadFile(string $path): void
    {
        // We resolve the classname from composer's autoload mapping
        $class = $this->fileMap[$path] ?? null;

        // And use it to make sure the class, or the file pattern, shouldn't be ignored
        if ($this->shouldIgnoreClass($class) || $this->shouldIgnorePath($path)) {
            return;
        }

        // Finally we require the path,
        // causing all its dependencies to be loaded as well
        require_once($path);

        self::$count++;

        echo "[Preloader] Preloaded `{$class}`" . PHP_EOL;
    }

    private function shouldIgnoreClass(?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        foreach ($this->ignoredClasses as $ignore) {
            if (strpos($name, $ignore) === 0) {
                return true;
            }
        }

        return false;
    }

    private function shouldIgnorePath(string $path): bool
    {
        foreach ($this->ignoredPaths as $ignoredPath) {
            $ignoredPath = preg_quote($ignoredPath, '/');
            if (preg_match("/$ignoredPath/", $path) === 1) {
                return true;
            }
        }

        return false;
    }
}

// we ignore the testing path since we composer install --no-dev which means phpunit isn't in our vendor dir
(new Preloader())
    ->paths(
        __DIR__ . '/vendor/laravel/framework',
        __DIR__ . '/app/Providers',
        __DIR__ . '/app/Http/Middleware',
        __DIR__ . '/app/Http/Controllers'
    )
    ->ignorePaths('Illuminate/Foundation/Testing')
    ->ignoreClasses(
        \Illuminate\Filesystem\Cache::class,
        \Illuminate\Log\LogManager::class,
        \Illuminate\Http\Testing\File::class,
        \Illuminate\Http\UploadedFile::class,
        \Illuminate\Support\Carbon::class
    )
    ->load();
