<?php
namespace dynoser\HELML;

use dynoser\base85\vc85;

/*
 * This code represents a PHP implementation of the HELMLdecoder class without dependencies.
 * 
 * The class provides functions for decoding HELML-formated data.
 * 
 */
class HELMLdecoder {
    // *** This class was automaticaly extracted from full HELML class (by SectMan Sections Manager)
    // *** if you whant only decoding HELML format, you may use this reduced class.
    // *** if you need encoding AND decoding HELML, use full HELML class instead it.
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

            if (!$pfPos && \substr($srcRows, -1) === '~') {
                $lvlCh = self::$URL_LVL;
                $spcCh = self::$URL_SPC;
            }

            // Replace all ~ to line divider
            $srcRows = \strtr($srcRows, '~', "\n");
            
            // Detect Line Divider
            foreach(["\n", "\r"] as $explCh) {
                if (false !== \strpos($srcRows, $explCh)) {
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
            $extraKeysCnt = \count($stack) - $level + $minLevel;
            if ($extraKeysCnt > 0) {
                // removing extra keys from stack
                 while(\count($stack) && $extraKeysCnt--) {
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
                } elseif ($key === '-+' || $key === '-++' || $key === '---') {
                    // Layer control keys
                    if (\is_string($value)) {
                        $value = \trim($value);
                    }
                    if ($key === '-++') {
                        $layerInit = $value ? $value : '0';
                        $layerCurr = $layerInit;
                    } elseif ($key === '-+') {
                        $layerCurr = ($value  || $value === '0') ? $value : (\is_numeric($layerCurr) ? ($layerCurr + 1) : 0);
                    }
                    $allLayers[$layerCurr] = 1;
                    continue;
                } else {
                    $decodedKey = self::base64Udecode(\substr($key, 1));
                    if (false !== $decodedKey) {
                        $key = $decodedKey;
                    }
                }
            }

            // If the value is null, start a new array and add it to the parent array
            if (\is_null($value) || $value === '') {
                $parent[$key] = [];
                \array_push($stack, $key);
            } elseif (\array_key_exists($layerCurr, $layersList)) {
                // multistring literal
                if ($value === '`' || $value === '<') {
                    $endChar = ($value === '<') ? '>' : '`';
                    $value = [];
                    for($cln = $lNum + 1; $cln < $linesCnt; $cln++) {
                        $line = \trim($strArr[$cln],"\r\n\x00");
                        if (\trim($line) === $endChar) {
                            $value = \implode("\n", $value);
                            $lNum = $cln;
                            break;
                        }
                        $value[] = $line;
                    }
                    if (\is_string($value)) {
                        if ($endChar === '>') {
                            $value = vc85::decode($value);
                        }
                    } else {
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
            $fc = \substr($encodedValue, 0, 1);
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
        } elseif ('<' === $fc) {
            return vc85::decode($encodedValue);
        } elseif ('%' === $fc) {
            return self::hexDecode(\substr($encodedValue, 1));
        }

        // if there are no spaces or quotes or "-" at the beginning
        if (self::$CUSTOM_FORMAT_DECODER) {
            return \call_user_func(self::$CUSTOM_FORMAT_DECODER, $encodedValue);
        }
        
        return self::base64Udecode($encodedValue);
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
    
    public static function hexDecode($str) {
        $hexArr = [];
        $l = \strlen($str);
        for($i = 0; $i < $l; $i++) {
            $ch1 = $str[$i];
            if (\ctype_xdigit($ch1)) {
                $ch2 = \substr($str, $i+1, 1);
                if (\ctype_xdigit($ch2)) {
                    $hexArr[] = $ch1 . $ch2;
                    $i++;
                } else {
                    $hexArr[] = '0' . $ch1;
                }
            }
        }
        return \hex2bin(\implode('', $hexArr));
    }
}
