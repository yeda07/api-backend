<?php
namespace App\DTOs;

class B2CEntityDTO
{
    public static function validate(array $data)
    {
        validator($data, [
            'first_name' => 'required|string|max:150',
            'last_name'  => 'nullable|string|max:150',
            'email'      => 'nullable|email'
        ])->validate();

        return $data;
    }
}
