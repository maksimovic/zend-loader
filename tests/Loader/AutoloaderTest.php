<?php

use PHPUnit\Framework\TestCase;

function ZendLoaderAutoloader_Autoload($class)
{
    return $class;
}

class Zend_Loader_AutoloaderTest_Autoloader implements Zend_Loader_Autoloader_Interface
{
    public function autoload($class)
    {
        return $class;
    }
}

class Zend_Loader_AutoloaderTest extends TestCase
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
     * $var Zend_Loader_Autoloader
     */
    protected $autoloader;

    /**
     * @var null|mixed
     */
    protected $error;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            $this->loaders = array();
        }

        // Store original include_path
        $this->includePath = get_include_path();

        Zend_Loader_Autoloader::resetInstance();
        $this->autoloader = Zend_Loader_Autoloader::getInstance();

        // initialize 'error' member for tests that utilize error handling
        $this->error = null;
    }

    public function tearDown(): void
    {
        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        foreach ($this->loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Restore original include_path
        set_include_path($this->includePath);

        // Reset autoloader instance so it doesn't affect other tests
        Zend_Loader_Autoloader::resetInstance();
    }

    public function testAutoloaderShouldBeSingleton(): void
    {
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $this->assertSame($this->autoloader, $autoloader);
    }

    public function testSingletonInstanceShouldAllowReset(): void
    {
        Zend_Loader_Autoloader::resetInstance();
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $this->assertNotSame($this->autoloader, $autoloader);
    }

    public function testAutoloaderShouldRegisterItselfWithSplAutoloader(): void
    {
        $autoloaders = spl_autoload_functions();
        $found = false;
        foreach ($autoloaders as $loader) {
            if (is_array($loader)) {
                if (('autoload' == $loader[1]) && ($loader[0] === get_class($this->autoloader))) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Autoloader instance not found in spl_autoload stack: ' . var_export($autoloaders, 1));
    }

    public function testDefaultAutoloaderShouldBeZendLoader(): void
    {
        $this->assertSame(array('Zend_Loader', 'loadClass'), $this->autoloader->getDefaultAutoloader());
    }

    public function testDefaultAutoloaderShouldBeMutable(): void
    {
        $this->autoloader->setDefaultAutoloader(array($this, 'autoload'));
        $this->assertSame(array($this, 'autoload'), $this->autoloader->getDefaultAutoloader());
    }

    public function testSpecifyingInvalidDefaultAutoloaderShouldRaiseException(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->autoloader->setDefaultAutoloader(uniqid());
    }

    public function testZfNamespacesShouldBeRegisteredByDefault(): void
    {
        $namespaces = $this->autoloader->getRegisteredNamespaces();
        $this->assertContains('Zend_', $namespaces);
        $this->assertNotContains('ZendX_', $namespaces);
    }

    public function testAutoloaderShouldAllowRegisteringArbitraryNamespaces(): void
    {
        $this->autoloader->registerNamespace('Phly_');
        $namespaces = $this->autoloader->getRegisteredNamespaces();
        $this->assertContains('Phly_', $namespaces);
    }

    public function testAutoloaderShouldAllowRegisteringMultipleNamespacesAtOnce(): void
    {
        $this->autoloader->registerNamespace(array('Phly_', 'Solar_'));
        $namespaces = $this->autoloader->getRegisteredNamespaces();
        $this->assertContains('Phly_', $namespaces);
        $this->assertContains('Solar_', $namespaces);
    }

    public function testRegisteringInvalidNamespaceSpecShouldRaiseException(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $o = new stdClass;
        $this->autoloader->registerNamespace($o);
    }

    public function testAutoloaderShouldAllowUnregisteringNamespaces(): void
    {
        $this->autoloader->unregisterNamespace('Zend');
        $namespaces = $this->autoloader->getRegisteredNamespaces();
        $this->assertNotContains('Zend', $namespaces);
    }

    public function testAutoloaderShouldAllowUnregisteringMultipleNamespacesAtOnce(): void
    {
        $this->autoloader->unregisterNamespace(array('Zend', 'ZendX'));
        $namespaces = $this->autoloader->getRegisteredNamespaces();
        $this->assertNotContains('Zend', $namespaces);
        $this->assertNotContains('ZendX', $namespaces);
    }

    public function testUnregisteringInvalidNamespaceSpecShouldRaiseException(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $o = new stdClass;
        $this->autoloader->unregisterNamespace($o);
    }

    /**
     * @group ZF-6536
     */
    public function testWarningSuppressionShouldBeDisabledByDefault(): void
    {
        $this->assertFalse($this->autoloader->suppressNotFoundWarnings());
    }

    public function testAutoloaderSuppressNotFoundWarningsFlagShouldBeMutable(): void
    {
        $this->autoloader->suppressNotFoundWarnings(true);
        $this->assertTrue($this->autoloader->suppressNotFoundWarnings());
    }

    public function testFallbackAutoloaderFlagShouldBeOffByDefault(): void
    {
        $this->assertFalse($this->autoloader->isFallbackAutoloader());
    }

    public function testFallbackAutoloaderFlagShouldBeMutable(): void
    {
        $this->autoloader->setFallbackAutoloader(true);
        $this->assertTrue($this->autoloader->isFallbackAutoloader());
    }

    public function testUnshiftAutoloaderShouldAddToTopOfAutoloaderStack(): void
    {
        $this->autoloader->unshiftAutoloader('require');
        $autoloaders = $this->autoloader->getAutoloaders();
        $test = array_shift($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testUnshiftAutoloaderWithoutNamespaceShouldRegisterAsEmptyNamespace(): void
    {
        $this->autoloader->unshiftAutoloader('require');
        $autoloaders = $this->autoloader->getNamespaceAutoloaders('');
        $test = array_shift($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testUnshiftAutoloaderShouldAllowSpecifyingSingleNamespace(): void
    {
        $this->autoloader->unshiftAutoloader('require', 'Foo');
        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Foo');
        $test = array_shift($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testUnshiftAutoloaderShouldAllowSpecifyingMultipleNamespaces(): void
    {
        $this->autoloader->unshiftAutoloader('require', array('Foo', 'Bar'));

        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Foo');
        $test = array_shift($autoloaders);
        $this->assertEquals('require', $test);

        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Bar');
        $test = array_shift($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testPushAutoloaderShouldAddToEndOfAutoloaderStack(): void
    {
        $this->autoloader->pushAutoloader('require');
        $autoloaders = $this->autoloader->getAutoloaders();
        $test = array_pop($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testPushAutoloaderWithoutNamespaceShouldRegisterAsEmptyNamespace(): void
    {
        $this->autoloader->pushAutoloader('require');
        $autoloaders = $this->autoloader->getNamespaceAutoloaders('');
        $test = array_pop($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testPushAutoloaderShouldAllowSpecifyingSingleNamespace(): void
    {
        $this->autoloader->pushAutoloader('require', 'Foo');
        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Foo');
        $test = array_pop($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testPushAutoloaderShouldAllowSpecifyingMultipleNamespaces(): void
    {
        $this->autoloader->pushAutoloader('require', array('Foo', 'Bar'));

        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Foo');
        $test = array_pop($autoloaders);
        $this->assertEquals('require', $test);

        $autoloaders = $this->autoloader->getNamespaceAutoloaders('Bar');
        $test = array_pop($autoloaders);
        $this->assertEquals('require', $test);
    }

    public function testAutoloaderShouldAllowRemovingConcreteAutoloadersFromStackByCallback(): void
    {
        $this->autoloader->pushAutoloader('require');
        $this->autoloader->removeAutoloader('require');
        $autoloaders = $this->autoloader->getAutoloaders();
        $this->assertNotContains('require', $autoloaders);
    }

    public function testRemovingAutoloaderShouldAlsoRemoveAutoloaderFromNamespacedAutoloaders(): void
    {
        $this->autoloader->pushAutoloader('require', array('Foo', 'Bar'))
                         ->pushAutoloader('include');
        $this->autoloader->removeAutoloader('require');
        $test = $this->autoloader->getNamespaceAutoloaders('Foo');
        $this->assertTrue(empty($test));
        $test = $this->autoloader->getNamespaceAutoloaders('Bar');
        $this->assertTrue(empty($test));
    }

    public function testAutoloaderShouldAllowRemovingCallbackFromSpecifiedNamespaces(): void
    {
        $this->autoloader->pushAutoloader('require', array('Foo', 'Bar'))
                         ->pushAutoloader('include');
        $this->autoloader->removeAutoloader('require', 'Foo');
        $test = $this->autoloader->getNamespaceAutoloaders('Foo');
        $this->assertTrue(empty($test));
        $test = $this->autoloader->getNamespaceAutoloaders('Bar');
        $this->assertFalse(empty($test));
    }

    public function testAutoloadShouldReturnFalseWhenNamespaceIsNotRegistered(): void
    {
        $this->assertFalse(Zend_Loader_Autoloader::autoload('Foo_Bar'));
    }

    public function testAutoloadShouldReturnFalseWhenNamespaceIsNotRegisteredButClassfileExists(): void
    {
        $this->addTestIncludePath();
        $this->assertFalse(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Foo'));
    }

    public function testAutoloadShouldReturnFalseWhenClassIsNotDefinedInClassfile(): void
    {
        $this->addTestIncludePath();
        $this->autoloader->registerNamespace('ZendLoaderAutoloader');
        $this->assertFalse(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_ClassNonexistent'));
    }

    public function testAutoloadShouldLoadClassWhenNamespaceIsRegisteredAndClassfileExists(): void
    {
        $this->addTestIncludePath();
        $this->autoloader->registerNamespace('ZendLoaderAutoloader');
        $this->assertTrue(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Foo'));
        $this->assertTrue(class_exists('ZendLoaderAutoloader_Foo', false));
    }

    public function testAutoloadShouldNotSuppressFileNotFoundWarningsWhenFlagIsDisabled(): void
    {
        $this->addTestIncludePath();
        $this->autoloader->suppressNotFoundWarnings(false);
        $this->autoloader->registerNamespace('ZendLoaderAutoloader');
        set_error_handler(array($this, 'handleErrors'));
        $this->assertFalse(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Bar'));
        restore_error_handler();

        $this->assertNull($this->error);
    }

    public function testAutoloadShouldNotSuppressErrorsWhenFlagIsDisabled(): void
    {
        $this->addTestIncludePath();
        $this->autoloader->suppressNotFoundWarnings(false);
        $this->autoloader->registerNamespace('ZendLoaderAutoloader');
        set_error_handler(array($this, 'handleErrors'));
        $this->assertTrue(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_SomethingWrong'));
        restore_error_handler();
        $this->assertNotNull($this->error);
    }

    public function testAutoloadShouldReturnTrueIfFunctionBasedAutoloaderMatchesAndReturnsNonFalseValue(): void
    {
        $this->autoloader->pushAutoloader('ZendLoaderAutoloader_Autoload');
        $this->assertTrue(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Foo_Bar'));
    }

    public function testAutoloadShouldReturnTrueIfMethodBasedAutoloaderMatchesAndReturnsNonFalseValue(): void
    {
        $this->autoloader->pushAutoloader(array($this, 'autoload'));
        $this->assertTrue(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Foo_Bar'));
    }

    public function testAutoloadShouldReturnTrueIfAutoloaderImplementationReturnsNonFalseValue(): void
    {
        $this->autoloader->pushAutoloader(new Zend_Loader_AutoloaderTest_Autoloader());
        $this->assertTrue(Zend_Loader_Autoloader::autoload('ZendLoaderAutoloader_Foo_Bar'));
    }

    public function testUsingAlternateDefaultLoaderShouldOverrideUsageOfZendLoader(): void
    {
        $this->autoloader->setDefaultAutoloader(array($this, 'autoload'));
        $class = $this->autoloader->autoload('Zend_ThisClass_WilNever_Exist');
        $this->assertTrue($class);
        $this->assertFalse(class_exists($class, false));
    }

    /**
     * @group ZF-10024
     */
    public function testClosuresRegisteredWithAutoloaderShouldBeUtilized(): void
    {
        $closure = require __DIR__ . '/_files/AutoloaderClosure.php';
        $this->autoloader->pushAutoloader($closure);
        $this->assertTrue(Zend_Loader_Autoloader::autoload('AutoloaderTest_AutoloaderClosure'));
    }

    /**
     * @group ZF-11219
     */
    public function testRetrievesAutoloadersFromLongestMatchingNamespace(): void
    {
        $this->autoloader->pushAutoloader(array($this, 'autoloadFirstLevel'), 'Level1_')
                         ->pushAutoloader(array($this, 'autoloadSecondLevel'), 'Level1_Level2');
        $class = 'Level1_Level2_Foo';
        $als   = $this->autoloader->getClassAutoloaders($class);
        $this->assertEquals(1, count($als));
        $al    = array_shift($als);
        $this->assertEquals(array($this, 'autoloadSecondLevel'), $al);
    }

    /**
     * @group ZF-10136
     */
    public function testMergedAutoloadersWithoutNamespace(): void
    {
        $this->autoloader
             ->pushAutoloader('autoloadOne')
             ->pushAutoloader('autoloadSecond');

        $class = 'Zend_Autoloader_Test';
        $autoloaders = $this->autoloader->getClassAutoloaders($class);
        $this->assertEquals(3, count($autoloaders));
    }

    public function addTestIncludePath(): void
    {
        set_include_path(__DIR__ . '/_files/' . PATH_SEPARATOR . $this->includePath);
    }

    public function handleErrors($errno, $errstr): void
    {
        $this->error = $errstr;
    }

    public function autoload($class)
    {
        return $class;
    }

    public function autoloadFirstLevel($class)
    {
        return $class;
    }

    public function autoloadSecondLevel($class)
    {
        return $class;
    }
}
