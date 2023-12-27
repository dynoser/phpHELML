<?php
namespace dynoser\HELML;
/*
 * This code represents a PHP implementation of the HELML class without dependencies.
 * 
 * The class provides functions for encoding and decoding HELML-formated data.
 * 
 */

class HELML {
    // *** This class was automaticaly extracted from HELML class source by SectMan Sections Manager.
    // *** This class contains full functionality HELML encoding and decoding, but is not source code
    // *** source code of this file you can see here: https://github.com/dynoser/HELML/blob/master/PHP/HELML.php

    // Custom user-specified values may be added here:
    public static $SPEC_TYPE_VALUES = [
        'N' => null,
        'U' => null,
        'T' => true,
        'F' => false,
        'NAN' => NAN,
        'INF' => INF,
        'NIF' =>-INF,
    ];
    
    public static $URL_SPC = '=';
    public static $URL_LVL = '.';

    //Custom hooks below (set callable if need)
    
    // Hook for decode "  Value"
    public static $CUSTOM_FORMAT_DECODER = null;

    // Default value decoder is self::decodeValue, may be replace here
    public static $CUSTOM_VALUE_DECODER = null;
    
    // Enable auto-create array when key already exists
    public static $ENABLE_DBL_KEY_ARR = false;
    
    public static $ADD_PREFIX = false;
    public static $ADD_POSTFIX = false;
        
    // Default value encoder is self::encodeValue, may be replaced here
    public static $CUSTOM_VALUE_ENCODER = null;

    public static $ENABLE_BONES = true;
    public static $ENABLE_SPC_IDENT = 1;
    public static $ENABLE_KEY_UPLINES = true; //add empty line before create-array-keys
    public static $ENABLE_HASHSYMBOLS = false; // adding # after nested-blocks

    /**
     * Encode array to HELML string
     *
     * @param array $inArr
     * @param integer $oneLineMode 0-multi-lines, 1-URL-mode, 2-oneLine-mode
     * @return string
     * @throws InvalidArgumentException
     */
    public static function encode($inArr, $oneLineMode = 0) {
        $outArr = self::$ADD_PREFIX ? [''] : [];
        if (!\is_array($inArr)) {
            throw new \InvalidArgumentException("Array required");
        }

        $urlMode = ($oneLineMode == 1);
        $strImp = $oneLineMode ? "~" : "\n";
        $lvlCh = $urlMode ? self::$URL_LVL : ':';
        $spcCh = $urlMode ? self::$URL_SPC : ' ';

        self::_encode($inArr, $outArr, 0, $lvlCh, $spcCh, self::isArrayList($inArr));

        if ($oneLineMode) {
            $newArr = [''];
            foreach ($outArr as $el) {
                $st = \trim($el);
                if (\strlen($st) > 0 && $st[0] !== '#') {  // skip empty lines and #-comments
                    $newArr[] = \strtr($st, "\n", $strImp);
                }
            }
            if ($urlMode) {
                $newArr[] = '';
            }
            $outArr = $newArr;
        }
        if (self::$ADD_POSTFIX) {
            $outArr[] = '#' . $lvlCh . $spcCh . '~';
        }
        return \implode($strImp, $outArr);
    }

