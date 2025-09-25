<?php

declare(strict_types=1);

namespace App\Rules;

use Hyperf\Validation\Contract\Rule;

class RulesUnique implements Rule
{
    protected string $fieldName;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // 获取所有指定字段的值
        $fieldValues = array_column($value, $this->fieldName);

        // 检查是否有重复的值
        return count($fieldValues) === count(array_unique($fieldValues));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return sprintf('%s 不能重复', $this->fieldName);
    }
}
