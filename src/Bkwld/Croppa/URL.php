<?php namespace Bkwld\Croppa;

/**
 * Appends and parses params of URLs
 */
class URL {

    const PATTERN = '(?P<url>.+?)-(?:(?P<w>[0-9_]+)x(?P<h>[0-9_]+)(?P<o>-[0-9a-zA-Z(),\-._]+)*|(?P<s>[a-zA-Z0-9_+]+))\.(?P<type>jpg|jpeg|png|gif|JPG|JPEG|PNG|GIF)$';
    /**
     * The pattern used to identify a request path as a Croppa-style URL
     * https://github.com/BKWLD/croppa/wiki/Croppa-regex-pattern //ToDo Update Wiki
     *
     * @return string
     */
    const INSTRUCTIONS_PATTERN = '-(?P<w>[0-9_]+)x(?P<h>[0-9_]+)(?P<o>-[0-9a-zA-Z(),\-._]+)*';

    /**
     * The pattern used to identify a request path as a "styles" URL
     *
     * @return string
     */
    const STYLES_PATTERN = '(?P<s>(?:%s|%s)+)';

    /**
     * The pattern used to match the supported file types.
     *
     * @return string
     */
    const TYPES_PATTERN = '\.(?P<type>jpg|jpeg|png|gif|JPG|JPEG|PNG|GIF)$';

    /**
     * The patterns used to identify a request path as a Croppa-style URL
     * Capturing groups:
     * <w>    => width
     * <h>    => height
     * <o>    => options
     * <s>    => styles
     * <type> => file types
     *
     * https://github.com/BKWLD/croppa/wiki/Croppa-regex-pattern //ToDo Update Wiki
     *
     * @var array
     */
    private $patterns = [
        // Pattern used to identify URLs with "file-{width}x{height}-{options}.jpg" format
        'instructions' => '-(?P<w>[0-9_]+)x(?P<h>[0-9_]+)(?P<o>-[0-9a-zA-Z(),\-._]+)*',

        // Pattern used to identify URLs with "file-{style1}+{style2}+{styleN}.jpg" format
        // %s to be replaced with $config['styles_separator']
        'styles'  => '(?P<s>(?:[a-zA-z][a-zA-Z0-9%s]*)+)',

        // The supported files types
        'types'   => '\.(?P<type>jpg|jpeg|png|gif|JPG|JPEG|PNG|GIF)$',
    ];

    /**
     * The pattern composed basing on 'use_styles' and 'styles_only' configs
     *
     * @var string
     */
    private $pattern;

    /**
     * Croppa general configuration
     *
     * @var array
     */
    private $config = [
        'use_styles'        => true,
        'styles_only'       => false,
        'styles_delimiters' => ['-', ''],
        'styles_separator'  => '+',
    ];

    /**
     * Resolved combination of styles
     *
     * @var Style
     */
    private $style;

    /**
     * Inject dependencies
     *
     * @param array $config
     */
    public function __construct($config = []) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Insert Croppa parameter suffixes into a URL.  For use as a helper in views
     * when rendering image src attributes.
     *
     * @param string $url URL of an image that should be cropped
     * @param integer|array|string $width Target width. If array or string is used for styles
     * @param integer $height Target height
     * @param array $options Additional Croppa options, passed as key/value pairs.  Like array('resize')
     * @return string The new path to your thumbnail
     */
    public function generate($url, $width = null, $height = null, $options = null) {
        // Extract the path from a URL and remove it's leading slash
        $path = $this->toPath($url);

        // Skip croppa requests for images the ignore regexp
        if (isset($this->config['ignore'])
            && preg_match('#'.$this->config['ignore'].'#', $path)) {
            return $this->pathToUrl($path);
        }

        // Defaults
        if (empty($path)) return; // Don't allow empty strings
        if (!$width && !$height) return $this->pathToUrl($path); // Pass through if empty

        // If $width is not a number and not null it is a styles list
        if ($width && ! is_numeric($width)) {
            $suffix = $this->config['styles_delimiters'][0] . (is_string($width) ? $width : implode($this->config['styles_separator'], $width)) . $this->config['styles_delimiters'][1];
        } else {
            $width = $width ? round($width) : '_';
            $height = $height ? round($height) : '_';

            // Produce width, height, and options
            $suffix = '-'.$width.'x'.$height;
            if ($options && is_array($options)) {
                foreach($options as $key => $val) {
                    if (is_numeric($key)) $suffix .= '-'.$val;
                    elseif (is_array($val)) $suffix .= '-'.$key.'('.implode(',',$val).')';
                    elseif(is_bool($val)) $suffix .= '-'.$key.'('.($val?1:0).')';
                    else $suffix .= '-'.$key.'('.$val.')';
                }
            }
        }

        // Assemble the new path
        $parts = pathinfo($path);
        $path = trim($parts['dirname'],'/').'/'.$parts['filename'].$suffix;
        if (isset($parts['extension'])) $path .= '.'.$parts['extension'];
        $url = $this->pathToUrl($path);

        // Secure with hash token
        if ($token = $this->signingToken($url)) $url .= '?token='.$token;

        // Return the $url
        return $url;
    }