    /**
     * Recursive helper function to process each key-value pair in the input array
     * 
     * @param array $inArr
     * @param array &$outArr
     * @param int $level
     * @param string $lvlCh
     * @param string $spcCh
     * @param int $isList
     */
    public static function _encode(
        $inArr,
        &$outArr,
        $level = 0,
        $lvlCh = ':',
        $spcCh = ' ',
        $isList = 0
    ) {
        foreach ($inArr as $key => $value) {
            // Use auto-increment key index if possible
            if ($isList && self::$ENABLE_BONES) {
                $key = '--';
            } else if (!$isList) {
                // Encode $key in base64url if it contains unwanted characters
                if (\strlen($key)) {
                    $fc = \substr($key, 0, 1);
                    $lc = \substr($key, -1, 1);
                    if (('#' === $fc && !$level) || $fc === $spcCh || $fc === ' ' || $lc === $spcCh || $lc === ' '
                            || false !== \strpos($key, $lvlCh) || $key === '<<' || $key === '>>') {
                        $fc = '-';
                    } else {
                        $pattern = ($spcCh == self::$URL_SPC) ? '/^[ -}]+$/' : '/^[^\x00-\x1F\x7E-\xFF]+$/u';
                        if (!\preg_match($pattern, $key)) {
                            $fc = '-';
                        }
                    }
                    if ('-' === $fc) {
                        // Add "-" to the beginning of the key to indicate it's in base64url
                        $key = '-' . self::base64Uencode($key);
                    }
                } else {
                    $key = '-';
                }
                
            }

            // Add the appropriate number of colons to the left of the key, based on the current level
            $key = \str_repeat($lvlCh, $level) . $key;
            
            // add space-ident to the left of the key (if need)
            if (self::$ENABLE_SPC_IDENT && ' ' === $spcCh) {
                $key = \str_repeat($spcCh, $level * self::$ENABLE_SPC_IDENT) . $key;
            }

            if (\is_array($value)) {
                // Add empty line before create-key
                if (self::$ENABLE_KEY_UPLINES && ' ' === $spcCh) {
                    $outArr[] = '';
                }

                $isNumKeys = self::isArrayList($value);
                if (!$isNumKeys) {
                    $key .= $lvlCh;
                }

                // If the value is an array, call this function recursively and increase the level
                $outArr[] = $key;
                self::_encode($value, $outArr, $level + 1, $lvlCh, $spcCh, $isNumKeys);

                if (self::$ENABLE_KEY_UPLINES && ' ' === $spcCh) {
                    $outArr[] = \str_repeat($spcCh, $level) . '#';
                }
            } else {
                // If the value is not an array, run it through a value encoding function, if one is specified
                $value = null === self::$CUSTOM_VALUE_ENCODER ? self::valueEncode($value, $spcCh) : \call_user_func(self::$CUSTOM_VALUE_ENCODER, $value);
                // Add the key:value pair to the output
                $outArr[] = $key . $lvlCh . $value;
            }
        }
    }

