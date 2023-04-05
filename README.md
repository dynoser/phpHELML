# HELML

![helml-logo](https://github.com/dynoser/HELML/raw/master/logo/icon.png)

## PHP package

* [PHP Source code -- phpHELML](https://github.com/dynoser/phpHELML)
* [HELML format definition (en)](https://github.com/dynoser/HELML/blob/master/docs/README-HELML_en.md)
* [Описание формата HELML (ru)](https://github.com/dynoser/HELML/blob/master/docs/README-HELML_ru.md)

## Implementations

- [HELML on Python](https://github.com/dynoser/HELML/blob/master/Python)
- [HELML on JavaScript](https://github.com/dynoser/HELML/blob/master/JavaScript)
- [HELML Visual Studio Code plugin](https://github.com/dynoser/HELML/blob/master/helml-vscode-plugin)

# Installation (PHP)
To install HELML, simply use composer:

```bash
composer require dynoser/helml
```

or, You may copy and use files directly:

```PHP
use dynoser\HELML\HELML;  # it means that the HELML class is in the namespace "dynoser\HELML"
use dynoser\HELML\LoadHELMLfile; # it means that the LoadHELMLfile class is in the namespace "dynoser\HELML"
require_once "src/HELML.php"; // specify the correct path to file
require_once 'src/LoadHELMLfile.php';// or use "autoload.php" from composer to autoload

```

# Usage

This package contains two independent classes:
 * class `HELML` - encoder/decoder HELML-format
 * class `LoadHELMLfile` - selective data-section loader from HELML file

# class HELML

Here's a quick example of how to use the `HELML` class:

```PHP
use dynoser\HELML\HELML;
require_once 'src/HELML.php'; // path to HELML.php file, or require "autoload.php"

# Example data structure

$data = [
    "key1" => "value1",
    "key2" => [1, 2, 3],
    "key3" => [
        "nested_key" => "nested_value"
    ]
];

# Encode the data structure into a HELML string
$encoded_data = HELML::encode($data);
print_r($encoded_data)

# Decode the HELML string back into a data structure
$decoded_data = HELML::decode($encoded_data);
```
encoded_data:
```console
key1: value1

key2:
 :--:  1
 :--:  2
 :--:  3

key3
 :nested_key: nested_value
```

# Features
Encode and decode data arrays to/from HELML.

# API

### **`HELML::encode`**($arr, $url_mode=False)

Encode a data array into a HELML string.

- **$arr** The input data array to be encoded.
- **$url_mode** (bool, optional): A boolean indicating if the URL mode should be used. Defaults to False.

Returns:

- string: The encoded HELML string.

### **`HELML::decode`**($src_rows)

Decode a HELML formatted string or list of strings into a nested dictionary.

- **$src_rows** The HELML input as a string or strings-array.

Returns:

- Array: The decoded nested array.


# Class LoadHELMLfile

This class implements selective loading of sections from a file in HELML format.

```PHP
use dynoser\HELML\LoadHELMLfile;

require_once 'src/LoadHELMLfile.php';// path to file, or require "autoload.php"

/*
For example, we have file "testdata.helml" contained this:

A:
 :X: 1
 :Y: 2
# Comment before section
B
 :X: 5
 :Y: 6

Core
 :Test: data
 :Nested key: value
C:
 # This is a Comment string
 :Other: data

DD: DD-Value
D: D-value
E: is E
F:
 :nested:
  ::--: First
  ::--: Second

*/

LoadHELMLfile::$add_section_comments = false; // switch off auto-comments

$encoded_data = LoadHELMLfile::Load('testdata.helml', ['B:', 'C', 'D']);

print_r($encoded_data)
```
Result string:
```sh
B
:X: 5
:Y: 6
C:
:Other: data
D: D-value
```
In result we got data only from 'B', 'C' and 'D' sections, without comments and empty-lines

In this way, it is very convenient to get sections from the root level.

You can get nested keys in exactly the same way, however, it should be remembered that
 we will get these structures without the structures in which they are located.

For example, we can get ':nested' key from previous example file, and we got:
```php
$encoded_data = LoadHELMLfile::Load('testdata.helml', [':nested']);

print_r($encoded_data);
```
Result:
```sh
:nested:
::--: First
::--: Second
```

The parsing of this sample will be as follows:
```php
(
    [nested] => Array
        (
            [0] => First
            [1] => Second
        )

)
```

Note:
 - Specifying "`:`" at the end of the section name is optional. Inside these colons are removed.
 - All the level colons at the beginning need to be specified if we want to get a non-root level section.
 - Parameter `$only_first_occ`, which allows you to get all occurrences of the listed sections, if there are several of them in the file


By setting the `$only_first_occ` parameter to `false`, you can extract all variants of the values of some nested key.
For example, let's get all the values of the nested key X from the examples above:
```php
$encoded_data = LoadHELMLfile::Load('testdata.helml', [':Y'], false);

print_r($encoded_data);
```
Result:
```sh
:Y: 2
:Y: 6
```

## Independence

 * Note that both classes `HELML` and `LoadHELMLfile` do not have any dependencies and can be used independently of each other.


## See also:
 * plugin "HELML" for Visual Studio Code
 * Try online [HELML plugin](https://marketplace.visualstudio.com/items?itemName=dynoser.helml) in [vscode.dev](https://vscode.dev)


# License
This project is licensed under the Apache-2 License.
