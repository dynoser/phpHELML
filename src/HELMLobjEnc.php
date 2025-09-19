<?php
namespace dynoser\HELML;

use dynoser\HELML\vc85;

/*
 * This code represents a PHP implementation of the HELMLobjEnc class.
 * 
 * The class provides object-oriented approach for encoding data to HELML format.
 * It extends HELMLobjDec and implements encoding algorithms based on the HELML class.
 * 
 */
class HELMLobjEnc extends HELMLobjDec {
    
    // Encoding configuration properties (similar to static properties in HELML class)
    
    /**
     * Add prefix to output
     * @var bool
     */
    public $ADD_PREFIX = false;
    
    /**
     * Add postfix to output
     * @var bool
     */
    public $ADD_POSTFIX = false;
    
    /**
     * Custom value encoder (callable)
     * @var callable|null
     */
    public $CUSTOM_VALUE_ENCODER = null;
    
    /**
     * Enable bones (auto-increment keys)
     * @var bool
     */
    public $ENABLE_BONES = true;
    
    /**
     * Enable space indentation
     * @var int
     */
    public $ENABLE_SPC_IDENT = 1;
    
    /**
     * Enable key uplines (empty lines before array keys)
     * @var bool
     */
    public $ENABLE_KEY_UPLINES = true;
    
    /**
     * Enable hash symbols after nested blocks
     * @var bool
     */
    public $ENABLE_HASHSYMBOLS = false;
    
    /**
     * Base85 encoding mode (0 - disabled, 1 - std.ascii-85, 2 - vwx-ascii-85, 3-vc85)
     * @var int
     */
    public $BASE85_ENCODE_MODE = 2;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Encode array to HELML string
     *
     * @param array $inArr
     * @param integer $oneLineMode 0-multi-lines, 1-URL-mode, 2-oneLine-mode
     * @return string
     */
    public function encode(array $inArr, int $oneLineMode = 0): string {
        $this->HELMLrowsArr = $this->ADD_PREFIX ? ['~'] : [];
        $outArr = & $this->HELMLrowsArr;

        $urlMode = ($oneLineMode == 1);
        $strImp = $oneLineMode ? "~" : "\n";
        $lvlCh = $urlMode ? $this->URL_LVL : $this->lvlCh;
        $spcCh = $urlMode ? $this->URL_SPC : $this->spcCh;

        $this->_encode($inArr, 0, $lvlCh, $spcCh, self::isArrayList($inArr));

        $needPostFix = $this->ADD_POSTFIX;

        if ($oneLineMode) {
            $needPostFix = $needPostFix || $lvlCh !== $this->URL_LVL || $spcCh !== $this->URL_SPC;
            $newArr = $this->ADD_PREFIX ? [] : [''];
            foreach ($outArr as $el) {
                $st = \trim($el);
                if (\strlen($st) > 0 && $st[0] !== '#') {  // skip empty lines and #-comments
                    $newArr[] = \strtr($st, "\n", $strImp);
                }
            }
            if ($urlMode && !$needPostFix) {
                $newArr[] = '';
            }
            $outArr = $newArr;
        } else {
            $needPostFix = $needPostFix || $lvlCh !== ':' || $spcCh !== ' ';
        }
        if ($needPostFix) {
            $outArr[] = '~#' . $lvlCh . $spcCh . '~';
        }
        return \implode($strImp, $outArr);
    }

    /**
     * Recursive helper function to process each key-value pair in the input array
     * 
     * @param array $inArr
     * @param int $level
     * @param string $lvlCh
     * @param string $spcCh
     * @param int $isList
     * @return void
     */
    protected function _encode(
        $inArr,
        $level = 0,
        $lvlCh = ':',
        $spcCh = ' ',
        $isList = 0
    ): void {
        $outArr = & $this->HELMLrowsArr;
        foreach ($inArr as $key => $value) {
            // Use auto-increment key index if possible
            if ($isList && $this->ENABLE_BONES) {
                $key = '--';
            } else if (!$isList) {
                // Encode $key in base64url if it contains unwanted characters
                $fc = \substr($key, 0, 1);
                $lc = \substr($key, -1, 1);
                if (('#' === $fc && !$level) || $fc === $spcCh || $fc === ' ' || $fc === '' || $lc === $spcCh || $lc === ' '
                        || false !== \strpos($key, $lvlCh) || $key === '<<' || $key === '>>') {
                    $fc = '-';
                } else {
                    $pattern = ($spcCh == $this->URL_SPC) ? '/^[ -}]+$/' : '/^[^\x00-\x1F\x7E-\xFF]+$/u';
                    if (!\preg_match($pattern, $key)) {
                        $fc = '-';
                    }
                }
                if ('-' === $fc) {
                    // Add "-" to the beginning of the key to indicate it's in base64url
                    $key = '-' . self::base64Uencode($key);
                }
            }

            // Add the appropriate number of colons to the left of the key, based on the current level
            $ident = \str_repeat($lvlCh, $level);

            // add space-ident to the left of the key (if need)
            if ($this->ENABLE_SPC_IDENT && ' ' === $spcCh) {
                $ident = \str_repeat($spcCh, $level * $this->ENABLE_SPC_IDENT) . $ident;
            }

            if (\is_array($value)) {
                // Add empty line before create-key
                if ($this->ENABLE_KEY_UPLINES && ' ' === $spcCh) {
                    $outArr[] = '';
                }

                $isNumKeys = self::isArrayList($value);
                if (!$isNumKeys) {
                    $key .= $lvlCh;
                }

                // If the value is an array, call this function recursively and increase the level
                $outArr[] = $ident . $key;
                $this->_encode($value, $level + 1, $lvlCh, $spcCh, $isNumKeys);

                if ($this->ENABLE_KEY_UPLINES && ' ' === $spcCh) {
                    $outArr[] = \str_repeat($spcCh, $level) . '#';
                }
            } else {
                // If the value is not an array, run it through a value encoding function
                $value = null === $this->CUSTOM_VALUE_ENCODER ? $this->valueEncode($value, $spcCh) : \call_user_func($this->CUSTOM_VALUE_ENCODER, $value);
                // Add the key:value pair to the output
                $outArr[] = $ident . $key . $lvlCh . $value;
            }
        }
    }