    /**
     * Decode a HELML-formatted string or array into an associative array
     * 
     * @param string|array $srcRows
     * @return array
     * @throws InvalidArgumentException
     */
    public static function decode($srcRows, $layersListSrc = [0]) {
        // If the input is an array, use it. Otherwise, split the input string into an array.
        $lvlCh = ':';
        $spcCh = ' ';
        
        $layerInit = 0;
        $layerCurr = $layerInit;
        $layersList = \array_flip($layersListSrc); // move values into keys
        $allLayers = [$layerInit => 1];

        if (\is_array($srcRows)) {
            $strArr = $srcRows;
        } elseif (\is_string($srcRows)) {
            // Search postfix
            $pfPos = \strpos($srcRows, '~#'); //~#: ~
            if ($pfPos >= 0 && \substr($srcRows, $pfPos + 4, 1) === '~') {
                // get control-chars from postfix
                $lvlCh = $srcRows[$pfPos + 2];
                $spcCh = $srcRows[$pfPos + 3];
            } else {
                $pfPos = 0;
            }
            foreach(["\n", "\r", "~"] as $explCh) {
                if (false !== \strpos($srcRows, $explCh)) {
                    if (!$pfPos && "~" === $explCh && \substr($srcRows, -1) === '~') {
                        $lvlCh = self::$URL_LVL;
                        $spcCh = self::$URL_SPC;
                    }
                    break;
                }
            }
            $strArr = \explode($explCh, $pfPos ? \substr($srcRows, 0, $pfPos) : $srcRows);
        } else {
            throw new \InvalidArgumentException("Array or String required");
        }
        
        // Initialize result array and stack for keeping track of current array nesting
        $result = [];
        $stack = [];

        $minLevel = -1;
        $baseLevel = 0;
        
        // Loop through each line in the input array
        $linesCnt = \count($strArr);
        for ($lNum = 0; $lNum < $linesCnt; $lNum++) {
            $line = \trim($strArr[$lNum]);

            // Skip empty lines and comment lines starting with '#'
            if (!\strlen($line) || \substr($line, 0, 1) === '#' || \substr($line, 0, 2) === '//') continue;

            // Calculate the level of nesting for the current line by counting the number of colons at the beginning
            $level = 0;
            while (\substr($line, $level, 1) === $lvlCh) {
                $level++;
            }

            // If the line has colons at the beginning, remove them from the line
            if ($level) {
                $line = \substr($line, $level);
            }

            // Split the line into a key and a value (or null if the line starts a new array)
            $parts = \explode($lvlCh, $line, 2);
            $key = $parts[0] ? $parts[0] : '0';
            $value = isset($parts[1]) ? $parts[1] : null;

            // base_level mod
            $level += $baseLevel;
            if (!$value) {
                if ($key === '<<') {
                    $baseLevel && $baseLevel--;
                    continue;
                } elseif ($key === '>>') {
                    $baseLevel++;
                    continue;
                }
            } elseif ($value === '>>') {
                $baseLevel++;
                $value = '';
            }

            // check min_level
            if ($minLevel < 0 || $minLevel > $level) {
                $minLevel = $level;
            }

            // Remove keys from the stack if level decreased
            $extra_keys_cnt = \count($stack) - $level + $minLevel;
            if ($extra_keys_cnt > 0) {
                // removing extra keys from stack
                 while(\count($stack) && $extra_keys_cnt--) {
                     \array_pop($stack);
                 }
                $layerCurr = $layerInit;
            }

            // Find the parent element in the result array for the current key
            $parent = &$result;
            foreach ($stack as $parentKey) {
                $parent = &$parent[$parentKey];
            }
            
            // Decode the key if it starts with an equals sign
            if ('-' === \substr($key, 0, 1)) {
                if ($key === '--') {
                    // auto-create next numeric key
                    $key = \count($parent);
                } elseif ($key === '-+') {
                    $layerCurr = ($value  || $value === '0') ? $value : (\is_numeric($layerCurr) ? ($layerCurr + 1) : 0);
                    if (empty($allLayers[$layerCurr])) {
                        $allLayers[$layerCurr] = 1;
                    }
                    continue;
                } else {
                    $decodedKey = self::base64Udecode(\substr($key, 1));
                    if (false !== $decodedKey) {
                        $key = $decodedKey;
                    }
                }
            }

            // If the value is null, start a new array and add it to the parent array
            if (\is_null($value) || !\strlen($value)) {
                $parent[$key] = [];
                \array_push($stack, $key);
            } elseif (\array_key_exists($layerCurr, $layersList)) {
                // multistring literal
                if ($value === '`') {
                    $value = [];
                    for($cln = $lNum + 1; $cln < $linesCnt; $cln++) {
                        $line = \trim($strArr[$cln],"\r\n\x00");
                        if (\trim($line) === '`') {
                            $value = \implode("\n", $value);
                            $lNum = $cln;
                            break;
                        }
                        $value[] = $line;
                    }
                    if (!\is_string($value)) {
                        $value = '`ERR`';
                    }
                } else {
                    // Use default valueDecoder or custom decoder function is specified
                    $value = \is_null(self::$CUSTOM_VALUE_DECODER) ? self::valueDecode($value, $spcCh) : \call_user_func(self::$CUSTOM_VALUE_DECODER, $value, $spcCh);
                }
               
                if (self::$ENABLE_DBL_KEY_ARR && \array_key_exists($key, $parent)) {
                    // If DBL_KEY_ARR enabled and key already exist
                    if (\is_array($parent[$key])) {
                        $parent[$key][] = $value;
                    } else {
                        $parent[$key] = [$parent[$key], $value];
                    }
                } else {
                    // Add the key-value pair to the current array
                    $parent[$key] = $value;
                }
            }
        }
        
        if (\count($allLayers) > 1) {
            $result['_layers'] = \array_keys($allLayers);
        }

        // Return the result array
        return $result;
    }

