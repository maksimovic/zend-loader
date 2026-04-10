<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Loader/MyLoader.php';
require_once __DIR__ . '/Loader/MyOverloader.php';
require_once __DIR__ . '/Loader/AutoloadableClass.php';

class ZendLoaderTest extends TestCase
{
    /**
     * @var array
     */
    private $loaders;

    /**
     * @var string
     */
    private $includePath;
    private $error;
    private $errorHandler;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            $this->loaders = array();
        }

        // Store original include_path
        $this->includePath = get_include_path();

        $this->error = null;
        $this->errorHandler = null;
        Zend_Loader_Autoloader::resetInstance();
    }

    public function tearDown(): void
    {
        if ($this->errorHandler !== null) {
            restore_error_handler();
        }

        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        if (is_array($loaders)) {
            foreach ($loaders as $loader) {
                spl_autoload_unregister($loader);
            }
        }

        if (is_array($this->loaders)) {
            foreach ($this->loaders as $loader) {
                spl_autoload_register($loader);
            }
        }

        // Restore original include_path
        set_include_path($this->includePath);

        // Reset autoloader instance so it doesn't affect other tests
        Zend_Loader_Autoloader::resetInstance();
    }

    public function setErrorHandler(): void
    {
        set_error_handler(array($this, 'handleErrors'), E_USER_NOTICE);
        $this->errorHandler = true;
    }

    public function handleErrors($errno, $errstr): void
    {
        $this->error = $errstr;
    }

    /**
     * Tests that a class can be loaded from a well-formed PHP file
     */
    public function testLoaderClassValid(): void
    {
        $dir = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1'));

        $this->assertTrue(Zend_Loader::loadClass('Class1', $dir));
        $this->assertTrue(class_exists('Class1', false));
    }

    public function testLoaderInterfaceViaLoadClass(): void
    {
        $this->markTestSkipped('Requires zend-controller package which is not available');
    }

    public function testLoaderLoadClassWithDotDir(): void
    {
        $this->markTestSkipped('have to be adjusted for split packages structure');
    }

    /**
     * Tests that an exception is thrown when a file is loaded but the
     * class is not found within the file
     */
    public function testLoaderClassNonexistent(): void
    {
        $this->setErrorHandler();
        $dir = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1'));

        try {
            Zend_Loader::loadClass('ClassNonexistent', $dir);
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertEquals('Class "ClassNonexistent" was not found in the file "ClassNonexistent.php".', $e->getMessage());
            $this->assertNull($this->error);
        }
    }

    /**
     * Tests that an exception is thrown when a file is not found
     */
    public function testLoaderFileNonexistent(): void
    {
        $this->setErrorHandler();
        $dir = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1'));

        try {
            Zend_Loader::loadClass('FileNotexistent', $dir);
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertMatchesRegularExpression('/file "(.*)" could not be found within configured include_path/i', $e->getMessage());
            $this->assertNull($this->error);
        }
    }

    /**
     * Tests that an exception is not thrown
     * using `tryLoadClass` to silently fail when file is not found
     */
    public function testLoaderTryLoadFileNonexistent(): void
    {
        $this->setErrorHandler();
        $dir = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1'));

        $this->assertFalse(Zend_Loader::tryLoadClass('FileNotexistent', $dir));
        $this->assertNull($this->error);
    }

    /**
     * Tests that an exception is still thrown when class in a file does not exist
     * while using `tryLoadClass`
     */
    public function testLoaderTryLoadClassNonexistent(): void
    {
        $this->setErrorHandler();
        $dir = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1'));

        try {
            Zend_Loader::tryLoadClass('ClassNonexistent', $dir);
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertEquals('Class "ClassNonexistent" was not found in the file "ClassNonexistent.php".', $e->getMessage());
            $this->assertNull($this->error);
        }
    }

    /**
     * Tests that an exception is thrown if the $dirs argument is
     * not a string or an array.
     */
    public function testLoaderInvalidDirs(): void
    {
        try {
            Zend_Loader::loadClass('Zend_Invalid_Dirs', new stdClass());
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertEquals('Directory argument must be a string or an array', $e->getMessage());
        }
    }

    /**
     * Tests that a class can be loaded from the search directories.
     */
    public function testLoaderClassSearchDirs(): void
    {
        $dirs = array();
        foreach (array('_testDir1', '_testDir2') as $dir) {
            $dirs[] = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', $dir));
        }

        // throws exception on failure
        $this->assertTrue(Zend_Loader::loadClass('Class1', $dirs));
        $this->assertTrue(Zend_Loader::loadClass('Class2', $dirs));
    }

    /**
     * Tests that a class located in a subdirectory can be loaded from the search directories
     */
    public function testLoaderClassSearchSubDirs(): void
    {
        $dirs = array();
        foreach (array('_testDir1', '_testDir2') as $dir) {
            $dirs[] = implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', $dir));
        }

        // throws exception on failure
        $this->assertTrue(Zend_Loader::loadClass('Class1_Subclass2', $dirs));
    }

    /**
     * Tests that the security filter catches illegal characters.
     */
    public function testLoaderClassIllegalFilename(): void
    {
        try {
            Zend_Loader::loadClass('/path/:to/@danger');
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertMatchesRegularExpression('/security(.*)filename/i', $e->getMessage());
        }
    }

    /**
     * Tests that loadFile() finds a file in the include_path when $dirs is null
     */
    public function testLoaderFileIncludePathEmptyDirs(): void
    {
        $saveIncludePath = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, array($saveIncludePath, implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1')))));

        $this->assertTrue(Zend_Loader::loadFile('Class3.php', null));

        set_include_path($saveIncludePath);
    }

    /**
     * Tests that loadFile() finds a file in the include_path when $dirs is non-null
     */
    public function testLoaderFileIncludePathNonEmptyDirs(): void
    {
        $saveIncludePath = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, array($saveIncludePath, implode(DIRECTORY_SEPARATOR, array(__DIR__, '_files', '_testDir1')))));

        $this->assertTrue(Zend_Loader::loadFile('Class4.php', implode(PATH_SEPARATOR, array('foo', 'bar'))));

        set_include_path($saveIncludePath);
    }

    /**
     * Tests that isReadable works
     */
    public function testLoaderIsReadable(): void
    {
        $this->assertTrue(Zend_Loader::isReadable(__FILE__));
        $this->assertFalse(Zend_Loader::isReadable(__FILE__ . '.foobaar'));
    }

    /**
     * Tests that autoload works for valid classes and interfaces
     */
    public function testLoaderAutoloadLoadsValidClasses(): void
    {
        $this->markTestSkipped('Requires zend-db and zend-auth packages which are not available');
    }

    /**
     * Tests that autoload returns false on invalid classes
     */
    public function testLoaderAutoloadFailsOnInvalidClasses(): void
    {
        $this->setErrorHandler();
        $this->assertFalse(Zend_Loader::autoload('Zend_FooBar_Magic_Abstract'));
        $this->assertStringContainsString('deprecated', $this->error);
    }

    public function testLoaderRegisterAutoloadRegisters(): void
    {
        $this->setErrorHandler();
        Zend_Loader::registerAutoload();
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $found       = false;
        foreach ($autoloaders as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    $found = true;
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Failed to register Zend_Loader_Autoloader with spl_autoload');
    }

    public function testLoaderRegisterAutoloadExtendedClassNeedsAutoloadMethod(): void
    {
        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyLoader');
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $expected    = array('Zend_Loader_MyLoader', 'autoload');
        $found       = false;
        foreach ($autoloaders as $function) {
            if ($expected == $function) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Failed to register Zend_Loader_MyLoader::autoload() with spl_autoload');

        spl_autoload_unregister($expected);
    }

    public function testLoaderRegisterAutoloadExtendedClassWithAutoloadMethod(): void
    {
        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader');
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $found       = false;
        foreach ($autoloaders as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Failed to register Zend_Loader_Autoloader with spl_autoload');

        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $expected    = array('Zend_Loader_MyOverloader', 'autoload');
        $this->assertContains($expected, $autoloaders, 'Failed to register My_Loader_MyOverloader with Zend_Loader_Autoloader: ' . var_export($autoloaders, 1));

        // try to instantiate a class that is known not to be loaded
        $obj = new Zend_Loader_AutoloadableClass();

        // now it should be loaded
        $this->assertTrue(class_exists('Zend_Loader_AutoloadableClass'),
            'Expected Zend_Loader_AutoloadableClass to be loaded');

        // and we verify it is the correct type
        $this->assertTrue($obj instanceof Zend_Loader_AutoloadableClass,
            'Expected to instantiate Zend_Loader_AutoloadableClass, got '.get_class($obj));

        spl_autoload_unregister($function);
    }

    public function testLoaderRegisterAutoloadFailsWithoutSplAutoload(): void
    {
        if (function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload() is installed on this PHP installation; cannot test for failure');
        }

        try {
            Zend_Loader::registerAutoload();
            $this->fail('registerAutoload should fail without spl_autoload');
        } catch (Zend_Exception $e) {
        }
    }

    public function testLoaderRegisterAutoloadInvalidClass(): void
    {
        $this->setErrorHandler();
        try {
            Zend_Loader::registerAutoload('stdClass');
            $this->fail('registerAutoload should fail without spl_autoload');
        } catch (Zend_Exception $e) {
            $this->assertEquals('The class "stdClass" does not have an autoload() method', $e->getMessage());
            $this->assertStringContainsString('deprecated', $this->error);
        }
    }

    public function testLoaderUnregisterAutoload(): void
    {
        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader');
        $this->assertStringContainsString('deprecated', $this->error);

        $expected    = array('Zend_Loader_MyOverloader', 'autoload');
        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $this->assertContains($expected, $autoloaders, 'Failed to register autoloader');

        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader', false);
        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $this->assertNotContains($expected, $autoloaders, 'Failed to unregister autoloader');

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }
    }

    /**
     * @group ZF-6605
     */
    public function testRegisterAutoloadShouldEnableZendLoaderAutoloaderAsFallbackAutoloader(): void
    {
        $this->setErrorHandler();
        Zend_Loader::registerAutoload();
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $this->assertTrue($autoloader->isFallbackAutoloader());

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }
    }

    /**
     * @group ZF-8200
     */
    public function testLoadClassShouldAllowLoadingPhpNamespacedClasses(): void
    {
        $this->assertTrue(Zend_Loader::loadClass('\Zfns\Foo', array(__DIR__ . '/Loader/_files')));
    }

    /**
     * @group ZF-7271
     * @group ZF-8913
     */
    public function testIsReadableShouldHonorStreamDefinitions(): void
    {
        $pharFile = __DIR__ . '/Loader/_files/Zend_LoaderTest.phar';
        $phar     = new Phar($pharFile, 0, 'zlt.phar');
        $incPath = 'phar://zlt.phar'
                 . PATH_SEPARATOR . $this->includePath;
        set_include_path($incPath);
        $this->assertTrue(Zend_Loader::isReadable('User.php'));
        unset($phar);
    }

    /**
     * @group ZF-8913
     */
    public function testIsReadableShouldNotLockWhenTestingForNonExistantFileInPhar(): void
    {
        $pharFile = __DIR__ . '/Loader/_files/Zend_LoaderTest.phar';
        $phar     = new Phar($pharFile, 0, 'zlt.phar');
        $incPath = 'phar://zlt.phar'
                 . PATH_SEPARATOR . $this->includePath;
        set_include_path($incPath);
        $this->assertFalse(Zend_Loader::isReadable('does-not-exist'));
        unset($phar);
    }

    /**
     * @group ZF-7271
     */
    public function testExplodeIncludePathProperlyIdentifiesStreamSchemes(): void
    {
        if (PATH_SEPARATOR != ':') {
            $this->markTestSkipped();
        }
        $path = 'phar://zlt.phar:/var/www:.:filter://[a-z]:glob://*';
        $paths = Zend_Loader::explodeIncludePath($path);
        $this->assertSame(array(
            'phar://zlt.phar',
            '/var/www',
            '.',
            'filter://[a-z]',
            'glob://*',
        ), $paths);
    }

    /**
     * @group ZF-9100
     */
    public function testIsReadableShouldReturnTrueForAbsolutePaths(): void
    {
        set_include_path(__DIR__ . '../../');
        $path = __DIR__;
        $this->assertTrue(Zend_Loader::isReadable($path));
    }

    /**
     * @group ZF-9263
     * @group ZF-9166
     * @group ZF-9306
     */
    public function testIsReadableShouldFailEarlyWhenProvidedInvalidWindowsAbsolutePath(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->markTestSkipped('Windows-only test');
        }
        $path = 'C:/this/file/should/not/exist.php';
        $this->assertFalse(Zend_Loader::isReadable($path));
    }
}
