<?php

namespace App\Plugins\Weathermap\Services;

/**
 * EditorValidatorService - Input validation for editor operations
 * 
 * Extracted from lib/editor/EditorFunctions.php
 * Provides consistent validation rules for map configuration parameters
 */
class EditorValidatorService
{
    /**
     * Validate bandwidth value (supports K, M, G, T suffixes)
     */
    public function validateBandwidth(string $bw): bool
    {
        return preg_match('/^(\d+\.?\d*[KMGT]?)$/', $bw) === 1;
    }

    /**
     * Validate that input matches one of allowed values
     */
    public function validateOneOf(string $input, array $valid = [], bool $case_sensitive = false): bool
    {
        if (!$case_sensitive) {
            $input = strtolower($input);
        }

        foreach ($valid as $v) {
            $check_val = $case_sensitive ? $v : strtolower($v);
            if ($check_val === $input) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate integer value
     */
    public function validateInteger(string $value): bool
    {
        return preg_match('/^\-*\d+$/', $value) === 1;
    }

    /**
     * Validate float value
     */
    public function validateFloat(string $value): bool
    {
        return preg_match('/^\d+\.\d+$/', $value) === 1;
    }

    /**
     * Validate yes/no boolean
     */
    public function validateYesNo(string $value): bool
    {
        return preg_match('/^(y|n|yes|no)$/i', $value) === 1;
    }

    /**
     * Validate SQL date format (YYYY-MM-DD)
     */
    public function validateSqlDate(string $value): bool
    {
        return preg_match('/^\d\d\d\d\-\d\d\-\d\d$/i', $value) === 1;
    }

    /**
     * Validate IP address
     */
    public function validateIp(string $value): bool
    {
        return preg_match(
            '/^((\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)(?:\.(\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)){3})$/',
            $value
        ) === 1;
    }

    /**
     * Validate alphabetic characters only
     */
    public function validateAlpha(string $value): bool
    {
        return preg_match('/^[A-Za-z]+$/', $value) === 1;
    }

    /**
     * Validate alphanumeric characters only
     */
    public function validateAlphanum(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
    }

    /**
     * Validate against a type spec
     */
    public function validateByType(string $value, string $type): bool
    {
        return match ($type) {
            'int' => $this->validateInteger($value),
            'float' => $this->validateFloat($value),
            'yesno' => $this->validateYesNo($value),
            'sqldate' => $this->validateSqlDate($value),
            'any' => true,
            'ip' => $this->validateIp($value),
            'alpha' => $this->validateAlpha($value),
            'alphanum' => $this->validateAlphanum($value),
            'bandwidth' => $this->validateBandwidth($value),
            default => false,
        };
    }

    /**
     * Extract and validate parameters from array
     * 
     * $paramarray format: [[$varname, $vartype, $required], ...]
     * where $vartype can be: int, float, yesno, sqldate, any, ip, alpha, alphanum, bandwidth
     */
    public function extractWithValidation(array $array, array $paramarray, string $prefix = ''): array
    {
        $all_present = true;
        $candidates = [];

        foreach ($paramarray as $var) {
            [$varname, $vartype, $varreqd] = $var;

            if ($varreqd === 'req' && !array_key_exists($varname, $array)) {
                $all_present = false;
            }

            if (array_key_exists($varname, $array)) {
                $varvalue = $array[$varname];

                if (!$this->validateByType($varvalue, $vartype)) {
                    $all_present = false;
                } else {
                    $candidates["{$prefix}{$varname}"] = $varvalue;
                }
            }
        }

        return [$all_present, $candidates];
    }
}
