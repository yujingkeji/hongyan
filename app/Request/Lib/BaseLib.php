<?php

declare(strict_types=1);

namespace App\Request\Lib;

use App\Common\Lib\Arr;

class BaseLib
{
    public function messages(): array
    {
        return [
            'required'             => 'The :attribute field is required.',
            'min'                  => 'min size of :attribute must be greater than :min',
            'same'                 => 'The :attribute and :other must match.',
            'size'                 => 'The :attribute must be exactly :size.',
            'between'              => 'The :attribute value :input is not between :min - :max.',
            'in'                   => 'The :attribute must be one of the following types: :values',
            'accepted'             => 'The :attribute must be accepted.',
            'active_url'           => 'The :attribute is not a valid URL.',
            'after'                => 'The :attribute must be a date after :date.',
            'after_or_equal'       => 'The :attribute must be a date after or equal to :date.',
            'alpha'                => 'The :attribute may only contain letters.',
            'alpha_dash'           => 'The :attribute may only contain letters, numbers, and dashes.',
            'alpha_num'            => 'The :attribute may only contain letters and numbers.',
            'array'                => 'The :attribute must be an array.',
            'before'               => 'The :attribute must be a date before :date.',
            'before_or_equal'      => 'The :attribute must be a date before or equal to :date.',
            'boolean'              => 'The :attribute field must be true or false.',
            'confirmed'            => 'The :attribute confirmation does not match.',
            'date'                 => 'The :attribute is not a valid date.',
            'date_format'          => 'The :attribute does not match the format :format.',
            'different'            => 'The :attribute and :other must be different.',
            'digits'               => 'The :attribute must be :digits digits.',
            'digits_between'       => 'The :attribute must be between :min and :max digits.',
            'dimensions'           => 'The :attribute has invalid image dimensions.',
            'distinct'             => 'The :attribute field has a duplicate value.',
            'email'                => 'The :attribute must be a valid email address.',
            'exists'               => 'The selected :attribute of :value  does not exist.',
            'file'                 => 'The :attribute must be a file.',
            'filled'               => 'The :attribute field is required.',
            'gt'                   => 'The :attribute must be greater than :value',
            'gte'                  => 'The :attribute must be great than or equal to :value',
            'image'                => 'The :attribute must be an image.',
            'in_array'             => 'The :attribute field does not exist in :other.',
            'integer'              => 'The :attribute must be an integer.',
            'ip'                   => 'The :attribute must be a valid IP address.',
            'ipv4'                 => 'The :attribute must be a valid IPv4 address.',
            'ipv6'                 => 'The :attribute must be a valid IPv6 address.',
            'json'                 => 'The :attribute must be a valid JSON string.',
            'lt'                   => 'The :attribute must be less than :value',
            'lte'                  => 'The :attribute must be less than or equal to :value',
            'max'                  => 'The :attribute may not be greater than :max.',
            'mimes'                => 'The :attribute must be a file of type: :values.',
            'mimetypes'            => 'The :attribute must be a file of type: :values.',
            'not_in'               => 'The selected :attribute field of :value does not exist.',
            'not_regex'            => 'The :attribute cannot match a given regular rule.',
            'numeric'              => 'The :attribute must be a number.',
            'present'              => 'The :attribute field must be present.',
            'regex'                => 'The :attribute format is invalid.',
            'required_if'          => 'The :attribute field is required when :other is :value.',
            'required_unless'      => 'The :attribute field is required unless :other is in :values.',
            'required_with'        => 'The :attribute field is required when :values is present.',
            'required_with_all'    => 'The :attribute field is required when :values is present.',
            'required_without'     => 'The :attribute field is required when :values is not present.',
            'required_without_all' => 'The :attribute field is required when none of :values are present.',
            'starts_with'          => 'The :attribute must be start with :values ',
            'string'               => 'The :attribute must be a string.',
            'timezone'             => 'The :attribute must be a valid zone.',
            'unique'               => 'The :attribute has already been taken.',
            'uploaded'             => 'The :attribute failed to upload.',
            'url'                  => 'The :attribute format is invalid.',
            'uuid'                 => 'The :attribute is invalid UUID.',
            'max_if'               => 'The :attribute may not be greater than :max when :other is :value.',
            'min_if'               => 'The :attribute must be at least :min when :other is :value.',
            'between_if'           => 'The :attribute must be between :min and :max when :other is :value.',
        ];
    }


}
