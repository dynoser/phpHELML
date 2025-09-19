<?php
namespace dynoser\HELML;

class vc85
{
    public static $vc85enc = []; // [int num85] => char (utf)
    public static $vc85dec = []; // [int char-code] => int num85
    public static $vc85chars = '';

    // encoder options:
    public static $currentEncodeMode = 0;
    public static $defaultEncodeMode = 2; // @see init
    public static $splitWidth = 75; // split result to lines by width
    public static $addPf = true; // add <~ ... ~> or not
    
    public static $mbstrsplit = null; // true if mb_str_split function is available

    public static $lastDecodedIsAlt85 = false;

    /**
     * (Optional)
     * The constructor is used only to set parameters.
     * if null is passed then the default-value is used
     * for example, call "new vc85(3);" will set encodeMode = 3
     * 
     * @param int|null $encodeMode null or 1,2,3 (default = 2)
     * @param int|null $splitWidth null or split width (default = 75)
     * @param bool|null $addPf null or true or false (default = true)
     */
    public function __construct($encodeMode = null, $splitWidth = null, $addPf = null) {
        if (is_numeric($encodeMode)) {
            self::init($encodeMode);
        }
        if (is_numeric($splitWidth)) {
            self::$splitWidth = (int)$splitWidth;
        }
        if (!is_null($addPf)) {
            self::$addPf = (bool)$addPf;
        }
    }

    /**
     * encodeMode:
     *  0 - get actual value from vc85::$defaultEncodeMode
     *  1 - classic ascii85 compatible characters table
     *  2 - vwx (default) - ascii85 with replaces: "=>v '=>w \=>x
     *  3 - vc85 - ascii85 with replaces all spec.chars to visually distinct characters (utf-8)
     *  4 - vc85 - same as 3, but the result will be encoded in cp1251
     * @param int $encodeMode Mode 1, default = 2, 3
     * @return void
     */
    public static function init(int $encodeMode = 0): void {
        if (!$encodeMode) {
            $encodeMode = self::$defaultEncodeMode;
        }
        if (self::$currentEncodeMode === $encodeMode) {
            return;
        }
        self::$mbstrsplit = \function_exists('\mb_str_split');
        
        $repc = 118; // replaces: "=>v '=>w \=>x
        for($i = 0; $i < 85; $i++) {
            $cn = 33 + $i;
            self::$vc85dec[$cn] = $i;
            if ($cn === 34 || $cn === 39 || $cn === 92) {
                $ca = $repc++;
                self::$vc85dec[$ca] = $i;
                if ($encodeMode > 1) {
                    $cn = $ca;
                }
            }
            self::$vc85enc[$i] = \chr($cn);
        }
        
        // replacement table: first char = from, second char = to.
        $repArr = ['!Я','#Ж','$Д','%П','&Ц','(Щ',')щ','*ж','+ф',',ц','-Э','.я',
            '/ю',':д',';Б','<Г','=э','>ъ','?Ъ','@Ф','IИ','OЮ','[Ш',']ш','^л', '`й', 'lЛ'];
        $cp1251 = \hex2bin('dfc6c4cfd6d9f9e6f4f6ddfffee4c1c3fdfadad4c8ded8f8ebe9cb');
        foreach($repArr as $n => $repCh) {
            $cn = \ord($repCh[0]);
            $ca = \ord(\substr($repCh, -1));
            $cp = \ord($cp1251[$n]);
            $i = $cn - 33;
            if ($encodeMode > 2) {
                self::$vc85enc[$i] = ($encodeMode === 4) ? $cp1251[$n] : \substr($repCh, 1);
            }
            self::$vc85dec[$ca] = $i;
            self::$vc85dec[$cp] = $i;
        }
        
        // put all available chars-bytes to one string (from array keys to ascii-chr)
        self::$vc85chars = \implode('', \array_map('chr',\array_keys(self::$vc85dec)));
        self::$currentEncodeMode = $encodeMode;
    }

