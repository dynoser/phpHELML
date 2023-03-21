# HELML

![helml-logo](https://github.com/dynoser/HELML/raw/master/logo/icon.png)

## Overview
HELML is a Human-Editable List Markup Language, or HEader-Like Markup Language
This is a PHP library that provides an easy way to encode and decode array data structures into human-readable and editable strings.
This can be used for cross-platform data transfer, configuration files, data serialization, and other purposes.

See more: [HELML format definition](https://github.com/dynoser/HELML/blob/master/README-HELML_en.md)


# Installation
To install HELML, simply use composer:

```bash
composer require dynoser/helml
```

# Usage

Here's a quick example of how to use the HELML library:

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
print($encoded_data)

# Decode the HELML string back into a data structure
$decoded_data = HELML::decode($encoded_data);
print($decoded_data)
```
encoded_data:
```console
key1: value1
key2:
:0:  1
:1:  2
:2:  3
key3
:nested_key: nested_value
```

# Features
Encode and decode arrays into human-readable and editable strings.
Support for URL mode, which uses different characters to separate levels and spaces.
Customizable value encoding and decoding functions.
Easy-to-understand format that can be used for various applications.

# API

### **HELML::encode**($arr, $url_mode=False)

Encode a data array into a HELML string.

- **$arr**: The input data array to be encoded.
- **$url_mode** (bool, optional): A boolean indicating if the URL mode should be used. Defaults to False.

Returns:

- string: The encoded HELML string.

### **HELML::decode**($src_rows)

Decode a HELML formatted string or list of strings into a nested dictionary.

- **$src_rows**: The HELML input as a string or strings-array.

Returns:

- array: The decoded array.


# License
This project is licensed under the Apache-2 License.
