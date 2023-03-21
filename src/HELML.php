<?php
namespace dynoser\HELML;
/*
 * This code represents a PHP implementation of the HELML class without dependencies.
 * 
 * The class provides functions for encoding and decoding data in the HELML format.
 * 
 */
class HELML {

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
    
    // Default value encoder is self::encodeValue, may be replaced here
    public static $CUSTOM_VALUE_ENCODER = null;
    
    // Default value decoder is self::decodeValue, may be replace here
    public static $CUSTOM_VALUE_DECODER = null;

    // Main function to encode an array into HELML format
    public static function encode($arr, $url_mode = false) {
        $results_arr = [];
        if (!is_array($arr)) {
            throw new InvalidArgumentException("Array required");
        }
        $str_imp = $url_mode ? "~" : "\n";
        $lvl_ch = $url_mode ? '.' : ':';
        $spc_ch = $url_mode ? '_' : ' ';
        self::_encode($arr, $results_arr, 0, $lvl_ch, $spc_ch);
        return implode($str_imp, $results_arr);
    }

    /**
     * Recursive helper function to process each key-value pair in the input array
     * 
     * @param array $arr
     * @param array $results_arr
     * @param int $level
     * @param string $lvl_ch
     * @param string $spc_ch
     */
    public static function _encode(
        $arr,
        &$results_arr,
        $level = 0,
        $lvl_ch = ":",
        $spc_ch = " "
    ) {
        foreach ($arr as $key => $value) {

            // Encode $key in base64url if it contains unwanted characters
            $fc = substr($key, 0, 1);
            $lc = substr($key, -1, 1);
            if (false !== strpos($key, $lvl_ch) || false !== strpos($key, '~') || '#' === $fc  || $fc === $spc_ch || $fc === ' ') {
                $fc = "-";
            }
            if ("-" === $fc || $lc === $spc_ch || $lc === ' ' || !preg_match('/^[[:print:]]*$/', $key)) {
                // Add "-" to the beginning of the key to indicate it's in base64url
                $key = "-" . self::base64url_encode($key);
            }

            // Add the appropriate number of colons to the left of the key, based on the current level
            $key = str_repeat($lvl_ch, $level) . $key;

            if (is_array($value)) {
                if (self::isArrayList($value)) {
                    $key .= ':';
                }
                // If the value is an array, call this function recursively and increase the level
                $results_arr[] = $key;
                self::_encode($value, $results_arr, $level + 1, $lvl_ch, $spc_ch);
            } else {
                // If the value is not an array, run it through a value encoding function, if one is specified
                $value = null === self::$CUSTOM_VALUE_ENCODER ? self::valueEncoder($value, $spc_ch) : call_user_func(self::$CUSTOM_VALUE_ENCODER, $value);
                // Add the key:value pair to the output
                $results_arr[] = $key . $lvl_ch . $value;
            }
        }
    }

    /**
     * Decode a HELML-formatted string or array into an associative array
     * 
     * @param string|array $src_rows
     * @return array
     * @throws InvalidArgumentException
     */
    public static function decode($src_rows) {
        // If the input is an array, use it. Otherwise, split the input string into an array.
        $lvl_ch = ':';
        $spc_ch = ' ';
        if (is_array($src_rows)) {
            $str_arr = $src_rows;
        } elseif (is_string($src_rows)) {
            foreach(["\n", "\r", "~"] as $exploder_ch) {
                if (false !== strpos($src_rows, $exploder_ch)) {
                    if ("~" === $exploder_ch) {
                        $lvl_ch = '.';
                        $spc_ch = '_';
                    }
                    break;
                }
            }
            $str_arr = explode($exploder_ch, $src_rows);
        } else {
            throw new InvalidArgumentException("Array or String required");
        }

        // Initialize result array and stack for keeping track of current array nesting
        $result = [];
        $stack = [];

        // Loop through each line in the input array
        foreach ($str_arr as $line) {
            $line = trim($line);

            // Skip empty lines and comment lines starting with '#'
            if (!strlen($line) || substr($line, 0, 1) === '#') continue;

            // Calculate the level of nesting for the current line by counting the number of colons at the beginning
            $level = 0;
            while (substr($line, $level, 1) === $lvl_ch) {
                $level++;
            }

            // If the line has colons at the beginning, remove them from the line
            if ($level) {
                $line = substr($line, $level);
            }

            // Split the line into a key and a value (or null if the line starts a new array)
            $parts = explode($lvl_ch, $line, 2);
            $key = $parts[0] ? $parts[0] : 0;
            $value = isset($parts[1]) ? $parts[1] : null;

            // Decode the key if it starts with an equals sign
            if (is_string($key) && ('-' === substr($key, 0, 1))) {
                $key = self::base64url_decode(substr($key, 1));
                if (!$key) {
                    $key = "ERR";
                }
            }

            // Remove keys from the stack until it matches the current level
            while (count($stack) > $level) {
                array_pop($stack);
            }

            // Find the parent element in the result array for the current key
            $parent = &$result;
            foreach ($stack as $parentKey) {
                $parent = &$parent[$parentKey];
            }

            // If the value is null, start a new array and add it to the parent array
            if ((null === $value) || !strlen($value)) {
                $parent[$key] = [];
                array_push($stack, $key);
            } else {
                // Use default valueDecoder or custom decoder function is specified
                $value = null === self::$CUSTOM_VALUE_DECODER ? self::valueDecoder($value, $spc_ch) : call_user_func(self::$CUSTOM_VALUE_DECODER, $value, $spc_ch);
                // Add the key-value pair to the current array
                $parent[$key] = $value;
            }
        }

        // Return the result array
        return $result;
    }
    
