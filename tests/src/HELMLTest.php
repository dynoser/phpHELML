<?php

namespace dynoser\HELML;

use \PHPUnit\Framework\TestCase;

class HELMLTest extends TestCase {
    public static function RandomBytes($length = 15)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }
        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }
    }

    public function provideDecodingCases() {
        foreach([
            ' ' => 'a',
            '_' => 'b',
            '=' => 'c',
            '@' => 'd',
            '#' => 'e',
        ] as $spc_ch => $kv) {
        foreach ([
            ['A: abcd',
                ['A' => 'abcd']
            ],

            [<<<HELML
ML:`
This is
  Multiline
`

EMPTY:`
`
 ONE ROW:`
 one row
`
HELML,
                [
                    'ML' => "This is\n  Multiline",
                    'EMPTY' => '',
                    'ONE ROW' => ' one row',
                ]
            ],

            [<<<HELML
--: 1
--: 2
--:  3
HELML,
                [
                    '1',
                    '2',
                    3,
                ]
            ],

            [<<<HELML
A:
:--: 1
:--: 2
:--:  3
B:  0
HELML,
                ['A' =>
                    [
                    '1',
                    '2',
                    3,
                    ],
                 'B' => 0,
                ]
            ],

            [<<<HELML
A: 1
B: 2
C:  3
HELML,
                [
                    'A' => '1',
                    'B' => '2',
                    'C' => 3,
                ]
            ],
            [<<<HELML
  A: 1
   B: -2
    C:  -3
HELML,
                [
                    'A' => '1',
                    'B' => '-2',
                    'C' => -3,
                ]
            ],
            [<<<HELML
  A:  T
   B:  N
    C:  -1.0
HELML,
                [
                    'A' => true,
                    'B' => null,
                    'C' => -1.0,
                ]
            ],
            [<<<HELML
  A:  F
   B:  U
    C:  -.0
HELML,
                [
                    'A' => false,
                    'B' => null,
                    'C' => -.0,
                ]
            ],
            [<<<HELML
ROOT1:
  :A:  123456789
  :B:  987654321
  :C:  -.0
ROOT2: X
HELML,
                [
                    'ROOT1' => [
                        'A' => 123456789,
                        'B' => 987654321,
                        'C' => -.0,
                        ],
                    'ROOT2' => 'X',
                ]
            ],
            [<<<HELML
ROOT1:
  :A:  123456789
  :B:  987654321
  :C:  -.0
  :SUB:
    ::X: X1
    ::Y: Y1
ROOT2:
  :SUB:
HELML,
                [
                    'ROOT1' => [
                        'A' => 123456789,
                        'B' => 987654321,
                        'C' => -.0,
                        'SUB' => [
                            'X' => 'X1',
                            'Y' => 'Y1',
                            ]
                        ],
                    'ROOT2' => ['SUB' => []],
                ]
            ],
            [<<<HELML
ROOT1:
>>
A:  123456789
B:  987654321
C:  -.0
SUB:
  :X: X1
  :Y: Y1
<<
ROOT2:
  :SUB:  N
HELML,
                [
                    'ROOT1' => [
                        'A' => 123456789,
                        'B' => 987654321,
                        'C' => -.0,
                        'SUB' => [
                            'X' => 'X1',
                            'Y' => 'Y1',
                            ]
                        ],
                    'ROOT2' => ['SUB' => null],
                ]
            ],
            [<<<HELML
ROOT1:>>
A:  123456789
B:  987654321
C:  -.0
SUB:>>
X: X1
Y: Y1
<<
D:  1
<<
E:  T
ROOT2:
  :SUB:  N
HELML,
                [
                    'ROOT1' => [
                        'A' => 123456789,
                        'B' => 987654321,
                        'C' => -.0,
                        'SUB' => [
                            'X' => 'X1',
                            'Y' => 'Y1',
                            ],
                            'D' => 1,
                        ],
                        'E' => true,
                    'ROOT2' => ['SUB' => null],
                ]
            ],
        ] as $k => $arr) {
            $arr[] = $spc_ch;
            yield $kv . $k . trim(substr($arr[0],0,8)) => $arr;
        }
        }
    }

    public function provideValueDecodingCases() {
        foreach([
            ' ' => 'a',
            '_' => 'b'
        ] as $spc_ch => $kv) {
            foreach([
                ['-', ''],
                ['"\n"', "\n"],

                [' 123', '123'],
                [' -123', '-123'],

                ['  1.1', 1.1],
                ['  -1.1', -1.1],

                ['  0.0', 0.0],
                ['  -0.0', -0.0],

                ['  1.0', 1.0],
                ['  -1.0', -1.0],

                ['  123', 123],
                ['  -123', -123],
                ['-' . base64_encode('123'), '123'],
                ['  T', true],
                ['  F', false],
                ['  N', null],
                ['  U', null],
                ['  INF', \INF],
                ['  NIF', -\INF],
                ['  NAN', \NAN],

                ['"HELLO WORLD"', "HELLO WORLD"],
                ['"HELLO\nWORLD"', "HELLO\nWORLD"],
                ['"\nHELLO\n WORLD\n"', "\nHELLO\n WORLD\n"],
                ['" HELLO WORLD"', " HELLO WORLD"],
                ['"HELLO WORLD "', "HELLO WORLD "],
                ['" HELLO WORLD "', " HELLO WORLD "],

                ["'HELLO WORLD'", "HELLO WORLD"],
                ["'HELLO\\nWORLD'", 'HELLO\nWORLD'],
                ["'\\nHELLO\\nWORLD\\n'", '\nHELLO\nWORLD\n'],
                ["' HELLO WORLD'", " HELLO WORLD"],
                ["'HELLO WORLD '", "HELLO WORLD "],
                ["' HELLO WORLD '", " HELLO WORLD "],

                ["`\nTEST MULTISTRING\n`", "TEST MULTISTRING"],
                ["`\nTEST\nMULTISTRING\nLITERAL\n`", "TEST\nMULTISTRING\nLITERAL"],

                ["-" . base64_encode("\t"), "\t"],

                [base64_encode("12345"), "12345"],
            ] as $k => $arr) {
                $arr[] = $spc_ch;
                yield $kv . $k . trim(substr($arr[0],0,6)) => $arr;
            }
        }
    }

    /**
     * @var HELML
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void {
        $this->object = new HELML;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void {
        
    }

    /**
     * @covers dynoser\HELML\HELML::encode
     * @dataProvider provideDecodingCases
     */
    public function testEncode($helml, $arr, $spc_ch): void {
        $obj = $this->object;
        if  ($spc_ch !== ' ') {
            $addPf = false;
            if ($spc_ch === '@') {
                $spc_ch  = '_';
                $addPf = true;
            }
            if ($spc_ch === '#') {
                $spc_ch = '=';
                $addPf = true;
            }
            $obj::$ADD_POSTFIX = $addPf;
            $result = $obj->decode($helml);
            if ($spc_ch === '_') {
                $helml = $obj->encode($result, 1);
            } else {
                $helml = $obj->encode($result, 2);
            }
            echo $helml . "\n";
        }
        $result = $obj->decode($helml);
        $this->assertSame($arr, $result);
    }

    public function encodeDataProvider()
    {
        return [
            [
                [
                    'name' => 'John',
                    'age' => 25,
                    'address' => [
                        'street' => '123 Main St',
                        'city' => 'New York',
                    ],
                ],
                [
                    "name: John",
                    "age:  25",
                    "",
                    "address:",
                    " :street: 123 Main St",
                    " :city: New York",
                    "#"
                ],
            ],

            [
                [
                    'key' => 'value',
                ],
                [
                    "key: value",
                ],
            ],

            [
                [
                    1,2,3,
                ],
                [
                    "--:  1",
                    "--:  2",
                    "--:  3",
                ],
            ],
            
            [
                [
                    '#' => 1 ,
                    '-' => 2,
                    '~' => 3,
                    '' => 444,
                ],
                [
                    "-Iw:  1",
                    "-LQ:  2",
                    "-fg:  3",
                    "-:  444",
                ],
            ],
        ];
    }

    /**
     * @covers dynoser\HELML\HELML::_encode
     * @dataProvider encodeDataProvider
     */
    public function test_encode($input_arr, $expected): void {
        $level = 0;
        $lvl_ch = ':';
        $spc_ch = ' ';
        $is_list = $this->object->isArrayList($input_arr);
        $results_arr = [];
        $this->object->_encode($input_arr, $results_arr, $level, $lvl_ch, $spc_ch, $is_list);
        $this->assertEquals($expected, $results_arr);
    }


    /**
     * @covers dynoser\HELML\HELML::decode
     * @dataProvider provideDecodingCases
     */
    public function testDecode($helml, $arr, $spc_ch): void {
        $obj = $this->object;
        if  ($spc_ch !== ' ') {
            $addPf = false;
            if ($spc_ch === '@') {
                $spc_ch  = '_';
                $addPf = true;
            }
            if ($spc_ch === '#') {
                $spc_ch = '=';
                $addPf = true;
            }
            $obj::$ADD_POSTFIX = $addPf;
            $result = $obj->decode($helml);
            if ($spc_ch === '_') {
                $helml = $obj->encode($result, 1);
            } else {
                $helml = $obj->encode($result, 2);
            }
        }
        $result = $obj->decode($helml);
        $this->assertSame($arr, $result);
    }

        
    public function providePrfixPostfix() {
        return  [
            [<<<CASE
            ~
            A:  1
            B:  2
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],

            [<<<CASE
            A:  1
            B:  2
            ~#: ~
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],
            
            [<<<CASE
            A=>>1
            B=>>2
            ~#=>~
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],
            
            [<<<CASE
            ~A.==1~B.==2~
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],
            
            [<<<CASE
            ~A.==1~B.==2~#.=~
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],
            
            [<<<CASE
            ~A=..1~B=..2~#=.~
            CASE,
            ['A'=> 1, 'B'=> 2],
            ],
        ];
    }

    /**
     * @covers dynoser\HELML\HELML::decode
     * @dataProvider providePrfixPostfix
     */
    public function testPrefixPostfix($helml, $expected) {
        $obj = $this->object;
        $result = $obj->decode($helml);
        $this->assertSame($expected, $result);
    }
    
    /**
     * @covers dynoser\HELML\HELML::valueDecode
     * @dataProvider provideValueDecodingCases
     */
    public function testValueDecode($helml, $expected, $spc_ch): void {
        if  ($spc_ch !== ' ') {
            $result = $this->object->valueDecode($helml);
            $helml = $this->object->valueEncode($result, $spc_ch);
        }
        $result = $this->object->valueDecode($helml, $spc_ch);
        if (\is_float($expected) && \is_nan($expected)) {
            $this->assertTrue(\is_nan($result));
        } else {
            $this->assertSame($expected, $result);
        }
    }

    /**
     * @covers dynoser\HELML\HELML::valueEncode
     * @dataProvider provideValueDecodingCases
     */
     public function testValueEncoder($helmlIn, $value, $spc_ch) {
         $helmlEncoded = $this->object->valueEncode($value, $spc_ch);
         if ($helmlEncoded == $helmlIn) {
            $this->assertSame($helmlIn, $helmlEncoded);
         } else {
            $backDecoded = $this->object->valueDecode($helmlEncoded, $spc_ch);
            if (\is_float($value) && \is_nan($value)) {
                $this->assertTrue(\is_nan($backDecoded));
            } else {
                $this->assertEquals($backDecoded, $value);
            }
         }
     }
     
    public function provideForIsArrayList() {
        foreach([
            [[0,0,0,0,0] , 5],
            [[0] , 1],
            [[1 => 1] , 0],
            [[0, '1' => 1], 2],
            [[   '1' => 1], 0],
            [['1' => 1, '0' => 1], 2],
            [['1' => 1, '2' => 1], 0],
            [[0, '1' => 1, '2' => 1], 3],
            [[0, '1' => 1, '3' => 1], 0],
            [[], 0],
            
            [[],  [0,0]],
            [[0,0,0,0,0] , [5,5]],
            [[0,0,0,0,0] , [6,0]],

            [[0, '1' => 1, '2' => 1], [3,3]],
            [[0, '1' => 1, '3' => 1], [3,0]],
            
        ] as $k => $arr) {
            yield $k => $arr;
        }
    }

    /**
     * @covers dynoser\HELML\HELML::isArrayList
     * @dataProvider provideForIsArrayList
     */
    public function testIsArrayList($arr, $expected): void {
        if (is_array($expected)) {
            $result = $this->object->isArrayList($arr, $expected[0]);
            $expected = $expected[1];
        } else {
            $result = $this->object->isArrayList($arr);
        }
        $this->assertSame($expected, $result);
    }
    
    public function providerForBase64U() {
        for($i=1; $i < 64; $i++) {
            $rndStr = self::RandomBytes($i);
            yield [$rndStr];
        }
    }

    /**
     * @dataProvider providerForBase64U
     * @covers dynoser\HELML\HELML::base64Uencode
     */
    public function testBase64Uencode($rndStr): void {
        $obj = $this->object;
        $encoded = $obj->base64Uencode($rndStr);
        $this->assertIsString($encoded);
        $decoded = $obj->base64Udecode($encoded);
        $this->assertSame($rndStr, $decoded);
    }

    /**
     * @covers dynoser\HELML\HELML::base64Udecode
     * @dataProvider providerForBase64U
     */
    public function testBase64Udecode($rndStr): void {
        $obj = $this->object;
        $encoded = $obj->base64Uencode($rndStr);
        $this->assertIsString($encoded);
        $decoded = $obj->base64Udecode($encoded);
        $this->assertSame($rndStr, $decoded);
    }
    
    
    public function providerHexDecode() {
        return [
            [
                '%6A %B %C',
                '6A0B0C'
            ],
            [
                '6A B C',
                '6A0B0C'
            ],
            [
                '%A  7B    C',
                '0A7B0C'
            ],
            [
                '01 23 45 67 89',
                '0123456789',
            ],
            [
                '0123456789',
                '0123456789',
            ],

        ];
    }
    /**
     * @covers dynoser\HELML\HELML::hexDecode
     * @dataProvider providerHexDecode
     */
    public function testHexDecode($hexStr, $expectedHex): void {
        $obj = $this->object;
        $decoded = $obj::hexDecode($hexStr);
        $this->assertIsString($decoded);
        $this->assertSame(\hex2bin($expectedHex), $decoded);
    }
}
