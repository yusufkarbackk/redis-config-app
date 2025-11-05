<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class UniqueFieldNamesRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            return false;
        }

        // Check if there's at least one field
        if (empty($value) || count($value) < 1) {
            Log::warning('No fields provided in application', [
                'attribute' => $attribute,
                'value' => $value
            ]);
            return false;
        }

        $fieldNames = array_filter(array_column($value, 'name'));

        // Check for duplicate names
        if (count($fieldNames) !== count(array_unique($fieldNames))) {
            Log::warning('Duplicate field names detected', [
                'field_names' => $fieldNames
            ]);
            return false;
        }

        // Validate each field structure
        foreach ($value as $index => $field) {
            if (!is_array($field)) {
                Log::warning('Invalid field structure in validation', [
                    'field_index' => $index,
                    'field_data' => $field
                ]);
                return false;
            }

            // Check required fields
            if (empty($field['name']) || empty($field['data_type'])) {
                Log::warning('Missing required field data', [
                    'field_index' => $index,
                    'field_name' => $field['name'] ?? 'empty',
                    'data_type' => $field['data_type'] ?? 'empty'
                ]);
                return false;
            }

            // Validate data type
            $validDataTypes = ['string', 'number', 'boolean', 'json'];
            if (!in_array($field['data_type'], $validDataTypes)) {
                Log::warning('Invalid data type', [
                    'field_index' => $index,
                    'field_name' => $field['name'],
                    'data_type' => $field['data_type']
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'At least one field is required. Field names must be unique within an application and all fields must have valid names and data types.';
    }
}