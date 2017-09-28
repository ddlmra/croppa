<?php

namespace Bkwld\Croppa;

/**
 * Manage styles
 */
class Style
{
    /**
     * An array of all valid styles
     *
     * @var array
     */
    protected $valid = [];

    /**
     * An array of invalid styles names
     *
     * @var array
     */
    protected $invalid = [];

    /**
     * Default style. Combined with all styles
     *
     * @var array
     */
    protected $default = [
        'width'   => null,
        'height'  => null,
        'options' => [],
    ];

    /**
     * The combination of all valid styles
     *
     * @var array
     */
    protected $style;

    /**
     * Croppa general configuration
     *
     * @var array
     */
    protected $config;


    /**
     * List of options whose arguments should be merged instead of overridden
     *
     * @var array
     */
    protected $extendable = [
        'filters'
    ];

    /**
     * Style constructor.
     *
     * @param array $styles
     * @param array $config
     */
    public function __construct($config = []) {
        $this->config = $config;
    }

    /**
     * Check if a style has options
     *
     * @param string $name
     * @return bool
     */
    public function hasOptions($name) {
        return isset($this->config['styles'][$name]['options']);
    }

    /**
     * Set the styles to be combined
     *
     * @param array $styles
     */
    public function setStyles($styles = null) {
        if(! $styles) return;

        $this->style = $this->valid = $this->invalid = [];

        if (!empty ($this->config['styles'])) {
            if ( ! empty($this->config['styles']['default'])) {
                $this->default = array_merge($this->default, $this->config['styles']['default']);
            }
            $this->valid = $this->importStyles($this->filter($styles));
        }
        $this->invalid = array_diff($styles, array_keys($this->valid));
    }

    /**
     * Return an array of styles names and their implementation
     *
     * @param array $names
     * @return array
     */
    public function importStyles($names) {
        return array_combine($names, array_map(function ($name) {
            if( ! $this->isValid($name)) return [];
            return $this->config['styles'][$name];
        }, $names));
    }

    /**
     * Check if a style exists
     *
     * @param string $style
     * @return bool
     */
    public function isValid($style) {
        return !empty($this->config['styles'][$style]);
    }

    /**
     * Return an array containing only valid styles
     *
     * @param array $styles
     * @return array
     */
    public function filter($styles) {
        return array_filter($styles, [$this, 'isValid']);
    }

    /**
     * Generate the styles combination map to use with test command
     *
     * @return array
     */
    public function getMap() {
        $map = [];

        $map['width'] = [];
        $map['height'] = [];

        foreach ($this->buildStyle()['options'] as $option => $arguments) {
            if( ! in_array($option, $this->extendable)) {
                $map['options'][$option] = [];
            } else {
                foreach ($arguments as $argument) {
                    $map['options'][$option][$argument] = [];
                }
            }
        }

        $styles = array_merge(
            array_reverse($this->valid),
            ['default' => $this->default],
            ['config' => ['options' => [
                'quality'   => $this->config['jpeg_quality'],
                'interlace' => $this->config['interlace'],
                'upscale'   => $this->config['upscale'],
            ]]]
        );

        foreach ($styles as $name => $style) {
            if(array_key_exists('width', $style)) $map['width'][] = $name;
            if(array_key_exists('height', $style)) $map['height'][] =  $name;

            foreach ($style['options'] as $option => $arguments) {
                // If current option is extendable map the arguments
                if(in_array($option, $this->extendable, true)){
                    foreach ($arguments as $argument) {
                        $map['options'][$option][$argument][] = $name;
                    }
                    continue;
                }
                if(is_numeric($option)) {
                    $map['options'][$arguments][] = $name;
                    continue;
                }
                $map['options'][$option][] = $name;
            }
        }

        return $map;
    }

    /**
     * Same as Bkwld\Croppa\URL::styleOptions()
     *
     * @return array
     */
    public function buildStyle() {
        $style = $this->getStyle();
        unset($style['options']);

        foreach ($this->getStyle()['options'] as $option => $arguments) {
            if (is_numeric($option)) {
                $style['options'][$arguments] = null;
            } elseif ( ! is_array($arguments)) {
                $style['options'][$option] = [$arguments];
            } else {
                $style['options'][$option] = $arguments;
            }
        }
        return $style;
    }

    /**
     * Return the combined style
     *
     * @return array
     */
    public function getStyle() {
        if(!empty($this->style)) return $this->style;

        return $this->combine($this->valid);
    }

    /**
     * Loop through all styles and combine them in a new resulting style
     *
     * @return array
     */
    protected function combine($styles)
    {
        if(!empty($this->style)) return $this->style;

        $this->style = array_reduce($styles, function ($combined, $style) {
            // If the current style is empty skip to the next iteration
            if (empty($style)) {
                return $combined;
            }

            // If the result of the previous iteration is an empty array
            // simply return the current combined style
            if (empty($combined)) {
                return $style;
            }
            $tmp = array_merge($combined, $style);

            if (isset($combined['options']) && isset($style['options'])) {
                $tmp['options'] = array_merge($combined['options'], $style['options']);

                // Remove duplicate number keyed options. Like "pad" or "resize"
                foreach ($tmp['options'] as $index => $option) {
                    if(is_numeric($index)) {
                        if (isset($combined['options'][$option])) {
                            unset($tmp['options'][$option]);
                            continue;
                        }elseif (isset($style['options'][$option]) || count(array_keys($tmp['options'], $option, true))>1) {
                            unset($tmp['options'][$index]);
                        }
                    }
                }

                // loop through options whose arguments should be merged instead of overridden
                foreach ($this->extendable as $option) {
                    if (isset($combined['options'][$option]) && isset($style['options'][$option])) {
                        $tmp['options'][$option] = array_unique(array_merge($combined['options'][$option], $style['options'][$option]));
                    }
                }
            }
            return $tmp;
        }, $this->default);
        return $this->style;
    }

    /**
     * Return the valid styles
     *
     * @return array
     */
    public function getValid() {
        return $this->valid;
    }

    /**
     * Return the invalid styles
     *
     * @return array
     */
    public function getInvalid() {
        return $this->invalid;
    }
}