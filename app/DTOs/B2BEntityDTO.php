<?php

namespace App\DTOs;

class B2BEntityDTO
{
    public static function validate(array $data)
    {
        validator($data, [
            'company_name' => 'required|string|max:200',
            'document'     => 'required|string|max:50',
            'email'        => 'nullable|email'
        ])->validate();

        return $data;
    }
}
