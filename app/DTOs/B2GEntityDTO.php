<?php
namespace App\DTOs;

class B2GEntityDTO
{
    public static function validate(array $data)
    {
        validator($data, [
            'institution_name' => 'required|string|max:200',
            'department'       => 'nullable|string|max:150'
        ])->validate();

        return $data;
    }
}
