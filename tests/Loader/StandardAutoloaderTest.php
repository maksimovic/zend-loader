<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestAsset/StandardAutoloader.php';

class Zend_Loader_StandardAutoloaderTest extends TestCase
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

    public function testFallbackAutoloaderFlagDefaultsToFalse(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $this->assertFalse($loader->isFallbackAutoloader());
    }

    public function testFallbackAutoloaderStateIsMutable(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->setFallbackAutoloader(true);
        $this->assertTrue($loader->isFallbackAutoloader());
        $loader->setFallbackAutoloader(false);
        $this->assertFalse($loader->isFallbackAutoloader());
    }

    public function testPassingNonTraversableOptionsToSetOptionsRaisesException(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();

        $obj  = new stdClass();
        foreach (array(true, 'foo', $obj) as $arg) {
            try {
                $loader->setOptions(true);
                $this->fail('Setting options with invalid type should fail');
            } catch (Zend_Loader_Exception_InvalidArgumentException $e) {
                $this->assertStringContainsString('array or Traversable', $e->getMessage());
            }
        }
    }

    public function testPassingArrayOptionsPopulatesProperties(): void
    {
        $options = array(
            'namespaces' => array(
                'Zend\\'   => dirname(__DIR__) . DIRECTORY_SEPARATOR,
            ),
            'prefixes'   => array(
                'Zend_'  => dirname(__DIR__) . DIRECTORY_SEPARATOR,
            ),
            'fallback_autoloader' => true,
        );
        $loader = new Zend_Loader_TestAsset_StandardAutoloader();
        $loader->setOptions($options);
        $this->assertEquals($options['namespaces'], $loader->getNamespaces());
        $this->assertEquals($options['prefixes'], $loader->getPrefixes());
        $this->assertTrue($loader->isFallbackAutoloader());
    }

    public function testPassingTraversableOptionsPopulatesProperties(): void
    {
        $namespaces = new ArrayObject(array(
            'Zend\\' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
        ));
        $prefixes = new ArrayObject(array(
            'Zend_' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
        ));
        $options = new ArrayObject(array(
            'namespaces' => $namespaces,
            'prefixes'   => $prefixes,
            'fallback_autoloader' => true,
        ));
        $loader = new Zend_Loader_TestAsset_StandardAutoloader();
        $loader->setOptions($options);
        $this->assertEquals((array) $options['namespaces'], $loader->getNamespaces());
        $this->assertEquals((array) $options['prefixes'], $loader->getPrefixes());
        $this->assertTrue($loader->isFallbackAutoloader());
    }

    public function testAutoloadsNamespacedClasses(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->registerNamespace('Zend\UnusualNamespace', __DIR__ . '/TestAsset');
        $loader->autoload('Zend\UnusualNamespace\NamespacedClass');
        $this->assertTrue(class_exists('Zend\UnusualNamespace\NamespacedClass', false));
    }

    public function testAutoloadsVendorPrefixedClasses(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->registerPrefix('ZendTest_UnusualPrefix', __DIR__ . '/TestAsset/UnusualPrefix');
        $loader->autoload('ZendTest_UnusualPrefix_PrefixedClass');
        $this->assertTrue(class_exists('ZendTest_UnusualPrefix_PrefixedClass', false));
    }

    public function testCanActAsFallbackAutoloader(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->setFallbackAutoloader(true);
        set_include_path(__DIR__ . '/TestAsset/' . PATH_SEPARATOR . $this->includePath);
        $loader->autoload('TestPrefix_FallbackCase');
        $this->assertTrue(class_exists('TestPrefix_FallbackCase', false));
    }

    public function testReturnsFalseForUnresolveableClassNames(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $this->assertFalse($loader->autoload('Some\Fake\Classname'));
    }

    public function testReturnsFalseForInvalidClassNames(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->setFallbackAutoloader(true);
        $this->assertFalse($loader->autoload('Some_Invalid_Classname_'));
    }

    public function testRegisterRegistersCallbackWithSplAutoload(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->register();
        $loaders = spl_autoload_functions();
        $this->assertTrue(count($this->loaders) < count($loaders));
        $test = array_pop($loaders);
        $this->assertEquals(array($loader, 'autoload'), $test);
    }

    public function testAutoloadsNamespacedClassesWithUnderscores(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $loader->registerNamespace('ZendTest\UnusualNamespace', __DIR__ . '/TestAsset');
        $loader->autoload('ZendTest\UnusualNamespace\Name_Space\Namespaced_Class');
        $this->assertTrue(class_exists('ZendTest\UnusualNamespace\Name_Space\Namespaced_Class', false));
    }

    public function testZendFrameworkPrefixIsNotLoadedByDefault(): void
    {
        $loader = new Zend_Loader_StandardAutoloader();
        $r = new ReflectionClass($loader);
        $prop = $r->getProperty('prefixes');
        $expected = array();
        $this->assertEquals($expected, $prop->getValue($loader));
    }

    public function testCanTellAutoloaderToRegisterZfPrefixAtInstantiation(): void
    {
        $loader = new Zend_Loader_StandardAutoloader(array('autoregister_zf' => true));
        $r      = new ReflectionClass($loader);
        $file   = $r->getFileName();
        $expected = array('Zend_' => dirname(dirname($file)) . DIRECTORY_SEPARATOR);
        $prop = $r->getProperty('prefixes');
        $this->assertEquals($expected, $prop->getValue($loader));
    }
}
