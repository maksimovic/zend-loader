<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_ClassMapAutoloaderTest extends TestCase
{
    /**
     * @var array
     */
    protected $loaders;

    /**
     * @var string|bool|mixed
     */
    protected $includePath;

    /**
     * @var Zend_Loader_ClassMapAutoloader
     */
    private $loader;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            $this->loaders = array();
        }

        // Store original include_path
        $this->includePath = get_include_path();

        $this->loader = new Zend_Loader_ClassMapAutoloader();
    }

    public function tearDown(): void
    {
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

    public function testRegisteringNonExistentAutoloadMapRaisesInvalidArgumentException(): void
    {
        $dir = __DIR__ . '__foobar__';
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        $this->loader->registerAutoloadMap($dir);
    }

    public function testValidMapFileNotReturningMapRaisesInvalidArgumentException(): void
    {
        $this->expectException(Zend_Loader_Exception_InvalidArgumentException::class);
        $this->loader->registerAutoloadMap(__DIR__ . '/_files/badmap.php');
    }

    public function testAllowsRegisteringArrayAutoloadMapDirectly(): void
    {
        $map = array(
            'Zend_Loader_Exception' => dirname(__DIR__, 2) . '/library/Zend/Loader/Exception.php',
        );
        $this->loader->registerAutoloadMap($map);
        $test = $this->loader->getAutoloadMap();
        $this->assertSame($map, $test);
    }

    public function testAllowsRegisteringArrayAutoloadMapViaConstructor(): void
    {
        $map = array(
            'Zend_Loader_Exception' => dirname(__DIR__, 2) . '/library/Zend/Loader/Exception.php',
        );
        $loader = new Zend_Loader_ClassMapAutoloader(array($map));
        $test = $loader->getAutoloadMap();
        $this->assertSame($map, $test);
    }

    public function testRegisteringValidMapFilePopulatesAutoloader(): void
    {
        $this->loader->registerAutoloadMap(__DIR__ . '/_files/goodmap.php');
        $map = $this->loader->getAutoloadMap();
        $this->assertTrue(is_array($map));
        $this->assertEquals(2, count($map));
        // Just to make sure nothing changes after loading the same map again
        $this->loader->registerAutoloadMap(__DIR__ . '/_files/goodmap.php');
        $map = $this->loader->getAutoloadMap();
        $this->assertTrue(is_array($map));
        $this->assertEquals(2, count($map));
    }

    public function testRegisteringMultipleMapsMergesThem(): void
    {
        $map = array(
            'Zend_Loader_Exception' => dirname(__DIR__, 2) . '/library/Zend/Loader/Exception.php',
            'Zend_Loader_StandardAutoloaderTest' => 'some/bogus/path.php',
        );
        $this->loader->registerAutoloadMap($map);
        $this->loader->registerAutoloadMap(__DIR__ . '/_files/goodmap.php');

        $test = $this->loader->getAutoloadMap();
        $this->assertTrue(is_array($test));
        $this->assertEquals(3, count($test));
        $this->assertNotEquals($map['Zend_Loader_StandardAutoloaderTest'], $test['Zend_Loader_StandardAutoloaderTest']);
    }

    public function testCanRegisterMultipleMapsAtOnce(): void
    {
        $map = array(
            'Zend_Loader_Exception' => dirname(__DIR__, 2) . '/library/Zend/Loader/Exception.php',
            'Zend_Loader_StandardAutoloaderTest' => 'some/bogus/path.php',
        );
        $maps = array($map, __DIR__ . '/_files/goodmap.php');
        $this->loader->registerAutoloadMaps($maps);
        $test = $this->loader->getAutoloadMap();
        $this->assertTrue(is_array($test));
        $this->assertEquals(3, count($test));
    }

    public function testRegisterMapsThrowsExceptionForNonTraversableArguments(): void
    {
        $tests = array(true, 'string', 1, 1.0, new stdClass);
        foreach ($tests as $test) {
            try {
                $this->loader->registerAutoloadMaps($test);
                $this->fail('Should not register non-traversable arguments');
            } catch (Zend_Loader_Exception_InvalidArgumentException $e) {
                $this->assertStringContainsString('array or implement Traversable', $e->getMessage());
            }
        }
    }

    public function testAutoloadLoadsClasses(): void
    {
        $map = array('Zend_UnusualNamespace_ClassMappedClass' => __DIR__ . '/TestAsset/ClassMappedClass.php');
        $this->loader->registerAutoloadMap($map);
        $this->loader->autoload('Zend_UnusualNamespace_ClassMappedClass');
        $this->assertTrue(class_exists('Zend_UnusualNamespace_ClassMappedClass', false));
    }

    public function testIgnoresClassesNotInItsMap(): void
    {
        $map = array('Zend_UnusualNamespace_ClassMappedClass' => __DIR__ . '/TestAsset/ClassMappedClass.php');
        $this->loader->registerAutoloadMap($map);
        $this->loader->autoload('Zend_UnusualNamespace_UnMappedClass');
        $this->assertFalse(class_exists('Zend_UnusualNamespace_UnMappedClass', false));
    }

    public function testRegisterRegistersCallbackWithSplAutoload(): void
    {
        $this->loader->register();
        $loaders = spl_autoload_functions();
        $this->assertTrue(count($this->loaders) < count($loaders));
        $found = false;
        foreach ($loaders as $loader) {
            if ($loader == array($this->loader, 'autoload')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Autoloader not found in stack');
    }

    public function testCanLoadClassMapFromPhar(): void
    {
        if (!class_exists('Phar')) {
            $this->markTestSkipped('Test requires Phar extension');
        }
        $map = 'phar://' . __DIR__ . '/_files/classmap.phar/test/.//../autoload_classmap.php';
        $this->loader->registerAutoloadMap($map);
        $this->loader->autoload('some_loadedclass');
        $this->assertTrue(class_exists('some_loadedclass', false));
        $test = $this->loader->getAutoloadMap();
        $this->assertEquals(2, count($test));

        // will not register duplicate, even with a different relative path
        $map = 'phar://' . __DIR__ . '/_files/classmap.phar/test/./foo/../../autoload_classmap.php';
        $this->loader->registerAutoloadMap($map);
        $test = $this->loader->getAutoloadMap();
        $this->assertEquals(2, count($test));
    }

    public function testCanLoadNamespacedClassFromPhar(): void
    {
        $map = 'phar://' . __DIR__ . '/_files/classmap.phar/test/.//../autoload_classmap.php';
        $this->loader->registerAutoloadMap($map);
        $this->loader->autoload('some\namespacedclass');
        $this->assertTrue(class_exists('some\namespacedclass', false));
    }
}
