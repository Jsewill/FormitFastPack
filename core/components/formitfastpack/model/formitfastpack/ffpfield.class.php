<?php
/**
 * @package:
 * @author: Oleg Pryadko (oleg@websitezen.com)
 * @createdon: 3/28/12
 * @license: GPL v.3 or later
 */
class ffpField {
    /** @var FormitFastPack A reference to the FormitFastPack object. */
    public $ffp = null;
    /** @var modX A reference to the modX object. */
    public $modx = null;
    /** @var array A collection of properties to adjust behaviour. */
    public $config = array();
    /** @var array A collection of defaults */
    public $defaults = array();
    public $html = array();
    public $placeholders = array();
    public $double_processing_needed = false;

    function __construct(FormitFastPack &$ffp, array $config = array()) {
        $this->ffp =& $ffp;
        $this->modx =& $ffp->modx;
        $this->config = $config;
        $cache_default = $this->modx->getOption('ffp.field_default_cache', null, 'auto');
        $custom_ph_default = $this->modx->getOption('ffp.custom_ph', null, 'class,multiple,array,header,default,class,outer_class,label,note,note_class,size,title,req,message,clear_message');
        $defaults = array(
            'debug' => false,
            'cache' => $cache_default,
            'name' => '',
            'type' => '',
            'outer_type' => '',
            'prefix' => 'fi.',
            'error_prefix' => 'fi.error.',
            'key_prefix' => '',
            // delimiter each field type is bordered by.
            // example: <!-- textarea --> <input type="textarea" name="[[+name]]">[[+current_value]]</input> <!-- textarea -->
            'delimiter_template' => '<!-- [[+type]] -->',
            'default_delimiter' => 'default',
            'outer_tpl' => 'fieldWrapTpl',
            // The main template (contains all field types separated by the delimiter)
            'tpl' => 'fieldTypesTpl',
            'options' => '',
            'options_delimiter' => '||',
            'options_inner_delimiter' => '==',
            'option_type' => '',
            'selected_text' => '',
            'custom_ph' => $custom_ph_default,
            'set_type_ph' => 'text,textarea,checkbox,radio,select',
            // inner and options should be identical
            'options_html' => '',
            'options_element' => '',
            'options_element_class' => 'modChunk',
            'options_element_properties' => '[]',
            'inner_html' => '',
            'inner_element' => '',
            'inner_element_class' => 'modChunk',
            'inner_element_properties' => '[]',
            'use_get' => false,
            'use_request' => false,
            'use_cookies' => false,
            'error_class' => 'error',
            'mark_selected' => true,
            'to_placeholders' => false,
        );
        $this->defaults = $defaults;
    }

    public function setOption($key, $value) {
        $this->config[$key] = $value;
    }

    public function setSettings(array $settings) {
        foreach ($this->defaults as $key => $default) {
            $this->config[$key] = $this->modx->getOption($key, $settings, $default);
        }
        $this->calculateConfig();
    }

