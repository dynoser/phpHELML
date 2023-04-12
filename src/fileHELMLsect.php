<?php
namespace dynoser\HELML;

/**
 * This class implements selective loading of sections from a file in HELML format
 */
class LoadHELMLfile {
    
    public static $add_section_comments = true;
    
    /**
     * The $fileName parameter must contain the path to the file to be opened.
     * The second parameter $sections_arr contains an array of section names to be read from the file.
     * If $sections_arr is empty, the function will return all lines from the file except empty lines and comments
     * The function skips empty lines and those starting with "#" character (comments)
     * The function looks for HELML-sections from the $sections_arr array in the file, when a section is found,
     *  it starts reading lines within that section, and stops reading when the next section or end of file is reached.
     * The function returns an array of strings contained in the selected sections.
     * 
     * if $only_first_occ is true, then only the first occurrence for each of the specified sections will be returned
     *   after the section is found for the first time, it is excluded from the search array.
     *   if all sections are found, then exit before the end of file is reached.
     *   This mode is recommended and selected by default as it is the fastest.
     * if $only_first_occ is false, then the search for the sections specified will go to the end of the file,
     *  and all found options will be returned.
     * 
     * @param string $fileName HELML-file path
     * @param array|string $sections_arr Array of strings, or comma-separated string
     * @param bool $only_first_occ Default true
     * @return string
     */
    public static function Load($fileName, $sections_arr = [], $only_first_occ = true) {
        if (!is_array($sections_arr)) {
            if (!is_string($sections_arr)) {
                throw new InvalidArgumentException("Parameter sections_arr must be array(of string) or string(comma-separated)");
            }
            $sections_arr = $sections_arr ? explode(',', $sections_arr) : [];
        }
        $results_arr = self::_LoadSections($fileName, $sections_arr, $only_first_occ);
        return implode("\n", $results_arr);
    }

    public static function _LoadSections($fileName, $req_sections_arr = [], $only_first_occ = false) {

        // Prepare $sections_arr
        $sections_arr = [];
        if (!is_array($req_sections_arr)) {
            throw new InvalidArgumentException("Parameter sections_arr must be array");
        }
        if ($req_sections_arr) {
            foreach($req_sections_arr as $st) {
                $st = trim($st);
                if (substr($st, 1) === ':') {
                    // remove ":" from end of string
                    $st = substr($st, 0, -1);
                }
                $l = strlen($st);
                if (!$l) continue;
                $sections_arr[$st] = $l;
            }
            $get_all = false;
        } else {
            $get_all = true;
        }

        // Results will be posted here
        $results_arr = [];

        // Open source file
        $f = fopen($fileName, 'rb');
        if (!$f) {
            // Can't read file
            return NULL;
        }

        $in_section_mode = false; // Section read mode (default off)
        $sel_key = ''; // Current section name
        $level = 0; // Current section level

        while (!feof($f)) {
            $st = fgets($f);
            if (false === $st) break; // break on err
            $st = trim($st);
             // Skip empty strings and comments
            if (!strlen($st) || '#' === $st[0]) continue;

            if (!$get_all) {
                if ($in_section_mode) {
                    // Calculate level of current string
                    $lvl_st = 0;
                    while (substr($st, $lvl_st, 1) === ':') {
                        $lvl_st++;
                    }
                    // section end check
                    if ($lvl_st <= $level) {
                        if (self::$add_section_comments) {
                            $results_arr[] = '# [' . $sel_key . '] END';
                            $results_arr[] = '';
                        }
                        $in_section_mode = false;
                    }
                }
                if (!$in_section_mode) {
                    // Check no-more-sections to read
                    if (empty($sections_arr)) break;
                    // Check one of section begin
                    foreach ($sections_arr as $sel_key => $key_len) {
                        if (substr($st, 0, $key_len) === $sel_key) {
                            // skip strings if not contain ":" or EOL after key
                            if (strlen($st) > $key_len && $st[$key_len] !== ':') continue;
                            
                            $in_section_mode = true;
                            // calculate current section nesting level
                            $level = 0;
                            while (substr($st, $level, 1) === ':') {
                                $level++;
                            }
                            if (self::$add_section_comments) {
                                $results_arr[] = '# [' . $sel_key . '] BEGIN';
                            }
                            $results_arr[] = $st;
                            if ($only_first_occ) {
                                unset($sections_arr[$sel_key]);
                            }
                            break;
                        }
                    }
                    continue;
                }
            }
            $results_arr[] = $st;
        }
        fclose($f);
        
        if ($only_first_occ && self::$add_section_comments && !empty($sections_arr)) {
            $results_arr[] = '# These requested sections were not found:';
            foreach($sections_arr as $sel_key => $sel_len) {
                $results_arr[] = ' # ' . $sel_key;
            }
        }
        
        return $results_arr;
    }
}
