<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8a26274d79cbda5d133856cf2e02ccaa
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Masterpuffin\\Sql\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Masterpuffin\\Sql\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8a26274d79cbda5d133856cf2e02ccaa::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8a26274d79cbda5d133856cf2e02ccaa::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8a26274d79cbda5d133856cf2e02ccaa::$classMap;

        }, null, ClassLoader::class);
    }
}
