<?php

namespace PajuranCodes\Configuration;

use function trim;
use function explode;
use function array_map;
use function strtolower;

/**
 * Get the value of an environment variable.
 * 
 * Based on the specification of the "variables_order" php.ini 
 * directive, the environment variable is read from $_SERVER first, 
 * then from $_ENV. The getenv() function is not used, because it 
 * poses a security/unexpected results risk by not beeing thread safe.
 * 
 * Optionally, the value of the environment variable 
 * can be converted to a native PHP value, as follows:
 * 
 * If the value is the string "null", then it is converted to NULL.
 * 
 * If the value is a string representation of a boolean value, e.g. 
 * "true", "on", "yes", "1", "false", "off", "no", or "0", then it 
 * is converted to the corresponding boolean value (TRUE or FALSE).
 * 
 * @link https://www.php.net/manual/en/ini.core.php#ini.variables-order Description of core php.ini directives: variables_order
 * 
 * @param string $name The name of an environment variable.
 * @param bool $convertToNative (optional) A flag to indicate if the value of the environment 
 * variable should be converted to a native PHP value (NULL, TRUE or FALSE) or not.
 * @return null|bool|string The value of the environment variable, optionally 
 * converted to a PHP native value.
 * @throws \InvalidArgumentException The environment variable is not defined.
 * 
 * @author pajurancodes
 */
function env(string $name, bool $convertToNative = false): null|bool|string {
    $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

    if (!isset($value)) {
        throw new \InvalidArgumentException(
                'The environment variable "' . $name . '" is not defined.'
        );
    }

    if ($convertToNative) {
        $value = envConvertToNative($value);
    }

    return $value;
}

/**
 * Get an indexed array built from the comma 
 * separated string value of an environment variable.
 * 
 * If the value of the environment variable is an empty string, 
 * then an empty array is returned. Otherwise, the string value 
 * is converted to an indexed array with trimmed element values.
 * 
 * Optionally, the value of the environment variable 
 * can be converted to a native PHP value, as follows:
 * 
 * If the value is the string "null", then it is converted to NULL.
 * 
 * If the value is a string representation of a boolean value, e.g. 
 * "true", "on", "yes", "1", "false", "off", "no", or "0", then it 
 * is converted to the corresponding boolean value (TRUE or FALSE).
 * 
 * @param string $name The name of an environment variable.
 * @param bool $convertToNative (optional) A flag to indicate if the values of the returned 
 * array should be converted to native PHP values (NULL, TRUE or FALSE) or not.
 * @return (null|bool|string)[] An indexed array with trimmed values, optionally 
 * converted to PHP native values, built from the comma separated 
 * string value of the environment variable.
 * 
 * @author pajurancodes
 */
function envList(string $name, bool $convertToNative = false): array {
    $value = env($name, false);

    if (trim($value) === '') {
        return [];
    }

    $array = array_map('trim', explode(',', $value));

    if ($convertToNative) {
        $array = array_map('envConvertToNative', $array);
    }

    return $array;
}

/**
 * Convert a string value to a native PHP value, as follows:
 * 
 * If the value is the string "null", then it is converted to NULL.
 * 
 * If the value is a string representation of a boolean value, e.g. 
 * "true", "on", "yes", "1", "false", "off", "no", or "0", then it 
 * is converted to the corresponding boolean value (TRUE or FALSE).
 * 
 * @param string $value A string value to be converted.
 * @return null|bool|string Either NULL, TRUE or FALSE, or the given string value.
 * 
 * @author pajurancodes
 */
function envConvertToNative(string $value): null|bool|string {
    return match (strtolower($value)) {
        'null' => null,
        'true', 'on', 'yes', '1' => true,
        'false', 'off', 'no', '0' => false,
        default => $value,
    };
}
