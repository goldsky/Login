<?php
/**
 * lgnHooks
 *
 * Copyright 2010 by Shaun McCormick <shaun@modx.com>
 *
 * Register is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Register is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Register; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package login
 */
/**
 * @package login
 * @subpackage request
 */
abstract class LoginController {
    /** @var modX $modx */
    public $modx;
    /** @var Login $login */
    public $login;
    /** @var array $config */
    public $config = array();
    /** @var array $scriptProperties */
    protected $scriptProperties = array();
    /** @var LoginValidator $validator */
    public $validator;
    /** @var LoginDictionary $dictionary */
    public $dictionary;
    /** @var LoginHooks $preHooks */
    public $preHooks;
    /** @var LoginHooks $postHooks */
    public $postHooks;
    /** @var array $placeholders */
    protected $placeholders = array();

    /**
     * @param Login $login A reference to the Login instance
     * @param array $config
     */
    function __construct(Login &$login,array $config = array()) {
        $this->login =& $login;
        $this->modx =& $login->modx;
        $this->config = array_merge($this->config,$config);
    }

    public function run($scriptProperties) {
        $this->setProperties($scriptProperties);
        $this->initialize();
        return $this->process();
    }

    abstract public function initialize();
    abstract public function process();

    /**
     * Set the default options for this module
     * @param array $defaults
     * @return void
     */
    protected function setDefaultProperties(array $defaults = array()) {
        $this->scriptProperties = array_merge($defaults,$this->scriptProperties);
    }

    /**
     * Set an option for this module
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setProperty($key,$value) {
        $this->scriptProperties[$key] = $value;
    }
    /**
     * Set an array of options
     * @param array $array
     * @return void
     */
    public function setProperties($array) {
        foreach ($array as $k => $v) {
            $this->setProperty($k,$v);
        }
    }

    /**
     * Return an array of REQUEST options
     * @return array
     */
    public function getProperties() {
        return $this->scriptProperties;
    }

    /**
     * @param $key
     * @param null $default
     * @param string $method
     * @return mixed
     */
    public function getProperty($key,$default = null,$method = '!empty') {
        $v = $default;
        switch ($method) {
            case 'empty':
            case '!empty':
                if (!empty($this->scriptProperties[$key])) {
                    $v = $this->scriptProperties[$key];
                }
                break;
            case 'isset':
            default:
                if (isset($this->scriptProperties[$key])) {
                    $v = $this->scriptProperties[$key];
                }
                break;
        }
        return $v;
    }

    public function setPlaceholder($k,$v) {
        $this->placeholders[$k] = $v;
    }
    public function getPlaceholder($k,$default = null) {
        return isset($this->placeholders[$k]) ? $this->placeholders[$k] : $default;
    }
    public function setPlaceholders($array) {
        foreach ($array as $k => $v) {
            $this->setPlaceholder($k,$v);
        }
    }
    public function getPlaceholders() {
        return $this->placeholders;
    }


    /**
     * Load the Dictionary class
     * @return LoginDictionary
     */
    public function loadDictionary() {
        $classPath = $this->getProperty('dictionaryClassPath',$this->login->config['modelPath'].'login/');
        $className = $this->getProperty('dictionaryClassName','LoginDictionary');
        if ($this->modx->loadClass($className,$classPath,true,true)) {
            $this->dictionary = new LoginDictionary($this->login);
            $this->dictionary->gather();
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[Login] Could not load LoginDictionary class from ');
        }
        return $this->dictionary;
    }

    /**
     * Loads the LoginValidator class.
     *
     * @access public
     * @param array $config An array of configuration parameters for the
     * LoginValidator class
     * @return LoginValidator An instance of the LoginValidator class.
     */
    public function loadValidator($config = array()) {
        if (!$this->modx->loadClass('LoginValidator',$this->config['modelPath'].'login/',true,true)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[Login] Could not load Validator class.');
            return false;
        }
        $this->validator = new LoginValidator($this->login,$config);
        return $this->validator;
    }


    /**
     * Loads the Hooks class.
     *
     * @access public
     * @param string $type The name of the Hooks service to load
     * @param array $config array An array of configuration parameters for the
     * hooks class
     * @return LoginHooks An instance of the fiHooks class.
     */
    public function loadHooks($type,$config = array()) {
        if (!$this->modx->loadClass('login.LoginHooks',$this->config['modelPath'],true,true)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[Login] Could not load Hooks class.');
            return false;
        }
        $this->$type = new LoginHooks($this->login,$this,$config);
        return $this->$type;
    }