    /**
     * Extract the path from a URL and remove it's leading slash
     *
     * @param string $url
     * @return string path
     */
    public function toPath($url) {
        return ltrim(parse_url($url, PHP_URL_PATH), '/');
    }

    /**
     * Append host to the path if it was defined
     *
     * @param string $path Request path (with leading slash)
     * @return string
     */
    public function pathToUrl($path) {
        if (empty($this->config['url_prefix'])) return '/'.$path;
        else if (empty($this->config['path'])) return rtrim($this->config['url_prefix'], '/').'/'.$path;
        else return rtrim($this->config['url_prefix'], '/').'/'.$this->relativePath($path);
    }

    /**
     * Generate the signing token from a URL or path.  Or, if no key was defined,
     * return nothing.
     *
     * @param string path or url
     * @return string|void
     */
    public function signingToken($url) {
        if (isset($this->config['signing_key'])
            && ($key = $this->config['signing_key'])) {
            return md5($key.basename($url));
        }
    }

    /**
     * Make the regex for the route definition.  This works by wrapping both the
     * basic Croppa pattern and the `path` config in positive regex lookaheads so
     * they working like an AND condition.
     * https://regex101.com/r/kO6kL1/1
     *
     * In the Laravel router, this gets wrapped with some extra regex before the
     * matching happens and for the pattern to match correctly, the final .* needs
     * to exist.  Otherwise, the lookaheads have no length and the regex fails
     * https://regex101.com/r/xS3nQ2/1 -> https://regex101.com/r/IBdqNk/1 (with styles implementation)
     *
     * @return string
     */
    public function routePattern() {
        return sprintf("(?=%s)(?=%s).+", $this->config['path'], $this->getPattern());
    }

    /**
     * Set the regex pattern based on the configuration
     * Capturing groups:
     * <url> => path to source image (without file extension)
     *
     * @return string
     */
    public function getPattern()
    {
        if (isset($this->pattern)) {
            return $this->pattern;
        }

        if ( ! $this->config['use_styles']) {
            return $this->pattern = '(?P<url>.+)' . $this->patterns['instructions'] . $this->patterns['types'];
        }

        $stylePattern = preg_quote($this->config['styles_delimiters'][0])
            . sprintf($this->patterns['styles'], preg_quote($this->config['styles_separator']))
            . preg_quote($this->config['styles_delimiters'][1]);

        if ($this->config['styles_only']) {
            return $this->pattern = '(?P<url>.+)' . $stylePattern . $this->patterns['types'];
        }

        return $this->pattern = '(?P<url>.+?)(?:' . $this->patterns['instructions'] . '|' . $stylePattern . ')' . $this->patterns['types'];
    }

