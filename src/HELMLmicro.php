<?php
namespace dynoser\HELML;

class HELMLmicro
{
    public static $SPEC_TYPE_VALUES = [
        'N' => null,
        'U' => null,
        'T' => true,
        'F' => false,
        'NAN' => NAN,
        'INF' => INF,
        'NIF' =>-INF,
    ];

    public static function decode($srcRows) {
        $lvlCh = ':';
        $spcCh = ' ';

        if (\is_array($srcRows)) {
            $strArr = $srcRows;
        } elseif (\is_string($srcRows)) {
            foreach(["\n", "\r", "~"] as $explCh) {
                if (false !== \strpos($srcRows, $explCh)) {
                    if ("~" === $explCh && \substr($srcRows, -1) === '~') {
                        $lvlCh = '.';
                        $spcCh = '_';
                    }
                    break;
                }
            }
            $strArr = \explode($explCh, $srcRows);
        } else {
            throw new \InvalidArgumentException("Array or String required");
        }
        
        $result = [];
        $stack = [];

        $minLevel = -1;
        
        $linesCnt = \count($strArr);
        for ($lNum = 0; $lNum < $linesCnt; $lNum++) {
            $line = \trim($strArr[$lNum]);

            if (!\strlen($line) || \substr($line, 0, 1) === '#' || \substr($line, 0, 2) === '//') continue;

            $level = 0;
            while (\substr($line, $level, 1) === $lvlCh) {
                $level++;
            }

            if ($level) {
                $line = \substr($line, $level);
            }

            $parts = \explode($lvlCh, $line, 2);
            $key = $parts[0] ? $parts[0] : '0';
            $value = isset($parts[1]) ? $parts[1] : null;

            if ($minLevel < 0 || $minLevel > $level) {
                $minLevel = $level;
            }

            $extraKeysCnt = \count($stack) - $level + $minLevel;
            if ($extraKeysCnt > 0) {
                 while(\count($stack) && $extraKeysCnt--) {
                     \array_pop($stack);
                 }
            }

            $parent = &$result;
            foreach ($stack as $parentKey) {
                $parent = &$parent[$parentKey];
            }

            if ('-' === \substr($key, 0, 1)) {
                if ($key === '--') {
                    $key = \count($parent);
                } else {
                    $decodedKey = self::base64Udecode(\substr($key, 1));
                    if (false !== $decodedKey) {
                        $key = $decodedKey;
                    }
                }
            }

            if (\is_null($value) || $value === '') {
                $parent[$key] = [];
                \array_push($stack, $key);
            } else {
                if ($value === '`') {
                    $value = [];
                    for($cln = $lNum + 1; $cln < $linesCnt; $cln++) {
                        $line = \trim($strArr[$cln],"\r\n\x00");
                        if ($line === '`') {
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
                    $value = self::valueDecoder($value, $spcCh);
                }
               
                $parent[$key] = $value;
            }
        }
 
        return $result;
    }

    public static function valueDecoder($encodedValue, $spcCh = ' ') {
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
                return \substr($encodedValue, 1);
            }
            $slicedValue = \substr($encodedValue, 2);
            if (\is_numeric($slicedValue)) {
                if (\strpos($slicedValue, '.')) {
                    return (double) $slicedValue;
                }
                return (int) $slicedValue;
            } elseif (\array_key_exists($slicedValue, self::$SPEC_TYPE_VALUES)) {
                return self::$SPEC_TYPE_VALUES[$slicedValue];
            }            
            return $encodedValue;
        } elseif ('"' === $fc || "'" === $fc) {
            $encodedValue = \substr($encodedValue, 1, -1);
            if ("'" === $fc) {
                return $encodedValue;
            }
            return \stripcslashes($encodedValue);
        }
        
        return self::base64Udecode($encodedValue);
    }

    public static function base64Udecode($str) {
        return \base64_decode(\strtr($str, '-_', '+/'));
    }
}
