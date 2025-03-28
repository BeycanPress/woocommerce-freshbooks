<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit02eacec343333f27284feb82d3281d59
{
    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'BeycanPress\\WooCommerce\\FreshBooks\\' => 35,
            'BeycanPress\\FreshBooks\\' => 23,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'BeycanPress\\WooCommerce\\FreshBooks\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
        'BeycanPress\\FreshBooks\\' => 
        array (
            0 => __DIR__ . '/..' . '/beycanpress/freshbooks/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit02eacec343333f27284feb82d3281d59::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit02eacec343333f27284feb82d3281d59::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit02eacec343333f27284feb82d3281d59::$classMap;

        }, null, ClassLoader::class);
    }
}