    /**
     * Parse a request path into Croppa instructions
     *
     * @param string $request
     * @return array | boolean
     */
    public function parse($request) {
        if (!preg_match('#'.$this->getPattern().'#', $request, $matches)) return false;

        $path = $this->relativePath($matches['url'].'.'.$matches['type']);  // Path

        if ( ! empty($matches['s'])) {
            $style = $this->getStyle($this->parseStyles($matches['s']));

            return [
                $path,
                $style['width'],
                $style['height'],
                $this->options($style['options']),
            ];
        }

        return [
            $path,
            $matches['w'] == '_' ? null : (int) $matches['w'],  // Width
            $matches['h'] == '_' ? null : (int) $matches['h'],  // Height
            $this->options($matches['o']),                          // Options
        ];
    }

    /**
     * Take a URL or path to an image and get the path relative to the src and
     * crops dirs by using the `path` config regex
     *
     * @param string $url url or path
     * @return string
     */
    public function relativePath($url) {
        $path = $this->toPath($url);
        if (!preg_match('#'.$this->config['path'].'#', $path, $matches)) {
            throw new Exception("$url doesn't match `{$this->config['path']}`");
        }
        return $matches[1];
    }

    /**
     * Create options array where each key is an option name
     * and the value if an array of the passed arguments
     *
     * @param  string|array $option_params Options string in the Croppa URL style or array of options
     * @return array
     */
    public function options($option_params) {
        $options = array();

        // If options is a string convert it into key value pairs
        if(is_string($options)) {
            // These will look like: "-quadrant(T)-resize"
            $option_params = explode('-', $option_params);

            // Loop through the params and make the options key value pairs
            foreach($option_params as $option) {
                if (!preg_match('#(\w+)(?:\(([\w,.]+)\))?#i', $option, $matches)) continue;
                if (isset($matches[2])) $options[$matches[1]] = explode(',', $matches[2]);
                else $options[$matches[1]] = null;
            }
        } else {
            $options = $this->styleOptions($option_params);
        }

        // Map filter names to filter class instances or remove the config.
        $options['filters'] = $this->buildfilters($options);
        if (empty($options['filters'])) unset($options['filters']);

        // Return new options array
        return $options;
    }

    /**
     * Parse style options array and returns an array where each key is an option name
     * and the value is an array of the passed arguments
     *
     * @param $option_params
     * @param $options
     * @return mixed
     */
    private function styleOptions($option_params) {
        $options = [];
        foreach ($option_params as $option => $arguments) {
            if (is_numeric($option)) {
                $options[$arguments] = null;
            } elseif ( ! is_array($arguments)) {
                $options[$option] = [$arguments];
            } else {
                $options[$option] = $arguments;
            }
        }
        return $options;
    }

    /**
     * Build filter class instances
     *
     * @param  array $options
     * @return array|null Array of filter instances
     */
    public function buildFilters($options) {
        if (empty($options['filters']) || !is_array($options['filters'])) return [];
        return array_filter(array_map(function($filter) {
            if (empty($this->config['filters'][$filter])) return;
            return new $this->config['filters'][$filter];
        }, $options['filters']));
    }

    /**
     * Return an array of styles names
     *
     * @param string $styles
     * @return array
     */
    public function parseStyles($styles) {
        return explode($this->config['styles_separator'], $styles);
    }

    /**
     * Return the combined style from a list of styles
     *
     * @param string|array $styles list of style names
     * @return array
     */
    public function getStyle($styles = null) {
        if(!$this->style) $this->style = new Style($this->config);
        $this->style->setStyles($styles);

        return $this->style->getStyle();
    }

    /**
     * Take options in the URL and options from the config file and produce a
     * config array in the format that PhpThumb expects
     *
     * @param array $options The url options from `parseOptions()`
     * @return array
     */
    public function phpThumbConfig($options) {
        return [
            'jpegQuality' => isset($options['quality']) ? $options['quality'][0] : $this->config['jpeg_quality'],
            'interlace' => isset($options['interlace']) ? $options['interlace'][0] : $this->config['interlace'],
            'resizeUp' => isset($options['upscale']) ? $options['upscale'][0] : $this->config['upscale'],
        ];
    }

}
