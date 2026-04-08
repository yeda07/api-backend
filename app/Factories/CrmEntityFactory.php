<?php
namespace App\Factories;

use App\DTOs\B2BEntityDTO;
use App\DTOs\B2CEntityDTO;
use App\DTOs\B2GEntityDTO;

class CrmEntityFactory
{
    public static function make(string $type, array $data)
    {
        return match ($type) {
            'B2B' => B2BEntityDTO::validate($data),
            'B2C' => B2CEntityDTO::validate($data),
            'B2G' => B2GEntityDTO::validate($data),
            default => throw new \Exception('Tipo de entidad inválido')
        };
    }
}
