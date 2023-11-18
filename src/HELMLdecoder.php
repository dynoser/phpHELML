<?php
namespace dynoser\HELML;
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
     * @param string|array $src_rows
     * @return array
     * @throws InvalidArgumentException
     */
    public static function decode($src_rows, $layers_list = [0]) {
        // If the input is an array, use it. Otherwise, split the input string into an array.
        $lvl_ch = ':';
        $spc_ch = ' ';
        
        $layer_init = 0;
        $layer_curr = $layer_init;
        $layers_list = \array_flip($layers_list); // move values into keys
        $all_layers = [$layer_init => 1];

        if (\is_array($src_rows)) {
            $str_arr = $src_rows;
        } elseif (\is_string($src_rows)) {
            foreach(["\n", "\r", "~"] as $exploder_ch) {
                if (false !== \strpos($src_rows, $exploder_ch)) {
                    if ("~" === $exploder_ch && \substr($src_rows, -1) === '~') {
                        $lvl_ch = '.';
                        $spc_ch = '_';
                    }
                    break;
                }
            }
            $str_arr = \explode($exploder_ch, $src_rows);
        } else {
            throw new \InvalidArgumentException("Array or String required");
        }
        
        // Initialize result array and stack for keeping track of current array nesting
        $result = [];
        $stack = [];

        $min_level = -1;
        
        // Loop through each line in the input array
        $lines_cnt = \count($str_arr);
        for ($lnum = 0; $lnum < $lines_cnt; $lnum++) {
            $line = \trim($str_arr[$lnum]);

            // Skip empty lines and comment lines starting with '#'
            if (!\strlen($line) || \substr($line, 0, 1) === '#' || \substr($line, 0, 2) === '//') continue;

            // Calculate the level of nesting for the current line by counting the number of colons at the beginning
            $level = 0;
            while (\substr($line, $level, 1) === $lvl_ch) {
                $level++;
            }

            // If the line has colons at the beginning, remove them from the line
            if ($level) {
                $line = \substr($line, $level);
            }

            // Split the line into a key and a value (or null if the line starts a new array)
            $parts = \explode($lvl_ch, $line, 2);
            $key = $parts[0] ? $parts[0] : '0';
            $value = isset($parts[1]) ? $parts[1] : null;

            // check min_level
            if ($min_level < 0 || $min_level > $level) {
                $min_level = $level;
            }

            // Remove keys from the stack if level decreased
            $extra_keys_cnt = \count($stack) - $level + $min_level;
            if ($extra_keys_cnt > 0) {
                // removing extra keys from stack
                 while(\count($stack) && $extra_keys_cnt--) {
                     \array_pop($stack);
                 }
                $layer_curr = $layer_init;
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
                    $layer_curr = ($value  || $value === '0') ? $value : (\is_numeric($layer_curr) ? ($layer_curr + 1) : 0);
                    if (empty($all_layers[$layer_curr])) {
                        $all_layers[$layer_curr] = 1;
                    }
                    continue;
                } else {
                    $decoded_key = self::base64Udecode(\substr($key, 1));
                    if (false !== $decoded_key) {
                        $key = $decoded_key;
                    }
                }
            }

            // If the value is null, start a new array and add it to the parent array
            if (\is_null($value) || !\strlen($value)) {
                $parent[$key] = [];
                \array_push($stack, $key);
            } elseif (\array_key_exists($layer_curr, $layers_list)) {
                // multistring literal
                if ($value === '`') {
                    $value = [];
                    for($cln = $lnum + 1; $cln < $lines_cnt; $cln++) {
                        $line = \trim($str_arr[$cln],"\r\n\x00");
                        if (\trim($line) === '`') {
                            $value = \implode("\n", $value);
                            $lnum = $cln;
                            break;
                        }
                        $value[] = $line;
                    }
                    if (!\is_string($value)) {
                        $value = '`ERR`';
                    }
                } else {
                    // Use default valueDecoder or custom decoder function is specified
                    $value = \is_null(self::$CUSTOM_VALUE_DECODER) ? self::valueDecoder($value, $spc_ch) : \call_user_func(self::$CUSTOM_VALUE_DECODER, $value, $spc_ch);
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
        
        if (\count($all_layers) > 1) {
            $result['_layers'] = \array_keys($all_layers);
        }

        // Return the result array
        return $result;
    }

    /**
     * Decode an encoded value based on its prefix
     * 
     * @param string $encodedValue
     * @param string $spc_ch
     * @return any
     */
    public static function valueDecoder($encodedValue, $spc_ch = ' ') {
        $first_char = \substr($encodedValue, 0, 1);
        if ($spc_ch === $first_char) {
            if (\substr($encodedValue, 0, 2) !== $spc_ch . $spc_ch) {
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
        } elseif ('"' === $first_char || "'" === $first_char) {
            $encodedValue = \substr($encodedValue, 1, -1); // trim the presumed quotes at the edges and return the interior
            if ("'" === $first_char) {
                return $encodedValue;
            }
            return \stripcslashes($encodedValue);
        } elseif ('-' === $first_char) {
            return self::base64Udecode(\substr($encodedValue, 1));
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
}