    /**
     * Encode a value based on its type and add any necessary prefixes
     * 
     * @param mixed $value
     * @param string $spcCh
     * @return string
     * @throws \InvalidArgumentException
     */
    public function valueEncode($value, $spcCh = ' '): string {
        $type = \gettype($value);
        switch ($type) {
            case 'string':
                if ('' === $value) {
                    return '-';
                }
                $fc = $value[0];
                $lc = \substr($value, -1);

                // try multi-string literal
                if ($spcCh === ' ' && false !== \strpos($value, "\n") && \function_exists('mb_check_encoding')
                    && \mb_check_encoding($value, 'UTF-8') && !\preg_match("/`\x0d|`\x0a/", $value)) {
                        return "`\n" . $value . "\n`";
                }

                if (!\preg_match(($spcCh === $this->URL_SPC) ? '/^[ -}]+$/' : '/^[^\x00-\x1F\x7E-\xFF]+$/u', $value)) {
                    $fc = '-';
                }
                if ($fc === '-') {
                    if ($this->BASE85_ENCODE_MODE && $spcCh === ' ') {
                        if (\class_exists('\dynoser\base85\vc85')) {
                            vc85::init($this->BASE85_ENCODE_MODE, 75, true);
                            return vc85::encode($value);
                        } else {
                            $this->BASE85_ENCODE_MODE = 0;
                        }
                    }
                    return '-' . self::base64Uencode($value);
                } elseif ($spcCh === $fc || ' ' === $fc ||  $spcCh === $lc || \ctype_space($lc)) {
                    // for empty strings or those that have spaces at the beginning or end
                    return "'" . $value . "'";
                }
                // if the value is simple, just add one space at the beginning
                return $spcCh . $value;

            case 'boolean':
                return $spcCh . $spcCh . ($value ? 'T' : 'F');

            case 'NULL':
                return $spcCh . $spcCh . 'N';

            case 'double':
            case 'float':
                if (\is_nan($value)) {
                    return $spcCh . $spcCh . 'NAN';
                } elseif (\is_infinite($value)) {
                    return $spcCh . $spcCh . ($value > 0 ? 'INF' : 'NIF');
                }
                if (false === \strpos($value, '.')) {
                    $value .= '.0';
                }
                if ($this->URL_SPC === $spcCh) {
                    // for url-mode because dot-inside
                    return '+' . self::base64Uencode($spcCh. $spcCh . (string)$value);
                }
                // if not url mode, go below
            case 'integer':
                return $spcCh . $spcCh . $value;
            default:
                throw new \InvalidArgumentException("Cannot encode value of type $type");
        }
    }

    /**
     * Check if the array is a list with keys 0,1,2..,
     * then returns the number of elements
     * otherwise returns 0
     * 
     * @param array $arr
     * @param int $expectedCount
     * @return int
     */
    public static function isArrayList(&$arr, $expectedCount = false): int {
        if (!\array_key_exists(0, $arr)) {
            return 0;
        }
        $elCount = \count($arr);
        if ($expectedCount && ($elCount != $expectedCount)) {
            return 0;
        }
        for($i = 1; $i < $elCount; $i++) {
            if (!\array_key_exists($i, $arr)) {
                return 0;
            }
        }
        return $elCount;
    }

    /**
     * Encode a string using the base64url encoding scheme
     * 
     * @param string $str
     * @return string
     */
    public static function base64Uencode($str): string {
        $enc = \base64_encode($str);
        return \rtrim(\strtr($enc, '+/', '-_'), '=');
    }
    
    /**
     * Set encoding configuration from array
     * 
     * @param array $config
     * @return void
     */
    public function setEncodingConfig(array $config): void {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Get current encoding configuration as array
     * 
     * @return array
     */
    public function getEncodingConfig(): array {
        return [
            'ADD_PREFIX' => $this->ADD_PREFIX,
            'ADD_POSTFIX' => $this->ADD_POSTFIX,
            'CUSTOM_VALUE_ENCODER' => $this->CUSTOM_VALUE_ENCODER,
            'ENABLE_BONES' => $this->ENABLE_BONES,
            'ENABLE_SPC_IDENT' => $this->ENABLE_SPC_IDENT,
            'ENABLE_KEY_UPLINES' => $this->ENABLE_KEY_UPLINES,
            'ENABLE_HASHSYMBOLS' => $this->ENABLE_HASHSYMBOLS,
            'BASE85_ENCODE_MODE' => $this->BASE85_ENCODE_MODE,
            'lvlCh' => $this->lvlCh,
            'spcCh' => $this->spcCh,
            'URL_SPC' => $this->URL_SPC,
            'URL_LVL' => $this->URL_LVL,
            'SPEC_TYPE_VALUES' => $this->SPEC_TYPE_VALUES,
            'CUSTOM_FORMAT_DECODER' => $this->CUSTOM_FORMAT_DECODER,
            'ENABLE_DBL_KEY_ARR' => $this->ENABLE_DBL_KEY_ARR,
        ];
    }
}
