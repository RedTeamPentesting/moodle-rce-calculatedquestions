#! /usr/bin/python
########################################
#                                      #
#  RedTeam Pentesting GmbH             #
#  kontakt@redteam-pentesting.de       #
#  https://www.redteam-pentesting.de/  #
#                                      #
########################################

import argparse
import re

parser = argparse.ArgumentParser(description='Generate php code')
parser.add_argument('function', help='The function name to translate into compatible PHP code')

DEBUG = False

def debug_print(out_string):
    if DEBUG:
        print(out_string)

def generate_numbers(function_name):
    nan_index = 0
    minus_group = 0
    res = ["", "", "", ""] # We need at most four groups (when we have to XOR with "-" and need two numbers)
    function_name = function_name.upper()

    for i in range(len(function_name)):
        nan_index = i % 3
        nan_char = 'NAN'.encode('ascii')[nan_index]
        fun_char = function_name.encode('ascii')[i]
        target_bits = nan_char ^ fun_char # what we have to change to get the result we want
        debug_print("Target: {0:08b}".format(target_bits))

        if (target_bits & 2**7 != 0) or (target_bits & 2**6 != 0): # We cannot change these bits
            print("Unsupported character: {}".format(chr(fun_char)))
            return

        was_minus = False
        if (target_bits & 2**5 != 0) or (target_bits & 2**4 != 0): # Requires minus to work
            debug_print("{} requires -".format(chr(fun_char)))
            target_bits ^= ord('-')
            debug_print("New target: {0:08b}".format(target_bits))
            was_minus = True

        # Find suitable numbers to XOR
        first_occurence = 0
        for i in range(3,-1,-1):
            if target_bits & 2**i:
                first_occurence = i
                break
        xor_num1 = 2**first_occurence
        remainder = (target_bits & 15) ^ xor_num1
        if remainder > 9 or remainder < 0:
            print("Something went wrong: Found numbers for XOR not in allowed range")
            return

        # To prevent -0 (which is evaluated as 0 and thus removes the "-")
        if xor_num1 == 0:
            xor_num1 = 1
            if remainder != 0:
                print("xor_num1 is 0 but remainder not, this should never happen")
                return
            remainder = 1

        # "Roll" for every minus to avoid "--"
        res[minus_group] += "{}".format(xor_num1)
        res[(minus_group + 1) % 4] += "{}".format(remainder)
        res[(minus_group +2) % 4] += "0"
        if was_minus:
            minus_group = (minus_group + 3) % 4
            res[minus_group] += "-"
        else:
            res[(minus_group + 3) % 4] += "0"

        debug_print("{}".format(xor_num1))
        debug_print("{}".format(remainder))
        debug_print(res)
        debug_print("--------")

    # Fix trailing "-" by simply adding a number, which is ignored
    for index, number_part in enumerate(res):
        if number_part.endswith("-"):
            res[index] = number_part + "1"
    debug_print(res)
    return res

def generate_string(function_name, res):
    # Generate final string. Start with NAN:
    nan_count = (len(function_name) + 2) // 3
    if nan_count < 3: # We need at least two, otherwise NAN is interpreted as number
        acos_part = "(acos(2) . 0+acos(2))"
    else:
        acos_part = "(acos(2) . 0+acos(2)" + " . 0+acos(2)" * (nan_count-2) + ")"
    debug_print(acos_part)

    # Create the number parts:
    number_parts = ""
    for number_part in res:
        number_parts += " ^ ("
        was_minus = False
        for index, char in enumerate(number_part):
            if char == "-":
                was_minus = True
            elif was_minus:
                number_parts += "-" + char
                was_minus = False
            else:
                number_parts += char
            # add " . " if there are more numbers to add
            if index == len(number_part) - 1 or was_minus:
                continue
            else:
                number_parts += " . "
        number_parts += ")"
    return "(" + acos_part + number_parts + ")"

def main():
    function_name = args.function
    function_name = function_name.strip()
    validation_regex = re.compile("^[_A-Za-z]*$")
    if not validation_regex.match(function_name):
        print("Function contains unsupported characters. Only uppercase letters and '_' are currently supported")
        return

    res = generate_numbers(function_name)

    res_string = generate_string(function_name, res)

    print(res_string)

if __name__ == "__main__":
    args = parser.parse_args()
    main()
