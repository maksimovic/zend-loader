<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_PluginLoaderTest extends TestCase
{
    /**
     * @var string|bool|mixed
     */
    protected $libPath;

    /**
     * @var null|mixed|string
     */
    protected $key;

    protected $_includeCache;

    public function setUp(): void
    {
        if ($this->_includeCache && file_exists($this->_includeCache)) {
            unlink($this->_includeCache);
        }
        Zend_Loader_PluginLoader::setIncludeFileCache(null);
        $this->_includeCache = __DIR__ . '/_files/includeCache.inc.php';
        $this->libPath = realpath(__DIR__ . '/../../library');
        $this->key = null;
    }

    public function tearDown(): void
    {
        $this->clearStaticPaths();
        Zend_Loader_PluginLoader::setIncludeFileCache(null);
        if ($this->_includeCache && file_exists($this->_includeCache)) {
            unlink($this->_includeCache);
        }
    }

    public function clearStaticPaths(): void
    {
        if (null !== $this->key) {
            $loader = new Zend_Loader_PluginLoader(array(), $this->key);
            $loader->clearPaths();
        }
    }

    public function testAddPrefixPathNonStatically(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $this->assertTrue(array_key_exists('Zend_View_', $paths));
        $this->assertTrue(array_key_exists('Zend_Loader_', $paths));
        $this->assertEquals(1, count($paths['Zend_View_']));
        $this->assertEquals(2, count($paths['Zend_Loader_']));
    }

    public function testAddPrefixPathMultipleTimes(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader');
        $paths = $loader->getPaths();

        $this->assertTrue(is_array($paths));
        $this->assertEquals(1, count($paths['Zend_Loader_']));
    }

    public function testAddPrefixPathStatically(): void
    {
        $this->key = 'foobar';
        $loader = new Zend_Loader_PluginLoader(array(), $this->key);
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $this->assertTrue(array_key_exists('Zend_View_', $paths));
        $this->assertTrue(array_key_exists('Zend_Loader_', $paths));
        $this->assertEquals(1, count($paths['Zend_View_']));
        $this->assertEquals(2, count($paths['Zend_Loader_']));
    }

    public function testAddPrefixPathThrowsExceptionWithNonStringPrefix(): void
    {
        $this->expectException(Zend_Loader_PluginLoader_Exception::class);
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath(array(), $this->libPath);
    }

    public function testAddPrefixPathThrowsExceptionWithNonStringPath(): void
    {
        $this->expectException(Zend_Loader_PluginLoader_Exception::class);
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Foo_Bar', array());
    }

    public function testRemoveAllPathsForGivenPrefixNonStatically(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths('Zend_Loader');
        $this->assertEquals(2, count($paths));
        $loader->removePrefixPath('Zend_Loader');
        $this->assertFalse($loader->getPaths('Zend_Loader'));
    }

    public function testRemoveAllPathsForGivenPrefixStatically(): void
    {
        $this->key = 'foobar';
        $loader = new Zend_Loader_PluginLoader(array(), $this->key);
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths('Zend_Loader');
        $this->assertEquals(2, count($paths));
        $loader->removePrefixPath('Zend_Loader');
        $this->assertFalse($loader->getPaths('Zend_Loader'));
    }

    public function testRemovePrefixPathThrowsExceptionIfPrefixNotRegistered(): void
    {
        $this->expectException(Zend_Loader_PluginLoader_Exception::class);
        $loader = new Zend_Loader_PluginLoader();
        $loader->removePrefixPath('Foo_Bar');
    }

    public function testRemovePrefixPathThrowsExceptionIfPrefixPathPairNotRegistered(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Foo_Bar', realpath(__DIR__));
        $paths = $loader->getPaths();
        $this->assertTrue(isset($paths['Foo_Bar_']));
        try {
            $loader->removePrefixPath('Foo_Bar', $this->libPath);
            $this->fail('Removing non-existent prefix/path pair should throw an exception');
        } catch (Exception $e) {
        }
    }

    public function testClearPathsNonStaticallyClearsPathArray(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $loader->clearPaths();
        $paths = $loader->getPaths();
        $this->assertEquals(0, count($paths));
    }

    public function testClearPathsStaticallyClearsPathArray(): void
    {
        $this->key = 'foobar';
        $loader = new Zend_Loader_PluginLoader(array(), $this->key);
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $loader->clearPaths();
        $paths = $loader->getPaths();
        $this->assertEquals(0, count($paths));
    }

    public function testClearPathsWithPrefixNonStaticallyClearsPathArray(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $loader->clearPaths('Zend_Loader');
        $paths = $loader->getPaths();
        $this->assertEquals(1, count($paths));
    }

    public function testClearPathsWithPrefixStaticallyClearsPathArray(): void
    {
        $this->key = 'foobar';
        $loader = new Zend_Loader_PluginLoader(array(), $this->key);
        $loader->addPrefixPath('Zend_View', $this->libPath . '/Zend/View')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend/Loader')
               ->addPrefixPath('Zend_Loader', $this->libPath . '/Zend');
        $paths = $loader->getPaths();
        $this->assertEquals(2, count($paths));
        $loader->clearPaths('Zend_Loader');
        $paths = $loader->getPaths();
        $this->assertEquals(1, count($paths));
    }

    public function testGetClassNameNonStaticallyReturnsFalseWhenClassNotLoaded(): void
    {
        $loader = new Zend_Loader_PluginLoader();
        $loader->addPrefixPath('Zend_View_Helper', $this->libPath . '/Zend/View/Helper');
        $this->assertFalse($loader->getClassName('FormElement'));
    }

    public function testGetClassNameStaticallyReturnsFalseWhenClassNotLoaded(): void
    {
        $this->key = 'foobar';
        $loader = new Zend_Loader_PluginLoader(array(), $this->key);
        $loader->addPrefixPath('Zend_View_Helper', $this->libPath . '/Zend/View/Helper');
        $this->assertFalse($loader->getClassName('FormElement'));
    }

    public function testLoadPluginNonStaticallyLoadsClass(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testLoadPluginStaticallyLoadsClass(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testLoadThrowsExceptionIfFileFoundInPrefixButClassNotLoaded(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testLoadThrowsExceptionIfNoHelperClassLoaded(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testGetClassAfterNonStaticLoadReturnsResolvedClassName(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testGetClassAfterStaticLoadReturnsResolvedClassName(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testClassFilesAreSearchedInLifoOrder(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    public function testWin32UnderscoreSpacedShortNamesWillLoad(): void
    {
        $this->markTestSkipped('Requires zend-filter package which is not available');
    }

    /**
     * @group ZF-4670
     */
    public function testIncludeCacheShouldBeNullByDefault(): void
    {
        $this->assertNull(Zend_Loader_PluginLoader::getIncludeFileCache());
    }

    /**
     * @group ZF-4670
     */
    public function testPluginLoaderShouldAllowSpecifyingIncludeFileCache(): void
    {
        $cacheFile = $this->_includeCache;
        $this->testIncludeCacheShouldBeNullByDefault();
        Zend_Loader_PluginLoader::setIncludeFileCache($cacheFile);
        $this->assertEquals($cacheFile, Zend_Loader_PluginLoader::getIncludeFileCache());
    }

    /**
     * @group ZF-4670
     */
    public function testPluginLoaderShouldThrowExceptionWhenPathDoesNotExist(): void
    {
        $this->expectException(Zend_Loader_PluginLoader_Exception::class);
        $cacheFile = __DIR__ . '/_filesDoNotExist/includeCache.inc.php';
        $this->testIncludeCacheShouldBeNullByDefault();
        Zend_Loader_PluginLoader::setIncludeFileCache($cacheFile);
        $this->fail('Should not allow specifying invalid cache file path');
    }

    public function testPluginLoaderShouldAppendIncludeCacheWhenClassIsFound(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    /**
     * @group ZF-5208
     */
    public function testStaticRegistryNamePersistsInDifferentLoaderObjects(): void
    {
        $loader1 = new Zend_Loader_PluginLoader(array(), "PluginLoaderStaticNamespace");
        $loader1->addPrefixPath("Zend_View_Helper", "Zend/View/Helper");

        $loader2 = new Zend_Loader_PluginLoader(array(), "PluginLoaderStaticNamespace");
        $this->assertEquals(array(
            "Zend_View_Helper_" => array("Zend/View/Helper/"),
        ), $loader2->getPaths());
    }

    public function testClassFilesGrabCorrectPathForLoadedClasses(): void
    {
        $this->markTestSkipped('Requires zend-view package which is not available');
    }

    /**
     * @group ZF-7350
     */
    public function testPrefixesEndingInBackslashDenoteNamespacedClasses(): void
    {
        $loader = new Zend_Loader_PluginLoader(array());
        $loader->addPrefixPath('Zfns\\', __DIR__ . '/_files/Zfns');
        try {
            $className = $loader->load('Foo');
        } catch (Exception $e) {
            $paths = $loader->getPaths();
            $this->fail(sprintf("Failed loading helper; paths: %s", var_export($paths, 1)));
        }
        $this->assertEquals('Zfns\\Foo', $className);
        $this->assertEquals('Zfns\\Foo', $loader->getClassName('Foo'));
    }

    /**
     * @group ZF-9721
     */
    public function testRemovePrefixPathThrowsExceptionIfPathNotRegisteredInPrefix(): void
    {
        try {
            $loader = new Zend_Loader_PluginLoader(array('My_Namespace_' => 'My/Namespace/'));
            $loader->removePrefixPath('My_Namespace_', 'ZF9721');
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof Zend_Loader_PluginLoader_Exception);
            $this->assertStringContainsString('Prefix My_Namespace_ / Path ZF9721', $e->getMessage());
        }
        $this->assertEquals(1, count($loader->getPaths('My_Namespace_')));
    }

    /**
     * @group ZF-11330
     */
    public function testLoadClassesWithBackslashInName(): void
    {
        $loader = new Zend_Loader_PluginLoader(array());
        $loader->addPrefixPath('Zfns\\', __DIR__ . '/_files/Zfns');
        try {
            $className = $loader->load('Foo\\Bar');
        } catch (Exception $e) {
            $this->fail(sprintf("Failed loading helper with backslashes in name"));
        }
        $this->assertEquals('Zfns\\Foo\\Bar', $className);
    }

    public function testLoadClassesWithBackslashAndUnderscoreInName(): void
    {
        $loader = new Zend_Loader_PluginLoader(array());
        $loader->addPrefixPath('Zfns\\Foo_', __DIR__ . '/_files/Zfns/Foo');

        try {
            $className = $loader->load('Demo');
        } catch (Exception $e) {
            $this->fail(sprintf("Failed loading helper with backslashes and underscores in name"));
        }

        $this->assertEquals('Zfns\Foo_Demo', $className);
    }
}
