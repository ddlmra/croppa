<?php

use Bkwld\Croppa\Storage;
use Bkwld\Croppa\URL;
use PHPUnit\Framework\TestCase;

class TestListAllCrops extends TestCase {

	public function setUp() {

	    $url= new URL([

        ]);

		// Mock src dir
		$this->src_dir = Mockery::mock('League\Flysystem\Filesystem')
			->shouldReceive('has')->with('01/me.jpg')->andReturn(true)
			->shouldReceive('has')->with('02/another.jpg')->andReturn(true)
			->shouldReceive('has')->with('03/ignore.jpg')->andReturn(false)
			->getMock();

		// Mock crops dir
		$this->crops_dir = Mockery::mock('League\Flysystem\Filesystem')
			->shouldReceive('listContents')
			->withAnyArgs()
			->andReturn([
					['path' => '01/me.jpg', 'basename' => 'me.jpg'],
					['path' => '01/me-too.jpg', 'basename' => 'me-too.jpg'],
					['path' => '01/me-200x100.jpg', 'basename' => 'me-200x100.jpg'],
					['path' => '01/me-200x200.jpg', 'basename' => 'me-200x200.jpg'],
					['path' => '01/me-200x300.jpg', 'basename' => 'me-200x300.jpg'],

					// Stored in another src dir
					['path' => '02/another.jpg', 'basename' => 'another-200x300.jpg'],
					['path' => '02/another-200x300.jpg', 'basename' => 'another-200x300.jpg'],
					['path' => '02/unrelated.jpg', 'basename' => 'unrelated.jpg'],

					// Not a crop cause there is no corresponding source file
					['path' => '03/ignore-200x200.jpg', 'basename' => 'ignore-200x200.jpg'],
				])
			->getMock();

		$this->app = ['Bkwld\Croppa\URL' => $url];
	}

	public function testAll() {
        $url= new URL();
	    $app = ['Bkwld\Croppa\URL' => $url];

		$storage = new Storage($app);
		$storage->setSrcDisk($this->src_dir);
		$storage->setCropsDisk($this->crops_dir);
		$this->assertEquals([
			'01/me-200x100.jpg',
			'01/me-200x200.jpg',
			'01/me-200x300.jpg',
			'02/another-200x300.jpg',
		], $storage->listAllCrops());
	}

    public function testAllWithStyles() {
        $url = new URL(['styles' => ['small' => ['width' => 300]]]);
        $app = ['Bkwld\Croppa\URL' => $url];

        $storage = new Storage($app);
        $storage->setSrcDisk($this->src_dir);
        $storage->setCropsDisk($this->crops_dir);
        $this->assertEquals([
            '01/me-too.jpg',
            '01/me-200x100.jpg',
            '01/me-200x200.jpg',
            '01/me-200x300.jpg',
            '02/another-200x300.jpg',
        ], $storage->listAllCrops());
    }

    public function testAllWithStylesOnly() {
        $url = new URL([
            'styles_only' => true,
            'styles' => ['small' => ['width' => 300]]]);
        $app = ['Bkwld\Croppa\URL' => $url];

        $storage = new Storage($app);
        $storage->setSrcDisk($this->src_dir);
        $storage->setCropsDisk($this->crops_dir);
        $this->assertEquals([
            '01/me-too.jpg',
        ], $storage->listAllCrops());
    }

	public function testFiltered() {
		$storage = new Storage($this->app);
		$storage->setSrcDisk($this->src_dir);
		$storage->setCropsDisk($this->crops_dir);
		$this->assertEquals([
			'02/another-200x300.jpg',
		], $storage->listAllCrops('^02/'));
	}

	public function tearDown() {
		Mockery::close();
	}

}
