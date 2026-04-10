<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_Autoloader_ResourceTest extends TestCase
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
     * @var null
     */
    protected $error;

    /**
     * $var Zend_Loader_Autoloader_Resource
     */
    protected $loader;

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

        $this->loader = new Zend_Loader_Autoloader_Resource(array(
            'namespace' => 'FooBar',
            'basePath'  => realpath(__DIR__ . '/_files'),
        ));
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

    public function testAutoloaderInstantiationShouldRaiseExceptionWithoutNamespace(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $loader = new Zend_Loader_Autoloader_Resource(array('basePath' => __DIR__));
    }

    public function testAutoloaderInstantiationShouldRaiseExceptionWithoutBasePath(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $loader = new Zend_Loader_Autoloader_Resource(array('namespace' => 'Foo'));
    }

    public function testAutoloaderInstantiationShouldRaiseExceptionWhenInvalidOptionsTypeProvided(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $loader = new Zend_Loader_Autoloader_Resource('foo');
    }

    public function testAutoloaderConstructorShouldAcceptZendConfigObject(): void
    {
        $this->markTestSkipped('Requires Zend_Config which is not available');
    }

    public function testAutoloaderShouldAllowRetrievingNamespace(): void
    {
        $this->assertEquals('FooBar', $this->loader->getNamespace());
    }

    public function testAutoloaderShouldAllowRetrievingBasePath(): void
    {
        $this->assertEquals(realpath(__DIR__ . '/_files'), $this->loader->getBasePath());
    }

    public function testNoResourceTypesShouldBeRegisteredByDefault(): void
    {
        $resourceTypes = $this->loader->getResourceTypes();
        $this->assertTrue(is_array($resourceTypes));
        $this->assertTrue(empty($resourceTypes));
    }

    public function testInitialResourceTypeDefinitionShouldRequireNamespace(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->addResourceType('foo', 'foo');
    }

    public function testPassingNonStringPathWhenAddingResourceTypeShouldRaiseAnException(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->addResourceType('foo', array('foo'), 'Foo');
    }

    public function testAutoloaderShouldAllowAddingArbitraryResourceTypes(): void
    {
        $this->loader->addResourceType('models', 'models', 'Model');
        $resources = $this->loader->getResourceTypes();
        $this->assertTrue(array_key_exists('models', $resources));
        $this->assertEquals($this->loader->getNamespace() . '_Model', $resources['models']['namespace']);
        $this->assertStringContainsString('/models', $resources['models']['path']);
    }

    public function testAutoloaderShouldAllowAddingResettingResourcePaths(): void
    {
        $this->loader->addResourceType('models', 'models', 'Model');
        $this->loader->addResourceType('models', 'apis');
        $resources = $this->loader->getResourceTypes();
        $this->assertStringNotContainsString('/models', $resources['models']['path']);
        $this->assertStringContainsString('/apis', $resources['models']['path']);
    }

    public function testAutoloaderShouldSupportAddingMultipleResourceTypesAtOnce(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $resources = $this->loader->getResourceTypes();
        $this->assertContains('model', array_keys($resources));
        $this->assertContains('form', array_keys($resources));
    }

    public function testAddingMultipleResourceTypesShouldRaiseExceptionWhenReceivingNonArrayItem(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->addResourceTypes(array('foo' => 'bar'));
    }

    public function testAddingMultipleResourceTypesShouldRaiseExceptionWhenMissingResourcePath(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->addResourceTypes(array('model' => array('namespace' => 'Model')));
    }

    public function testSetResourceTypesShouldOverwriteExistingResourceTypes(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));

        $this->loader->setResourceTypes(array(
            'view'   => array('path' => 'views', 'namespace' => 'View'),
            'layout' => array('path' => 'layouts', 'namespace' => 'Layout'),
        ));

        $resources = $this->loader->getResourceTypes();
        $this->assertNotContains('model', array_keys($resources));
        $this->assertNotContains('form', array_keys($resources));
        $this->assertContains('view', array_keys($resources));
        $this->assertContains('layout', array_keys($resources));
    }

    public function testHasResourceTypeShouldReturnFalseWhenTypeNotDefined(): void
    {
        $this->assertFalse($this->loader->hasResourceType('model'));
    }

    public function testHasResourceTypeShouldReturnTrueWhenTypeIsDefined(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->assertTrue($this->loader->hasResourceType('model'));
    }

    public function testRemoveResourceTypeShouldRemoveResourceFromList(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $this->loader->removeResourceType('form');

        $resources = $this->loader->getResourceTypes();
        $this->assertContains('model', array_keys($resources));
        $this->assertNotContains('form', array_keys($resources));
    }

    public function testAutoloaderShouldAllowSettingDefaultResourceType(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->loader->setDefaultResourceType('model');
        $this->assertEquals('model', $this->loader->getDefaultResourceType());
    }

    public function testSettingDefaultResourceTypeToUndefinedTypeShouldHaveNoEffect(): void
    {
        $this->loader->setDefaultResourceType('model');
        $this->assertNull($this->loader->getDefaultResourceType());
    }

    public function testLoadShouldRaiseExceptionWhenNotTypePassedAndNoDefaultSpecified(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->load('Foo');
    }

    public function testLoadShouldRaiseExceptionWhenResourceTypeDoesNotExist(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->load('Foo', 'model');
    }

    public function testLoadShouldReturnObjectOfExpectedClass(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $object = $this->loader->load('ZendLoaderAutoloaderResourceTest', 'model');
        $this->assertTrue($object instanceof FooBar_Model_ZendLoaderAutoloaderResourceTest);
    }

    public function testSuccessiveCallsToLoadSameResourceShouldReturnSameObject(): void
    {
        $this->loader->addResourceTypes(array(
            'form' => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $object = $this->loader->load('ZendLoaderAutoloaderResourceTest', 'form');
        $this->assertTrue($object instanceof FooBar_Form_ZendLoaderAutoloaderResourceTest);
        $test   = $this->loader->load('ZendLoaderAutoloaderResourceTest', 'form');
        $this->assertSame($object, $test);
    }

    public function testAutoloadShouldAllowEmptyNamespacing(): void
    {
        $loader = new Zend_Loader_Autoloader_Resource(array(
            'namespace' => '',
            'basePath'  => realpath(__DIR__ . '/_files'),
        ));
        $loader->addResourceTypes(array(
            'service' => array('path' => 'services', 'namespace' => 'Service'),
        ));
        $test = $loader->load('ZendLoaderAutoloaderResourceTest', 'service');
        $this->assertTrue($test instanceof Service_ZendLoaderAutoloaderResourceTest);
    }

    public function testPassingClassOfDifferentNamespaceToAutoloadShouldReturnFalse(): void
    {
        $this->assertFalse($this->loader->autoload('Foo_Bar_Baz'));
    }

    public function testPassingClassWithoutBothComponentAndClassSegmentsToAutoloadShouldReturnFalse(): void
    {
        $this->assertFalse($this->loader->autoload('FooBar_Baz'));
    }

    public function testPassingClassWithUnmatchedResourceTypeToAutoloadShouldReturnFalse(): void
    {
        $this->assertFalse($this->loader->autoload('FooBar_Baz_Bat'));
    }

    public function testMethodOverloadingShouldRaiseExceptionForNonGetterMethodCalls(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->lalalalala();
    }

    public function testMethodOverloadingShouldRaiseExceptionWhenRequestedResourceDoesNotExist(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->getModel('Foo');
    }

    public function testMethodOverloadingShouldRaiseExceptionWhenNoArgumentPassed(): void
    {
        $this->expectException(Zend_Loader_Exception::class);
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->loader->getModel();
    }

    public function testMethodOverloadingShouldReturnObjectOfExpectedType(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $test = $this->loader->getModel('ZendLoaderAutoloaderResourceMethodOverloading');
        $this->assertTrue($test instanceof FooBar_Model_ZendLoaderAutoloaderResourceMethodOverloading);
    }

    /**
     * @group ZF-7473
     */
    public function testAutoloaderShouldReceiveNamespaceWithTrailingUnderscore(): void
    {
        $al = Zend_Loader_Autoloader::getInstance();
        $loaders = $al->getNamespaceAutoloaders('FooBar');
        $this->assertTrue(empty($loaders));
        $loaders = $al->getNamespaceAutoloaders('FooBar_');
        $this->assertFalse(empty($loaders));
        $loader = array_shift($loaders);
        $this->assertSame($this->loader, $loader);
    }

    /**
     * @group ZF-7501
     */
    public function testAutoloaderShouldTrimResourceTypePathsForTrailingPathSeparator(): void
    {
        $this->loader->addResourceType('models', 'models/', 'Model');
        $resources = $this->loader->getResourceTypes();
        $this->assertEquals($this->loader->getBasePath() . '/models', $resources['models']['path']);
    }

    /**
     * @group ZF-6727
     */
    public function testAutoloaderResourceGetClassPath(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $path = $this->loader->getClassPath('FooBar_Model_Class_Model');
        // if true we have // in path
        $this->assertFalse(strpos($path, '//'));
    }

    /**
     * @group ZF-8364
     * @group ZF-6727
     */
    public function testAutoloaderResourceGetClassPathReturnFalse(): void
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $path = $this->loader->autoload('Something_Totally_Wrong');
        $this->assertFalse($path);
    }

    /**
     * @group ZF-10836
     */
    public function testConstructorAcceptsNamespaceKeyInAnyOrder(): void
    {
        // namespace is after resourceTypes - fails in ZF 1.11.1
        $data = array(
            'basePath'      => 'path/to/some/directory',
            'resourceTypes' => array(
                'acl' => array(
                    'path'      => 'acls/',
                    'namespace' => 'Acl',
                )
            ),
            'namespace'     => 'My'
        );
        $loader1 = new Zend_Loader_Autoloader_Resource($data);

        // namespace is defined before resourceTypes - always worked as expected
        $data = array(
            'basePath'      => 'path/to/some/directory',
            'namespace'     => 'My',
            'resourceTypes' => array(
                'acl' => array(
                    'path'      => 'acls/',
                    'namespace' => 'Acl',
                )
            )
        );
        $loader2 = new Zend_Loader_Autoloader_Resource($data);

        // Check that autoloaders are configured the same
        $this->assertEquals($loader1, $loader2);
    }

    /**
     * @group ZF-11219
     */
    public function testMatchesMultiLevelNamespaces(): void
    {
        $this->loader->setNamespace('Foo_Bar')
            ->setBasePath(__DIR__ . '/_files')
            ->addResourceType('model', 'models', 'Model');
        $path = $this->loader->getClassPath('Foo_Bar_Model_Baz');
        $this->assertEquals(__DIR__ . '/_files/models/Baz.php', $path, var_export($path, 1));
    }
}
