<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_AutoloaderFactoryClassMapLoaderTest extends TestCase
{
    /**
     * @var array
     */
    protected $_loaders;

    /**
     * @var string
     */
    protected $_includePath;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->_loaders = spl_autoload_functions();
        if (!is_array($this->_loaders)) {
            $this->_loaders = array();
        }

        // Store original include_path
        $this->_includePath = get_include_path();
    }

    public function tearDown(): void
    {
        Zend_Loader_AutoloaderFactory::unregisterAutoloaders();
        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        if (is_array($loaders)) {
            foreach ($loaders as $loader) {
                spl_autoload_unregister($loader);
            }
        }

        foreach ($this->_loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Restore original include_path
        set_include_path($this->_includePath);
    }

    public function testAutoincluding(): void
    {
        Zend_Loader_AutoloaderFactory::factory(
            array(
                'Zend_Loader_ClassMapAutoloader' => array(
                    __DIR__ . '/_files/goodmap.php',
                ),
            )
        );
        $loader = Zend_Loader_AutoloaderFactory::getRegisteredAutoloader(
            'Zend_Loader_ClassMapAutoloader'
        );
        $map = $loader->getAutoloadMap();
        $this->assertTrue(is_array($map));
        $this->assertEquals(2, count($map));
    }
}
