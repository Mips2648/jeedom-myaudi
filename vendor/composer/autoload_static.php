<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit374f89a83178c24e51a61e5398c48d8d
{
    public static $prefixesPsr0 = array (
        'M' => 
        array (
            'MipsEqLogicTrait' => 
            array (
                0 => __DIR__ . '/..' . '/mips/jeedom-tools/src',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit374f89a83178c24e51a61e5398c48d8d::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit374f89a83178c24e51a61e5398c48d8d::$classMap;

        }, null, ClassLoader::class);
    }
}
