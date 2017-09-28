<?php

use Bkwld\Croppa\Style;
use Bkwld\Croppa\URL;
use PHPUnit\Framework\TestCase;

class TestUrlParsing extends TestCase {

    protected $style;
    private $url;
	public function setUp() {

        $config = [
            'path'    => 'uploads/(.*)$',
            'filters' => [
                'gray'      => Bkwld\Croppa\Filters\BlackWhite::class,
                'darkgray'  => Bkwld\Croppa\Filters\Darkgray::class,
                'blur'      => Bkwld\Croppa\Filters\Blur::class,
                'negative'  => Bkwld\Croppa\Filters\Negative::class,
                'orange'    => Bkwld\Croppa\Filters\OrangeWarhol::class,
                'turquoise' => Bkwld\Croppa\Filters\TurquoiseWarhol::class,
            ],
            'styles'  => [
                'small' => [
                    'height'  => 200,
                    'options' => [
                        'quadrant' => ['T', 'R'],
                        'filters'  => [
                            'gray',
                            'blur'
                        ],
                        'quality'  => 80,
                        'pad' => [1,2,3]
                    ],
                ],
                'thumb' => [
                    'options' => [
                        'quadrant' => 'C',
                        'quality'  => 50,
                        'filters'  => [
                            'blur',

                        ],
                        'pad'
                    ],
                ],
            ],
        ];
        $this->url = new URL($config);
        $this->style = new Style($config);
	}

	public function testNoParams() {
		$this->assertFalse($this->url->parse('uploads/1/2/file.jpg'));
	}

	public function testWidth() {
		$this->assertEquals([
			'1/2/file.jpg', 200, null, []
		], $this->url->parse('uploads/1/2/file-200x_.jpg'));
	}

	public function testHeight() {
		$this->assertEquals([
			'1/2/file.jpg', null, 100, []
		], $this->url->parse('uploads/1/2/file-_x100.jpg'));
	}

	public function testWidthAndHeight() {
		$this->assertEquals([
			'1/2/file.jpg', 200, 100, []
		], $this->url->parse('uploads/1/2/file-200x100.jpg'));
	}

	public function testWidthAndHeightAndOptions() {
		$this->assertEquals([
			'1/2/file.jpg', 200, 100, ['resize' => null]
		], $this->url->parse('uploads/1/2/file-200x100-resize.jpg'));
	}

	public function testWidthAndHeightAndOptionsWithValue() {
		$this->assertEquals([
			'1/2/file.jpg', 200, 100, ['quadrant' => ['T']]
		], $this->url->parse('uploads/1/2/file-200x100-quadrant(T).jpg'));
	}

	public function testWidthAndHeightAndOptionsWithValueList() {
		$this->assertEquals([
			'1/2/file.jpg', 200, 100, ['trim_perc' => [0.25,0.25,0.75,0.75]]
		], $this->url->parse('uploads/1/2/file-200x100-trim_perc(0.25,0.25,0.75,0.75).jpg'));
	}

	public function testFilters() {
		$this->assertEquals([
			'1/2/file.jpg', 200, 100, ['filters' => [
				new Bkwld\Croppa\Filters\Blur,
				new Bkwld\Croppa\Filters\Negative,
			]]
		], $this->url->parse('uploads/1/2/file-200x100-filters(blur,negative).jpg'));
	}

	public function testCropsInSubDirectory() {
		$url = new URL([
			'path' => 'images/(?:crops/)?(.*)$',
		]);
		$this->assertEquals([
			'file.jpg', 200, 100, []
		], $url->parse('images/crops/file-200x100.jpg'));
	}

    public function testStyles() {
        /*$this->assertEquals([
            '1/2/file.jpg',
            null,
            200,
            [
                'quadrant' => ['T', 'R'],
                'filters'  => [
                    new Bkwld\Croppa\Filters\BlackWhite,
                    new Bkwld\Croppa\Filters\Blur,
                ],
                'quality'  => [80],
            ],
        ], $this->url->parse('uploads/1/2/file-small.jpg'));*/

        $this->assertEquals([
            'hanagaki-junmai-umeshu.jpg',
            null,
            200,
            [
                'quadrant' => ['C'],
                'filters'  => [
                    new Bkwld\Croppa\Filters\BlackWhite,
                    new Bkwld\Croppa\Filters\Blur,
                ],
                'quality'  => [50],
                'pad'      => null,
            ],
        ], $this->url->parse('/uploads/hanagaki-junmai-umeshu-small+thumb.jpg'));
    }

    public function testStylesConflict() {
        $this->assertEquals([
            '1/2/file.jpg', 200, 300, [],
        ], $this->url->parse('uploads/1/2/file-200x300.jpg'));
    }

    public function testBuildStyle()
    {
        $this->assertEquals([
            'hanagaki-junmai-umeshu.jpg',
            null,
            200,
            [
                'quadrant' => ['C'],
                'filters'  => [
                    new Bkwld\Croppa\Filters\BlackWhite,
                    new Bkwld\Croppa\Filters\Blur,
                ],
                'quality'  => [50],
                'pad'      => [1,2,3],
            ],
        ], $this->url->parse('/uploads/hanagaki-junmai-umeshu-small+thumb.jpg'));
    }

    public function testMap()
    {
        $this->style->setStyles(['small','thumb']);
        // $map = $this->style->getMap();
        $this->assertEquals([
            'hanagaki-junmai-umeshu.jpg',
            null,
            200,
            [
                'quadrant' => ['C'],
                'filters'  => [
                    new Bkwld\Croppa\Filters\BlackWhite,
                    new Bkwld\Croppa\Filters\Blur,
                ],
                'quality'  => [50],
                'pad'      => null,
            ],
        ], $this->style->getMap());
    }
}
