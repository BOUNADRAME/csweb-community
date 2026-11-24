<?php

namespace Tests\Unit\CSPro;

use AppBundle\CSPro\FileManager;
use AppBundle\CSPro\FileInfo;
use PHPUnit\Framework\TestCase;

class FileManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/csweb_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetDirectoryListingWithoutRootReturnsNull(): void
    {
        $fm = new FileManager();
        // rootFolder is null by default
        $this->assertNull($fm->getDirectoryListing('anything'));
    }

    public function testGetDirectoryListingEmptyDir(): void
    {
        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $result = $fm->getDirectoryListing('');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetDirectoryListingWithFiles(): void
    {
        file_put_contents($this->tempDir . '/hello.txt', 'world');

        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $result = $fm->getDirectoryListing('');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(FileInfo::class, $result[0]);
        $this->assertSame('hello.txt', $result[0]->name);
        $this->assertSame('file', $result[0]->type);
        $this->assertSame(md5('world'), $result[0]->md5);
        $this->assertSame(5, $result[0]->size);
    }

    public function testGetDirectoryListingDetectsSubdirectories(): void
    {
        mkdir($this->tempDir . '/subdir');

        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $result = $fm->getDirectoryListing('');
        $this->assertCount(1, $result);
        $this->assertSame('directory', $result[0]->type);
        $this->assertSame('subdir', $result[0]->name);
    }

    public function testPutFileCreatesFile(): void
    {
        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $info = $fm->putFile('test.txt', 'hello');
        $this->assertInstanceOf(FileInfo::class, $info);
        $this->assertSame('test.txt', $info->name);
        $this->assertSame('file', $info->type);
        $this->assertSame(md5('hello'), $info->md5);
        $this->assertSame(5, $info->size);
        $this->assertSame('hello', file_get_contents($this->tempDir . '/test.txt'));
    }

    public function testPutFileCreatesSubdirectories(): void
    {
        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $info = $fm->putFile('a/b/deep.txt', 'nested');
        $this->assertInstanceOf(FileInfo::class, $info);
        $this->assertSame('deep.txt', $info->name);
        $this->assertTrue(is_file($this->tempDir . '/a/b/deep.txt'));
    }

    public function testPutFileWithoutRootReturnsNull(): void
    {
        $fm = new FileManager();
        $this->assertNull($fm->putFile('test.txt', 'data'));
    }

    public function testGetFileInfoReturnsFileInfo(): void
    {
        file_put_contents($this->tempDir . '/existing.txt', 'content');

        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $info = $fm->getFileInfo('existing.txt');
        $this->assertInstanceOf(FileInfo::class, $info);
        $this->assertSame('existing.txt', $info->name);
        $this->assertSame('file', $info->type);
        $this->assertSame(md5('content'), $info->md5);
    }

    public function testGetFileInfoReturnsNullForMissing(): void
    {
        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $this->assertNull($fm->getFileInfo('nonexistent.txt'));
    }

    public function testGetFileInfoForDirectory(): void
    {
        mkdir($this->tempDir . '/mydir');

        $fm = new FileManager();
        $fm->rootFolder = $this->tempDir;

        $info = $fm->getFileInfo('mydir');
        $this->assertInstanceOf(FileInfo::class, $info);
        $this->assertSame('directory', $info->type);
        $this->assertFalse(property_exists($info, 'md5') && isset($info->md5));
    }
}
