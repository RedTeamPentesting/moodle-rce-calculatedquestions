<?php
// Parts of this file are copied from Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// Modified get_string function to print errors
function get_string($identifier, $component = '', $a = null, $lazyload = false) {
    return "error $identifier with value: $a";
}

// Excerpts from the qtype_calculated class of Moodle
// question/type/calculated/questiontype.php
class qtype_calculated {
    /**
     * @var string a placeholder is a letter, followed by zero or more alphanum chars (as well as space, - and _ for readability).
     */
    const PLACEHOLDER_REGEX_PART = '[[:alpha:]][[:alpha:][:digit:]\-_\s]*';

    /**
     * @var string REGEXP for a placeholder, wrapped in its {...} delimiters, with capturing brackets around the name.
     */
    const PLACEHODLER_REGEX = '~\{(' . self::PLACEHOLDER_REGEX_PART . ')\}~';

    public function substitute_variables($str, $dataset) {
        // Testing for wrong numerical values.
        // All calculations used this function so testing here should be OK.

        foreach ($dataset as $name => $value) {
            $val = $value;
            if (! is_numeric($val)) {
                $a = new stdClass();
                $a->name = '{'.$name.'}';
                $a->value = $value;
                echo get_string('notvalidnumber', 'qtype_calculated', $a);
                $val = 1.0;
            }
            if ($val <= 0) { // MDL-36025 Use parentheses for "-0" .
                $str = str_replace('{'.$name.'}', '('.$val.')', $str);
            } else {
                $str = str_replace('{'.$name.'}', $val, $str);
            }
        }
        return $str;
    }

    public function substitute_variables_and_eval($str, $dataset) {
        $formula = $this->substitute_variables($str, $dataset);
        if ($error = qtype_calculated_find_formula_errors($formula)) {
            return $error;
        }
        // Calculate the correct answer.
        if (empty($formula)) {
            $str = '';
        } else if ($formula === '*') {
            $str = '*';
        } else {
            $str = null;
            eval('$str = '.$formula.';');
        }
        return $str;
    }
}

// From Moodle: question/type/calculated/questiontype.php
function qtype_calculated_find_formula_errors($formula) {
    foreach (['//', '/*', '#', '<?', '?>'] as $commentstart) {
        if (strpos($formula, $commentstart) !== false) {
            return get_string('illegalformulasyntax', 'qtype_calculated', $commentstart);
        }
    }

    // Validates the formula submitted from the question edit page.
    // Returns false if everything is alright
    // otherwise it constructs an error message.
    // Strip away dataset names. Use 1.0 to remove valid names, so illegal names can be identified later.
    $formula = preg_replace(qtype_calculated::PLACEHODLER_REGEX, '1.0', $formula);

    // Strip away empty space and lowercase it.
    $formula = strtolower(str_replace(' ', '', $formula));

    // Only mathematical operators are supported. Bitwise operators are not safe.
    // Note: In this context, ^ is a bitwise operator (exponents are represented by **).
    $safeoperatorchar = '-+/*%>:\~<?=!';
    $operatorornumber = "[{$safeoperatorchar}.0-9eE]";

    // Validate mathematical functions in formula.
    while (preg_match("~(^|[{$safeoperatorchar},(])([a-z0-9_]*)" .
            "\\(({$operatorornumber}+(,{$operatorornumber}+((,{$operatorornumber}+)+)?)?)?\\)~",
            $formula, $regs)) {
        switch ($regs[2]) {
            // Simple parenthesis.
            case '':
                if ((isset($regs[4]) && $regs[4]) || strlen($regs[3]) == 0) {
                    return get_string('illegalformulasyntax', 'qtype_calculated', $regs[0]);
                }
                break;

                // Zero argument functions.
            case 'pi':
                if (array_key_exists(3, $regs)) {
                    return get_string('functiontakesnoargs', 'qtype_calculated', $regs[2]);
                }
                break;

            // Single argument functions (the most common case).
            case 'abs': case 'acos': case 'acosh': case 'asin': case 'asinh':
            case 'atan': case 'atanh': case 'bindec': case 'ceil': case 'cos':
            case 'cosh': case 'decbin': case 'decoct': case 'deg2rad':
            case 'exp': case 'expm1': case 'floor': case 'is_finite':
            case 'is_infinite': case 'is_nan': case 'log10': case 'log1p':
            case 'octdec': case 'rad2deg': case 'sin': case 'sinh': case 'sqrt':
            case 'tan': case 'tanh':
                if (!empty($regs[4]) || empty($regs[3])) {
                    return get_string('functiontakesonearg', 'qtype_calculated', $regs[2]);
                }
                break;

                // Functions that take one or two arguments.
            case 'log': case 'round':
                    if (!empty($regs[5]) || empty($regs[3])) {
                        return get_string('functiontakesoneortwoargs', 'qtype_calculated', $regs[2]);
                    }
                break;

                // Functions that must have two arguments.
            case 'atan2': case 'fmod': case 'pow':
                        if (!empty($regs[5]) || empty($regs[4])) {
                            return get_string('functiontakestwoargs', 'qtype_calculated', $regs[2]);
                        }
                break;

                // Functions that take two or more arguments.
            case 'min': case 'max':
                    if (empty($regs[4])) {
                        return get_string('functiontakesatleasttwo', 'qtype_calculated', $regs[2]);
                    }
                break;

            default:
                return get_string('unsupportedformulafunction', 'qtype_calculated', $regs[2]);
        }

        // Exchange the function call with '1.0' and then check for
        // another function call...
        if ($regs[1]) {
            // The function call is proceeded by an operator.
            $formula = str_replace($regs[0], $regs[1] . '1.0', $formula);
        } else {
            // The function call starts the formula.
            $formula = preg_replace('~^' . preg_quote($regs[2], '~') . '\([^)]*\)~', '1.0', $formula);
        }
    }

    if (preg_match("~[^{$safeoperatorchar}.0-9eE]+~", $formula, $regs)) {
        return get_string('illegalformulasyntax', 'qtype_calculated', $regs[0]);
    } else {
        // Formula just might be valid.
        return false;
    }
}

$dataset = array("a" => 1, "b" => 2, "c" => 3, "negative" => -987654321);
$calc = new qtype_calculated;
$out = $calc->substitute_variables_and_eval($argv[1], $dataset);
echo "$out\n";

?>