    public function calculateConfig() {
        $options = array();

        // delimiters
        $this->config['delimiter'] = str_replace('[[+type]]', $this->config['type'], $this->config['delimiter_template']);
        $this->config['default_delimiter'] = str_replace('[[+type]]', $this->config['default_delimiter'], $this->config['delimiter_template']);
        // default to the field type for outer type. If the delimiter is not found, it will use the default delimiter. If the default delimiter is not found, it will use the entire outer_tpl.
        $this->config['outer_delimiter'] = empty($this->config['outer_type']) ? $this->config['delimiter'] : str_replace('[[+type]]', $this->config['outer_type'], $this->config['delimiter_template']);

        // For checkboxes, radios, selects, etc... that require inner fields, parse options
        // Set defaults for the options of certain field types and allow to override from a system settings JSON array
        $inner_static = $this->modx->fromJSON($this->modx->getOption('ffp.inner_options_static', null, '[]'));
        if (empty($inner_static)) {
            $inner_static = array();
            $inner_static['bool'] = array('option_tpl' => 'bool', 'selected_text' => ' checked="checked"');
            $inner_static['checkbox'] = array('option_tpl' => 'bool', 'selected_text' => ' checked="checked"');
            $inner_static['radio'] = array('option_tpl' => 'bool', 'selected_text' => ' checked="checked"');
            $inner_static['select'] = array('option_tpl' => 'option', 'selected_text' => ' selected="selected"');
        }
        $inner_static['default'] = isset($inner_static['default']) ? $inner_static['default'] : array('option_tpl' => '', 'selected_text' => ' checked="checked" selected="selected"');
        // options templates
        $this->config['default_option_tpl'] = isset($inner_static[$this->config['type']]['option_tpl']) ? $inner_static[$this->config['type']]['option_tpl'] : $inner_static['default']['option_tpl'];
        $this->config['default_selected_text'] = isset($inner_static[$this->config['type']]['selected_text']) ? $inner_static[$this->config['type']]['selected_text'] : $inner_static['default']['selected_text'];
        $this->config['inner_static'] = $inner_static;

        /*      CACHING         */
        // See if caching is set system-wide or in the scriptProperties
        $cache = $this->config['cache'];
        // By default, only cache elements that have options.
        if ($cache == 'auto') {
            $auto_cache = (array_key_exists($this->config['type'], $this->config['inner_static']) || $this->config['options'] || $this->config['options_element'] || $this->config['inner_element']);
            $cache = $auto_cache ? 1 : 0;
            // temporarily set auto_cach to always 1
            $cache = true;
        }
        $this->config['cache'] = ($cache && $this->modx->getCacheManager()) ? $cache : false;

        // Allow overriding the default settings for types from the script properties
        $this->config['option_tpl'] = $this->config['option_type'] ? $this->config['option_type'] : $this->config['default_option_tpl'];
        $this->config['selected_text'] = $this->config['selected_text'] ? $this->config['selected_text'] : $this->config['default_selected_text'];

        // used in variable calcs
        $this->config['error_prefix'] = $this->config['error_prefix'] ? $this->config['error_prefix'] : $this->config['prefix'] . 'error.';

        // generate unique key
        $this->config['key'] = preg_replace("/[^a-zA-Z0-9_-]/", "", $this->config['key_prefix'] . $this->config['name']);
    }

    public function calculateCacheConfig() {
        if (empty($this->config['cacheKey'])) $this->config['cacheKey'] = $this->modx->getOption('cache_resource_key', null, 'resource');
        if (empty($this->config['cacheHandler'])) {
            $cache_resource_handler_default = $this->modx->getOption(xPDO::OPT_CACHE_HANDLER, null, 'xPDOFileCache');
            $this->config['cacheHandler'] = $this->modx->getOption('cache_resource_handler', null, $cache_resource_handler_default);
        }
        if (!isset($this->config['cacheExpires'])) {
            $cache_resource_expires_default = $this->modx->getOption(xPDO::OPT_CACHE_EXPIRES, null, 0);
            $this->config['cacheExpires'] = (integer)$this->modx->getOption('cache_resource_expires', null, $cache_resource_expires_default);
        }
        if (empty($this->config['cacheElementKey'])) $this->config['cacheElementKey'] = $this->modx->resource->getCacheKey() . '/' . md5($this->modx->toJSON($this->config) . implode('', $this->modx->request->getParameters()));
        $this->config['cacheOptions'] = array(
            xPDO::OPT_CACHE_KEY => $this->config['cacheKey'],
            xPDO::OPT_CACHE_HANDLER => $this->config['cacheHandler'],
            xPDO::OPT_CACHE_EXPIRES => $this->config['cacheExpires'],
        );
    }

