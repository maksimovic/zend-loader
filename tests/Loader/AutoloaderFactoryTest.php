<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_AutoloaderFactoryTest extends TestCase
{
    /**
     * @var array
     */
    protected $loaders;

    /**
     * @var string|bool|mixed
     */
    protected $includePath;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            $this->loaders = array();
        }

        // Store original include_path
        $this->includePath = get_include_path();
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

        foreach ($this->loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Restore original include_path
        set_include_path($this->includePath);
    }

    public function testRegisteringValidMapFilePopulatesAutoloader(): void
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_ClassMapAutoloader' => array(
                __DIR__ . '/_files/goodmap.php',
            ),
        ));
        $loader = Zend_Loader_AutoloaderFactory::getRegisteredAutoloader('Zend_Loader_ClassMapAutoloader');
        $map = $loader->getAutoloadMap();
        $this->assertTrue(is_array($map));
        $this->assertEquals(2, count($map));
    }

    /**
     * This tests checks if invalid autoloaders cause exceptions
     */
    public function testFactoryCatchesInvalidClasses(): void
    {
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        include __DIR__ . '/_files/InvalidInterfaceAutoloader.php';
        Zend_Loader_AutoloaderFactory::factory(array(
            'InvalidInterfaceAutoloader' => array()
        ));
    }

    public function testFactoryDoesNotRegisterDuplicateAutoloaders(): void
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'TestPrefix' => __DIR__ . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        $this->assertEquals(1, count(Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'ZendTest_Loader_TestAsset_TestPlugins' => __DIR__ . '/TestAsset/TestPlugins',
                ),
            ),
        ));
        $this->assertEquals(1, count(Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
        $this->assertTrue(class_exists('TestPrefix_NoDuplicateAutoloadersCase'));
        $this->assertTrue(class_exists('ZendTest_Loader_TestAsset_TestPlugins_Foo'));
    }

    public function testCanUnregisterAutoloaders(): void
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'TestPrefix' => __DIR__ . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        Zend_Loader_AutoloaderFactory::unregisterAutoloaders();
        $this->assertEquals(0, count(Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testCanUnregisterAutoloadersByClassName(): void
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'namespaces' => array(
                    'TestPrefix' => __DIR__ . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        Zend_Loader_AutoloaderFactory::unregisterAutoloader('Zend_Loader_StandardAutoloader');
        $this->assertEquals(0, count(Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testCanGetValidRegisteredAutoloader(): void
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'namespaces' => array(
                    'TestPrefix' => __DIR__ . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        $autoloader = Zend_Loader_AutoloaderFactory::getRegisteredAutoloader('Zend_Loader_StandardAutoloader');
        $this->assertTrue($autoloader instanceof Zend_Loader_StandardAutoloader);
    }

    public function testDefaultAutoloader(): void
    {
        Zend_Loader_AutoloaderFactory::factory();
        $autoloader = Zend_Loader_AutoloaderFactory::getRegisteredAutoloader('Zend_Loader_StandardAutoloader');
        $this->assertTrue($autoloader instanceof Zend_Loader_StandardAutoloader);
        $this->assertEquals(1, count(Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testGetInvalidAutoloaderThrowsException(): void
    {
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        $loader = Zend_Loader_AutoloaderFactory::getRegisteredAutoloader('InvalidAutoloader');
    }

    public function testFactoryWithInvalidArgumentThrowsException(): void
    {
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        Zend_Loader_AutoloaderFactory::factory('InvalidArgument');
    }

    public function testFactoryWithInvalidAutoloaderClassThrowsException(): void
    {
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        Zend_Loader_AutoloaderFactory::factory(array('InvalidAutoloader' => array()));
    }

    public function testCannotBeInstantiatedViaConstructor(): void
    {
        $reflection = new ReflectionClass('Zend_Loader_AutoloaderFactory');
        $constructor = $reflection->getConstructor();
        $this->assertNull($constructor);
    }

    public function testPassingNoArgumentsToFactoryInstantiatesAndRegistersStandardAutoloader(): void
    {
        Zend_Loader_AutoloaderFactory::factory();
        $loaders = Zend_Loader_AutoloaderFactory::getRegisteredAutoloaders();
        $this->assertEquals(1, count($loaders));
        $loader = array_shift($loaders);
        $this->assertTrue($loader instanceof Zend_Loader_StandardAutoloader);

        $test  = array($loader, 'autoload');
        $found = false;
        foreach (spl_autoload_functions() as $function) {
            if ($function === $test) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'StandardAutoloader not registered with spl_autoload');
    }
}
