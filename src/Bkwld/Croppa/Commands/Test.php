<?php namespace Bkwld\Croppa\Commands;

// Deps
use Bkwld\Croppa\Storage;
use Bkwld\Croppa\Style;
use Bkwld\Croppa\URL;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Delete ALL crops from the crops_dir
 */
class Test extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'croppa:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test crops styles';

    /**
     * The stile being tested
     *
     * @var Style
     */
    protected $style;

    /**
     * Croppa general configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Corppa URL instance
     *
     * @var URL
     */
    protected $url;

    /**
     * Default values to display when option parameters are null
     *
     * @var array
     */
    protected $defaults = [
        'resize' => true,
        'pad'    => [255, 255, 255],
    ];

    /**
     * Dependency inject
     *
     * @param Storage $storage
     */
    public function __construct($config = [], URL $url) {
        parent::__construct();
        $this->config = $config;
        $this->url = $url;
        $this->style = new Style($config);
    }

    /**
     * Execute the console command for laravel <=5.4
     *
     * @return void
     */
    public function fire() {
        $this->handle();
    }

    /**
     * Execute the console command for laravel >=5.5
     *
     * @return void
     */
    public function handle() {
        $names = explode(',', $this->input->getArgument('styles'));
        $this->style->setStyles($names);

        $style = $this->style->buildStyle();
        $style['options'] = array_merge([
            'quality'   => [$this->config['jpeg_quality']],
            'interlace' => [$this->config['interlace']],
            'upscale'   => [$this->config['upscale']],
        ], $style['options']);
        $this->styleTable($style, $this->style->getMap());

        $this->generateUrls($names, $this->style->getStyle());

        $this->invalidStyles($this->style);
    }

    /**
     * Format values for console output
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value) {
        if (is_numeric($value)) {
            return "<fg=cyan>{$value}</>";
        }
        if (is_null($value)) {
            return "<fg=magenta>null</>";
        }
        if (is_bool($value)) {
            return '<fg=blue>' . ($value ? 'true' : 'false') . '</>';
        }

        return (string) $value;
    }

    /**
     * Format styles for console output
     *
     * @param string $style
     * @return string
     */
    protected function formatStyle($style) {
        switch ($style){
            case 'default':
            case 'config':
                return "<comment>{$style}</comment>";
            default:
                return $style;
        }
    }

    /**
     * Format an array of values and implode to string
     *
     * @param array $values
     * @param string $glue
     * @return string
     */
    protected function implodeValues($values, $option, $glue=', ') {
        $values = (is_null($values) && isset($this->defaults[$option])) ? $this->defaults[$option] : $values;
        if (!is_array($values)) return $this->formatValue($values);
        return implode($glue, array_map(function ($val) {
            return $this->formatValue($val);
        }, $values));
    }

    /**
     * Format an array of styles and implode to string
     *
     * @param array $styles
     * @param string $glue
     * @return string
     */
    protected function implodeStyles($styles, $glue=', ') {
        return implode($glue, array_map(function ($val) {
            return $this->formatStyle($val);
        }, $styles));
    }

    /**
     * Get the console command options
     *
     * @return array;
     */
    protected function getOptions() {
        return [
            ['url', null, InputOption::VALUE_REQUIRED, 'The url to test with', null],
        ];
    }

    /**
     * Get the console command arguments
     *
     * @return array;
     */
    protected function getArguments() {
        return [
            ['styles', InputArgument::REQUIRED, 'The styles to test', null],
        ];
    }

    /**
     * Loop through the styles map and outputs a table to the console
     *
     * @param array $style
     * @param array $map
     */
    protected function styleTable($style, $map) {
        $headers = ['Instruction', 'Value', 'From', 'Overrides'];
        $instructions = [
            ['width', $this->formatValue($style['width']), $this->formatStyle(array_shift($map['width'])), $this->implodeStyles($map['width'])],
            new TableSeparator(),
            ['height', $this->formatValue($style['height']), $this->formatStyle(array_shift($map['height'])), $this->implodeStyles($map['height'])],
        ];
        foreach ($map['options'] as $option => $styles) {
            $instructions[] = new TableSeparator();
            if (isset($styles[0])) {
                // If keys are numeric it means we already have a list of styles
                $instructions[] = [$option, $this->implodeValues($style['options'][$option], $option), $this->formatStyle(array_shift($map['options'][$option])), $this->implodeStyles($map['options'][$option])];
            } else {
                // If keys are not numeric loop through option arguments
                $first = true;
                foreach ($styles as $key => $val) {
                    if ($first) {
                        $instructions[] = [$option, $this->implodeValues($key, $option), $this->formatStyle(array_shift($val)), $this->implodeStyles($val)];
                        $first = false;
                    } else {
                        $instructions[] = [null, $this->implodeValues($key, $option), $this->formatStyle(array_shift($val)), $this->implodeStyles($val)];
                    }
                }
            }
        };
        $this->table($headers, $instructions);
    }

    /**
     * Generate URLs and output to the console
     *
     * @param array $names
     * @param array $style
     */
    protected function generateUrls($names, $style) {
        $path = $this->input->getOption('url') ?: 'path/to/image/file.jpg';
        $this->line('');
        $this->info('Generated URL:');
        $this->line(' ' . $this->url->generate($path, $names));
        $this->comment(' Equivalent of:');
        $this->line(' ' . $this->url->generate($path, $style['width'], $style['height'], $style['options']));
    }

    /**
     * List all invalid styles (if any) to the console output
     *
     * @param Style $style
     */
    protected function invalidStyles($style) {
        if ( ! empty($style->getInvalid())) {
            $this->line('');
            $this->error('The following styles are not defined and will be ignored when rendering');
            foreach ($this->style->getInvalid() as $invalid) {
                $this->comment("  $invalid");
            }
        }
    }

}