    public function toCache(array $attributes_to_cache){
        $to_cache = array();
        foreach($attributes_to_cache as $attribute) {
            $to_cache[$attribute] = $this->$attribute;
        }
        $this->modx->cacheManager->set($this->config['cacheElementKey'], $to_cache, $this->config['cacheExpires'], $this->config['cacheOptions']);
    }
    public function fromCache(array $attributes_to_cache){
        $cached = true;
        $cache_array = $this->modx->cacheManager->get($this->config['cacheElementKey'], $this->config['cacheOptions']);
        // validate
        foreach($attributes_to_cache as $attribute) {
            if(!isset($cache_array[$attribute])) {
                $cached = false;
                break;
            }
        }
        // set attributes
        foreach($attributes_to_cache as $attribute) {
            $this->$attribute = $cache_array[$attribute];
        }
        return $cached;
    }
    public function process() {
        $attributes_to_cache = array('html','placeholders','double_processing_needed');
        $cached = false;
        // try to get values from cache
        if ($this->config['cache']) {
            $this->calculateCacheConfig();
            $cached = $this->fromCache($attributes_to_cache);
        }
        if (!$cached) {
            // prime all vars
            $this->html = $this->initiateHtml();
            $this->placeholders = $this->initiatePlaceholders();
            // $this->html['outer'] = $this->ffp->processContent($this->html['outer'], $this->placeholders);
        }
        // Store to cache if needed.
        if ($this->config['cache'] && !$cached) {
            $this->toCache($attributes_to_cache);
        }

        // get the current value of the field & FormIt validation error
        $current_value = $this->getCurrentValue();
        $error = $this->getError();

        // Add selected markers to options - much faster than FormItIsSelected and FormItIsChecked for large forms
        if ($this->html['options'] && $this->config['selected_text'] && $this->config['mark_selected']) {
            $this->html['options'] = $this->ffp->markSelected($this->html['options'], $current_value, $this->config['selected_text']);
        }

        // set final placeholders
        $this->placeholders['current_value'] = $current_value;
        $this->placeholders['error'] = $error;
        $this->placeholders['error_class'] = $error ? (' ' . $this->config['error_class']) : '';
        $this->placeholders['options_html'] = $this->html['options'];

        // Process outer_tpl first ONLY if inner_html ph has output filters.
        // Warning: this may cause unexpected results due to double processing.
        if ($this->double_processing_needed) {
            $this->placeholders['inner_html'] = $this->ffp->processContent($this->html['inner'], $this->placeholders);
        }

        // Optionally set all placeholders globally
        if ($this->config['to_placeholders']) {
            $this->modx->toPlaceholders($this->placeholders, $this->config['key_prefix']);
        }

        // Process the placeholders. With caching, this should be the only time a chunk is processed.
        $output = $this->ffp->processContent($this->html['outer'], $this->placeholders);
        return $output;
    }

    public function initiatePlaceholders() {
        $placeholders = $this->config;
        // set defaults as placeholders as well
        $get_defaults = explode(',', 'name,type,outer_type,prefix,error_prefix,key_prefix,tpl,option_tpl,outer_tpl,key');
        foreach ($get_defaults as $var) {
            $placeholders[$var] = (string)$this->config[$var];
        }
        // load custom placeholders - not essential, but helps a lot with speed.
        $custom_ph = explode(',', $this->config['custom_ph']);
        foreach ($custom_ph as $key) {
            if (!isset($placeholders[$key])) $placeholders[$key] = '';
        }
        // set placeholders for field types (e.g [[+checkbox:notempty=`checkbox stuff`]])
        if ($this->config['set_type_ph']) {
            $types = explode(',', $this->config['set_type_ph']);
            foreach ($types as $key) {
                $placeholders[$key] = ($key == $this->config['type']) ? '1' : '';
            }
        }
        // unset any variable placeholders
        $variables = array('error', 'current_value', 'error_class', 'options_html', 'inner_html', 'outer_html');
        foreach ($variables as $key) {
            if (isset($placeholders[$key])) unset($placeholders[$key]);
        }
        return $placeholders;
    }
    public function initiateHtml() {
        $html = array();
        $html['options'] = $this->config['options_html'];
        $html['inner'] = $this->config['inner_html'];

        // Set overrides for options and inner_html
        $html['options'] = $this->processElementOverrides('options', $html['options']);
        $html['inner'] = $this->processElementOverrides('inner', $html['inner']);

        // process inner and outer html template chunks
        if (empty($html['inner'])) $html['inner'] = $this->ffp->getChunkContent($this->config['tpl'], $this->config['delimiter'], $this->config['default_delimiter']);
        $html['outer'] = $this->ffp->getChunkContent($this->config['outer_tpl'], $this->config['outer_delimiter'], $this->config['default_delimiter']);

        // Parse options for checkboxes, radios, etc... if &options is passed
        // Note: if any provided options_html has been found, this part will be skipped
        $options = $this->config['options'];
        if ($options && empty($html['options'])) {
            $html['options'] = $this->processOptions($options, $this->placeholders);
        }

        // If outer template is set, process it. Otherwise just use the $html['inner']
        $html['outer'] = empty($html['outer']) ? $html['inner'] : $html['outer'];
        $inner_no_replace = '[[+inner_html:';
        $inner_replace = '[[+inner_html]]';
        $this->double_processing_needed = false;
        if (strpos($html['outer'], $inner_no_replace) !== false) {
            $this->double_processing_needed = true;
        } else {
            $html['outer'] = str_replace($inner_replace, $html['inner'], $html['outer']);
        }
        return $html;
    }