    /**
     * Encode a value based on its type and add any necessary prefixes
     * 
     * @param string $value
     * @param string $spcCh
     * @return any
     * @throws InvalidArgumentException
     */
    public static function valueEncode($value, $spcCh = ' ') {
        $type = \gettype($value);
        switch ($type) {
            case 'string':
                if (!\strlen($value)) {
                    return '-';
                }
                $fc = $value[0];
                $lc = \substr($value, -1);

                // try multi-string literal
                if ($spcCh === ' ' && false !== \strpos($value, "\n") && $lc !== '`'  && \function_exists('mb_check_encoding')
                    && \mb_check_encoding($value, 'UTF-8') && !\preg_match("/`\x0d|`\x0a/", $value)) {
                        return "`\n" . $value . "\n`";
                }

                if (!\preg_match(($spcCh === self::$URL_SPC) ? '/^[ -}]+$/' : '/^[^\x00-\x1F\x7E-\xFF]+$/u', $value)) {
                    $fc = '-';
                }
                if ($fc === '-') {
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
                if (self::$URL_SPC === $spcCh) {
                    // for url-mode because dot-inside
                    return '+' . self::base64Uencode((string)$value);
                }
                // if not url mode, go below
            case 'integer':
                return $spcCh . $spcCh . $value;
            default:
                throw new \InvalidArgumentException("Cannot encode value of type $type");
        }
    }

    /**
     * Decode an encoded value based on its prefix
     * 
     * @param string $encodedValue
     * @param string $spcCh
     * @return any
     */
    public static function valueDecode($encodedValue, $spcCh = ' ') {
        $fc = \substr($encodedValue, 0, 1);
        if ('-' === $fc || '+' === $fc) {
            $encodedValue = self::base64Udecode(\substr($encodedValue, 1));
            if ('-' === $fc) {
                return $encodedValue;
            }
            $encodedValue = $spcCh . $spcCh . $encodedValue;
            $fc = $spcCh;
        }
        if ($spcCh === $fc) {
            if (\substr($encodedValue, 0, 2) !== $spcCh . $spcCh) {
                // if the string starts with only one space, return the string after it
                return \substr($encodedValue, 1);
            }
            // if the string starts with two spaces, then it encodes a non-string value
            $slicedValue = \substr($encodedValue, 2); // strip left spaces
            if (\is_numeric($slicedValue)) {
                // it's probably a numeric value
                if (\strpos($slicedValue, '.')) {
                    // if there's a decimal point, it's a floating point number
                    return (double) $slicedValue;
                }
                // if there's no decimal point, it's an integer
                return (int) $slicedValue;
            } elseif (\array_key_exists($slicedValue, self::$SPEC_TYPE_VALUES)) {
                return self::$SPEC_TYPE_VALUES[$slicedValue];
            } elseif (self::$CUSTOM_FORMAT_DECODER) {
                return \call_user_func(self::$CUSTOM_FORMAT_DECODER, $encodedValue);
            }            
            return $encodedValue;
        } elseif ('"' === $fc || "'" === $fc) {
            $encodedValue = \substr($encodedValue, 1, -1); // trim the presumed quotes at the edges and return the interior
            if ("'" === $fc) {
                return $encodedValue;
            }
            return \stripcslashes($encodedValue);
        } elseif ('`' === $fc) {
            return \substr($encodedValue, 2, -2);
        }

        // if there are no spaces or quotes or "-" at the beginning
        if (self::$CUSTOM_FORMAT_DECODER) {
            return \call_user_func(self::$CUSTOM_FORMAT_DECODER, $encodedValue);
        }
        
        return self::base64Udecode($encodedValue);
    }

    /**
     * if the array is a list with keys 0,1,2..,
     * then returns the number of elements
     * otherwise returns 0
     * 
     * @param array $arr
     * @param int $expectedCount
     * @return int
     */
    public static function isArrayList(&$arr, $expectedCount = false)
    {
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
    public static function base64Uencode($str) {
        $enc = \base64_encode($str);
        return \rtrim(\strtr($enc, '+/', '-_'), '=');
    }
    
    /**
     * Decode a base64url encoded string
     * 
     * @param string $str
     * @return string
     */
    public static function base64Udecode($str) {
        return \base64_decode(\strtr($str, '-_', '+/'));
    }
}
