<?php

namespace Webkul\PackageGenerator\Generators;

use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Webkul\PackageGenerator\Package;

class PackageGenerator
{
    /**
     * The package vendor namespace
     *
     * @var string
     */
    protected $vendorNamespace;

    /**
     * The package name
     *
     * @var string
     */
    protected $packageName;

    /**
     * Repository object
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Filesystem object
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Package object
     *
     * @var string
     */
    protected $package;

    /**
     * @var boolean
     */
    protected $plain;

    /**
     * @var boolean
     */
    protected $force;

    /**
     * @var boolean
     */
    protected $type = 'package';

    /**
     * Contains subs files information
     *
     * @var string
     */
    protected $stubFiles = [
        'package'  => [
            'views/layouts/style'                        => 'Resources/views/layouts/style.blade.php',
            'views/index'                                => 'Resources/views/index.blade.php',
            'scaffold/menu'                              => 'Config/menu.php',
            'scaffold/acl'                               => 'Config/acl.php',
            'assets/js/app'                              => 'Resources/assets/js/app.js',
            'assets/sass/app'                            => 'Resources/assets/sass/app.scss',
            'assets/images/Icon-Temp'                    => 'Resources/assets/images/Icon-Temp.svg',
            'assets/images/Icon-Temp-Active'             => 'Resources/assets/images/Icon-Temp-Active.svg',
            'assets/publishable/css/app'                 => '../publishable/assets/css/app.css',
            'assets/publishable/js/app'                  => '../publishable/assets/js/app.js',
            'assets/publishable/images/Icon-Temp'        => '../publishable/assets/images/Icon-Temp.svg',
            'assets/publishable/images/Icon-Temp-Active' => '../publishable/assets/images/Icon-Temp-Active.svg',
            'webpack'                                    => '../webpack.mix.js',
            'package'                                    => '../package.json',
            'gitignore'                                  => '../.gitignore',
        ],
    ];

    /**
     * Contains package file paths for creation
     *
     * @var array
     */
    protected $paths = [
        'package'  => [
            'config'     => 'Config',
            'command'    => 'Console/Commands',
            'migration'  => 'Database/Migrations',
            'seeder'     => 'Database/Seeders',
            'contracts'  => 'Contracts',
            'model'      => 'Models',
            'routes'     => 'Http',
            'controller' => 'Http/Controllers',
            'filter'     => 'Http/Middleware',
            'request'    => 'Http/Requests',
            'provider'   => 'Providers',
            'repository' => 'Repositories',
            'event'      => 'Events',
            'listener'   => 'Listeners',
            'emails'     => 'Mail',
            'assets'     => 'Resources/assets',
            'lang'       => 'Resources/lang',
            'views'      => 'Resources/views',
        ],
    ];

    /**
     * The constructor.
     * 
     * @param  \Illuminate\Config\Repository  $config
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @param  \Webkul\PackageGenerator\Package  $package
     */
    public function __construct(
        Config $config,
        Filesystem $filesystem,
        Package $package
    )
    {
        $this->config = $config;

        $this->filesystem = $filesystem;

        $this->package = $package;
    }

    /**
     * Set console 
     *
     * @param  \Illuminate\Console\Command  $console
     * @return Webkul\PackageGenerator\Generators\PackageGenerator
     */
    public function setConsole($console)
    {
        $this->console = $console;

        return $this;
    }

    /**
     * Set package.
     *
     * @param  string  $packageName
     * @return Webkul\PackageGenerator\Generators\PackageGenerator
     */
    public function setPackage($packageName)
    {
        $this->packageName = $packageName;

        return $this;
    }

    /**
     * Set package plain.
     *
     * @param  string  $plain
     * @return Webkul\PackageGenerator\Generators\PackageGenerator
     */
    public function setPlain($plain)
    {
        $this->plain = $plain;

        return $this;
    }

    /**
     * Set force status.
     *
     * @param  boolean  $force
     * @return \Webkul\PackageGenerator\Generators\PackageGenerator
     */
    public function setForce($force)
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Set type status.
     *
     * @param  boolean  $isPaymentPackage
     * @return \Webkul\PackageGenerator\Generators\PackageGenerator
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Generate package
     *
     * @return void
     */
    public function generate()
    {
        if ($this->package->has($this->packageName)) {
            if ($this->force) {
                $this->package->delete($this->packageName);
            } else {
                $this->console->error("Package '{$this->packageName}' already exist !");

                return;
            }
        }

        $this->createFolders();

        if (! $this->plain) {
            $this->createFiles();

            $this->createClasses();
        }

        $this->console->info("Package '{$this->packageName}' created successfully.");
    }

    /**
     * Generate package folders
     *
     * @return void
     */
    public function createFolders()
    {
        foreach ($this->paths[$this->type] as $key => $folder) {
            $path = base_path('packages/' . $this->packageName . '/src') . '/' . $folder;

            $this->filesystem->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Generate package files
     *
     * @return void
     */
    public function createFiles()
    {
        $variables = $this->getStubVariables();

        foreach ($this->stubFiles[$this->type] as $stub => $file) {
            $path = base_path('packages/' . $this->packageName . '/src') . '/' . $file;

            if (! $this->filesystem->isDirectory($dir = dirname($path))) {
                $this->filesystem->makeDirectory($dir, 0775, true);
            }

            $this->filesystem->put($path, $this->getStubContents($stub, $variables));

            $this->console->info("Created file : {$path}");
        }
    }

    /**
     * Generate package classes
     *
     * @return void
     */
    public function createClasses()
    {
        if ($this->type == 'package') {
            $this->console->call('package:make-provider', [
                'name'    => $this->packageName . 'ServiceProvider',
                'package' => $this->packageName,
            ]);

            $this->console->call('package:make-module-provider', [
                'name'    => 'ModuleServiceProvider',
                'package' => $this->packageName,
            ]);

            $this->console->call('package:make-controller', [
                'name'    => $this->packageName . 'Controller',
                'package' => $this->packageName
            ]);

            $this->console->call('package:make-route', [
                'package' => $this->packageName
            ]);
        }
    }

    /**
     * @return array
     */
    protected function getStubVariables()
    {
        return [
            'LOWER_NAME'      => $this->getLowerName(),
            'CAPITALIZE_NAME' => $this->getCapitalizeName(),
            'PACKAGE'         => $this->getClassNamespace($this->packageName),
            'CLASS'           => $this->getClassName(),
        ];
    }

    /**
     * @return string
     */
    protected function getClassName()
    {
        return class_basename($this->packageName);
    }

    /**
     * @param  string  $name
     * @return string
     */
    protected function getClassNamespace($name)
    {
        return str_replace('/', '\\', $name);
    }

    /**
     * Returns content of stub file
     *
     * @param  string  $stub
     * @param  array  $variables
     * @return string
     */
    public function getStubContents($stub, $variables = [])
    {
        $path = __DIR__ . '/../stubs/' . $stub . '.stub';

        $contents = file_get_contents($path);

        foreach ($variables as $search => $replace) {
            $contents = str_replace('$' . strtoupper($search) . '$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * @return string
     */
    protected function getCapitalizeName()
    {
        return ucwords(class_basename($this->packageName));
    }

    /**
     * @return string
     */
    protected function getLowerName()
    {
        return strtolower(class_basename($this->packageName));
    }
}