    public function processElementOverrides($level, $default) {
        $output = $default;
        $element = $this->config[$level . '_element'];
        $element_class = $this->config[$level . '_element_class'];
        $element_properties = $this->modx->fromJSON($this->config[$level . '_element_properties']);
        $properties = array_merge($this->placeholders, $element_properties);
        if ($element && $element_class) {
            if ($element_class === 'modChunk') {
                // Shortcut - use the cachable chunk method of FFP. Allows file-based chunks.
                $output = $this->ffp->getChunk($element, $properties);
            } else {
                // Full route for snippets & others
                /** @var $elementObj modElement */
                $elementObj = $this->modx->getObject($element_class, array('name' => $element));
                if ($elementObj) {
                    $output = $elementObj->process($properties);
                }
            }
        }
        return $output;
    }

    public function processOptions($options, $placeholders) {
        $inner_delimiter = '<!-- ' . $this->config['option_tpl'] . ' -->';
        $output = '';
        $options = explode($this->config['options_delimiter'], $options);
        foreach ($options as $option) {
            $option_array = explode($this->config['options_inner_delimiter'], $option);
            foreach ($option_array as $key => $value) {
                $option_array[$key] = trim($value);
            }
            $inner_array = $placeholders;
            $inner_array['label'] = $option_array[0];
            $inner_array['value'] = isset($option_array[1]) ? $option_array[1] : $option_array[0];
            $inner_array['key'] = $this->config['key'] . '-' . preg_replace("/[^a-zA-Z0-9-\s]/", "", $inner_array['value']);
            $output .= $this->ffp->getChunk($this->config['tpl'], $inner_array, $inner_delimiter);
        }
        return $output;
    }

    public function getError() {
        $error = $this->modx->getPlaceholder($this->config['error_prefix'] . $this->config['name']);
        return $error;
    }

    public function getCurrentValue() {
        $current_value = $this->modx->getPlaceholder($this->config['prefix'] . $this->config['name']);
        if (empty($current_value) && $this->config['use_get']) {
            $current_value = isset($_GET[$this->config['name']]) ? $_REQUEST[$this->config['name']] : null;
        }
        if (empty($current_value) && $this->config['use_request']) {
            $current_value = isset($_REQUEST[$this->config['name']]) ? $_REQUEST[$this->config['name']] : null;
        }
        if ($this->config['use_cookies']) {
            $session_key = 'field.' . $this->config['key'] . $this->config['name'];
            $current_value = empty($current_value) ? $this->modx->getOption($session_key, $_SESSION, null) : $current_value;
            $_SESSION[$session_key] = $current_value;
        }
        $current_value = (string)$current_value;
        return $current_value; // ToDo: add better caching and take this out to a str_replace function.
    }

}