    /**
     * Encode binary-string data to base-85
     * use inside:
     *   vc85::$currentEncodeMode (1 or 2 or 3, @see vc85::init)
     *   vc85::$splitWidth (to split result to lines by width, set 0 if no need)
     *   vc85::$addPf (true or false, will add <~ ... ~> or not)
     * @param string $data binary string
     * @param mixed $altPf
     * @return string vc85 encoded string
     */
    public static function encode($data, bool $altPf = false): string {
        $l = \strlen($data);
        $sub = $l % 4;
        $pad = $sub ? (4 - $sub) : 0;
        
        self::$currentEncodeMode || self::init();
        $enc85Arr = self::$vc85enc;
  
        $pow85Arr = [52200625, 614125, 7225, 85, 1];
        $out = self::$addPf ? [$altPf ? '{~' : '<~'] : [];
        foreach(\unpack("N*", $data . \str_repeat("\0", $pad)) as $uint32) {
            $sum = '';
            foreach($pow85Arr as $pow) {
                $rem = $uint32 % $pow;
                $sum .= $enc85Arr[(int)($uint32 / $pow)];
                $uint32 = $rem;
            }
            $out[] = $sum;
        }
        if ($sub) {
            $out[\count($out)-1] = \implode('', \array_slice(self::explodeUTF8($sum), 0, 5 - $pad));
        }
        if (self::$addPf) {
            $out[] = $altPf ? '~}' : '~>';
        }
        return (self::$splitWidth > 0) ? self::implodeSplitter($out) : \implode('', $out);
    }
    /**
     * @return string
     * @param mixed $out
     */
    private static function implodeSplitter($out) {
        if (self::$currentEncodeMode < 3) {
            $arr = \str_split(\implode('', $out), self::$splitWidth);
        } elseif (self::$mbstrsplit) {
            $arr = \mb_str_split(\implode('', $out), self::$splitWidth, 'utf-8');
        } else {
            $arr = [];
            $rowLen = 0;
            $st = '';
            foreach($out as $grp5) {
                $st .= $grp5;
                if ($rowLen < self::$splitWidth) {
                    $rowLen += 5;
                } else {
                    $arr[] = $st;
                    $st = '';
                    $rowLen = 0;
                }
            }
            if ($rowLen) {
                $arr[] = $st;
            }
        }

        $c = \count($arr);
        if ($c > 1) {
            $lc = $arr[$c-1];
            if ($lc === '>' || $lc === '}') {
                $arr[$c-2] .= $lc;
                $arr[$c-1] = '';
            }
            $fc = $arr[0];
            if ($fc === '<' || $fc === '{') {
                $arr[0] = $fc . $arr[1];
                $arr[1] = '';
            }
        }
        return \implode("\n", $arr);
    }
    
    /**
     * Decodes all base85 encoding variants, has no options
     *
     * @param string $dataSrc base-85 encoded string
     * @return string decoded binary data
     * @throws InvalidArgumentException* @param mixed $isAlt
     
     */
    public static function decode($dataSrc, $isAlt = false) {
        $data = \trim(\strtr($dataSrc, \chr(208) . \chr(209) . "\t\n\r", '     '));
        if (\substr($data, 0, 2) === '< ' && \substr($data, -2) === ' >') {
            $data = \substr($data, 2, -2);
        } else {
            // try cut data between <~ ... ~>
            $p = \strpos($data, '<~');
            if (false === $p ) {
                $p = \strpos($data, '{~');
                $isAlt = (false !== $p);
            } else {
                $isAlt = false;
            }
            self::$lastDecodedIsAlt85 = $isAlt;
            $i = (false !== $p) ? $p + 2 : 0;
            $j = \strpos($data, '~', $i);
            if ($j) {
                $data = \substr($data, $i, $j - $i);
            } elseif ($i) {
                $data = \substr($data, $i);
            }
        }
       
        self::$currentEncodeMode || self::init();

        $dataWrk = \str_replace(["z", "y", " "], ["!!!!!", "+<VdL", ''], $data);

        $l = \strlen($dataWrk);
        if ($l !== \strspn($dataWrk, self::$vc85chars)) {
            throw new \InvalidArgumentException("Data contains invalid characters");
        }
        $sub = $l % 5;
        $pad = $sub ? (5 - $sub) : 0;

        $out = \array_map(function($value) {
            $sum = 0;
            foreach (\unpack("C*", $value) as $char) {
                $sum = $sum * 85 + self::$vc85dec[$char];
            }
            return \pack("N", $sum);
        }, \str_split($dataWrk . \str_repeat(self::$vc85enc[84], $pad), 5));

        if ($sub) {
            $lg = \count($out) - 1;
            $out[$lg] = \substr($out[$lg], 0, 4 - $pad);
        }

        return \implode('', $out);
    }
    
    /**
    * Splits a UTF-8 string into characters.
    *
    * This function takes a UTF-8 encoded string as input and breaks it down into its constituent characters.
    *
    * @param string $str The input string to be split.
    * @param int $brkCnt The maximum number of characters to break the string into. Defaults to 65535.
    *
    * @return array|null An array of characters or null in case of an error.
    */
    public static function explodeUTF8(string $str, int $brkCnt = 65535): ?array {
        if (self::$mbstrsplit) {
            return \mb_str_split($str, 1, 'utf-8');
        }
        $charsArr = [];
        $len = \strlen($str);
        for($i = 0; $i < $len; $i++){
            $cn = \ord($str[$i]);
            if ($cn > 128) {
                if (($cn > 247)) return null;
                elseif ($cn > 239) $bytes = 4;
                elseif ($cn > 223) $bytes = 3;
                elseif ($cn > 191) $bytes = 2;
                else return null;
                if (($i + $bytes) > $len) return null;
                $charsArr[] = \substr($str, $i, $bytes);
                while ($bytes > 1) {
                    $i++;
                    $b = \ord($str[$i]);
                    if ($b < 128 || $b > 191) return null;
                    $bytes--;
                }
            } else {
                $charsArr[] = \chr($cn);
            }
            if ($i > $brkCnt) break;
        }
        return $charsArr;
    }
}