    /**
     * @param string $processor
     * @return mixed|string
     */
    public function runProcessor($processor) {
        $output = '';
        $processor = $this->loadProcessor($processor);
        if (empty($processor)) return $output;

        return $processor->process();
    }

    /**
     * @param $processor
     * @return bool|LoginProcessor
     */
    public function loadProcessor($processor) {
        $processorFile = $this->config['processorsPath'].$processor.'.php';
        if (!file_exists($processorFile)) {
            return false;
        }
        try {
            $className = 'Login'.ucfirst($processor).'Processor';
            if (!class_exists($className)) {
                $className = include_once $processorFile;
            }
            /** @var LoginProcessor $processor */
            $processor = new $className($this->login,$this);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[Login] '.$e->getMessage());
        }
        return $processor;
    }

    /**
     * Get extended fields for a user
     * @return array
     */
    public function getExtended() {
        $extended = array();
        if ($this->getProperty('useExtended',true,'isset')) {
            $getExtended = $this->profile->get('extended');
            $ext = array();
            foreach ($getExtended as $k => $v) {
                if (is_array($v)) {
                    $ext[] = $this->implodePhs($v, $k);
                } else {
                    $ext[][$k] = $v;
                }
            }
            foreach ($ext as $v) {
                foreach ($v as $a => $b) {
                    $extended[$a] = $b;
                }
            }
        }
        return (array) $extended;
    }

    /**
     * Merge multi dimensional associative arrays with separator
     * @param array $array raw associative array
     * @param string $keyName parent key of this array
     * @param string $separator separator between the merged keys
     * @param array $holder to hold temporary array results
     * @return array one level array
     */
    public function implodePhs(array $array, $keyName = null, $separator = '.', array $holder = array()) {
        $phs = !empty($holder) ? $holder : array();
        foreach ($array as $k => $v) {
            $key = !empty($keyName) ? $keyName . $separator . $k : $k;
            if (is_array($v)) {
                $phs = $this->implodePhs($v, $key, $separator, $phs);
            } else {
                $phs[$key] = $v;
            }
        }
        return $phs;
    }

    /**
     * Flip the numeric keys as the parent key of the same extended sets
     * @param array $array extended array
     * @param string $parentKey parent key's name to be glued back as the placeholder's prefix
     * @param string $separator placeholder names' separator
     * @return array flipped array
     */
    public function flipNumericChild(array $array, $parentKey, $separator='.') {
        $flip = $ar = array();
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $ar[] = $this->implodePhs($v, $k);
            } else {
                $ar[][$k] = $v;
            }
        }
        foreach ($ar as $v) {
            $exp = array(); $imp = '';
            foreach ($v as $a => $b) {
                $exp = @explode($separator, $a);
                $index = array_pop($exp);
                $imp = @implode($separator, $exp);
                $key = !empty($imp) ? $parentKey . $separator . $imp : $parentKey;
                $flip[$index][$key] = $b;
            }
        }

        return $flip;
    }

    /**
     * Helper method to detect the existance of numeric keys
     * @param array $tree raw array
     * @param int $depth get the depth of multi dimension array
     * @return array depth & count of numeric keys in the extended profile
     */
    public function recursiveNumericChild(array $tree, $depth = 0) {
        $rec = array();
        foreach ($tree as $k => $v) {
            if (is_array($v)) {
                return $this->recursiveNumericChild($v, $depth+1);
            } else {
                $rec['depth'] = $depth;
                /* this below detects multiple field names based on numeric array */
                $rec['count'] = is_numeric($k) ? count($tree) : 0;
                return $rec;
            }
        }
    }

}

/**
 * Abstracts processors into a class
 * @package login
 */
abstract class LoginProcessor {
    /** @var Login $login */
    public $login;
    /** @var LoginController $controller */
    public $controller;
    /** @var LoginDictionary $dictionary */
    public $dictionary;
    /** @var array $config */
    public $config = array();

    /**
     * @param Login &$login A reference to the Login instance
     * @param LoginController &$controller
     * @param array $config
     */
    function __construct(Login &$login,LoginController &$controller,array $config = array()) {
        $this->login =& $login;
        $this->modx =& $login->modx;
        $this->controller =& $controller;
        $this->dictionary =& $controller->dictionary;
        $this->config = array_merge($this->config,$config);
    }

    abstract function process();
}