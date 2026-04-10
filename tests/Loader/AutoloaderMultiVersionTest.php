<?php

use PHPUnit\Framework\TestCase;

class Zend_Loader_AutoloaderMultiVersionTest extends TestCase
{
    public function testAllMultiVersionTestsSkipped(): void
    {
        $this->markTestSkipped('Multi-version tests require TESTS_ZEND_LOADER_AUTOLOADER_MULTIVERSION_ENABLED constant');
    }
}