    /**
     * Encode a value based on its type and add any necessary prefixes
     * 
     * @param string $value
     * @param string $spc_ch
     * @return any
     * @throws InvalidArgumentException
     */
    public static function valueEncoder($value, $spc_ch = ' ') {
        $type = gettype($value);
        switch ($type) {
            case 'string':
                if ('_' === $spc_ch) {
                    // for url-mode
                    $need_encode = (false !== strpos($value, '~'));
                    $reg_str = '/^[ -~]*$/';
                } else {
                    $need_encode = false;
                    $reg_str = '/^[[:print:]]*$/u';
                }
                if ($need_encode || !preg_match($reg_str, $value) || (('_' === $spc_ch) && (false !== strpos($value, '~')))) {
                    // if the string contains special characters, encode it in base64
                    return self::base64url_encode($value);
                } elseif (!strlen($value) || ($spc_ch === $value[0]) || ($spc_ch == substr($value, -1)) || ctype_space(substr($value, -1))) {
                    // for empty strings or those that have spaces at the beginning or end
                    return "'" . $value . "'";
                } else {
                    // if the value is simple, just add one space at the beginning
                    return $spc_ch . $value;
                }
            case 'boolean':
                return $spc_ch . $spc_ch . ($value ? 'T' : 'F');
            case 'NULL':
                return $spc_ch . $spc_ch . 'N';
            case 'double':
            case 'float':
                if (is_nan($value)) {
                    return $spc_ch . $spc_ch . 'NAN';
                } elseif (is_infinite($value)) {
                    return $spc_ch . $spc_ch . ($value > 0 ? 'INF' : 'NIF');
                }
                if ('_' === $spc_ch) {
                    // for url-mode because dot-inside
                    return self::base64url_encode($value);
                }
                // if not url mode, go below
            case 'integer':
                return $spc_ch . $spc_ch . $value;
            default:
                throw new InvalidArgumentException("Cannot encode value of type $type");
        }
    }

    /**
     * Decode an encoded value based on its prefix
     * 
     * @param string $encodedValue
     * @param string $spc_ch
     * @return any
     */
    public static function valueDecoder($encodedValue, $spc_ch = ' ') {
        $first_char = substr($encodedValue, 0, 1);
        if ($spc_ch === $first_char) {
            if (substr($encodedValue, 0, 2) !== $spc_ch . $spc_ch) {
                // if the string starts with only one space, return the string after it
                return substr($encodedValue, 1);
            }
            // if the string starts with two spaces, then it encodes a non-string value
            $encodedValue = substr($encodedValue, 2); // strip left spaces
            if (is_numeric($encodedValue)) {
                // it's probably a numeric value
                if (strpos($encodedValue, '.')) {
                    // if there's a decimal point, it's a floating point number
                    return (double) $encodedValue;
                }
                // if there's no decimal point, it's an integer
                return (int) $encodedValue;
            } elseif (array_key_exists($encodedValue, self::$SPEC_TYPE_VALUES)) {
                return self::$SPEC_TYPE_VALUES[$encodedValue];
            } elseif (self::$CUSTOM_FORMAT_DECODER) {
                return call_user_func(self::$CUSTOM_FORMAT_DECODER, $encodedValue);
            }            
            return $encodedValue;
        } elseif ('"' === $first_char || "'" === $first_char) {
            $encodedValue = substr($encodedValue, 1, -1); // trim the presumed quotes at the edges and return the interior
            if ("'" === $first_char) {
                return $encodedValue;
            }
            return stripcslashes($encodedValue);
        }
        // if there are no spaces or quotes at the beginning, the value should be in base64
        $decoded = self::base64url_decode($encodedValue);
        if (false === $decoded) {
            return $encodedValue; // Fallback if can't decode
        }
        return $decoded;
    }
    
    public static function isArrayList(&$arr, $expected_count = false)
    {
        if (!array_key_exists(0, $arr))
            return false;
        $el_count = count($arr);
        if ($expected_count && ($el_count != $expected_count))
            return false;
        for($i = 1; $i < $el_count; $i++) {
            if (!array_key_exists($i, $arr))
                return false;
        }
        return true;
    }
    
    /**
     * Encode a string using the base64url encoding scheme
     * 
     * @param string $str
     * @return string
     */
    public static function base64url_encode($str) {
        $enc = base64_encode($str);
        return rtrim(strtr($enc, '+/', '-_'), '=');
    }
    
    /**
     * Decode a base64url encoded string
     * 
     * @param string $str
     * @return string
     */
    public static function base64url_decode($str) {
        return base64_decode(strtr($str, '-_', '+/'));
    }
}
