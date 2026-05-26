<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CommissionPlan;
use App\Models\InventoryProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantOptionService
{
    public function paymentMethods(): array
    {
        return $this->staticOptions([
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'credit_card' => 'Tarjeta de credito',
            'debit_card' => 'Tarjeta debito',
            'check' => 'Cheque',
        ]);
    }

    public function leadOrigins(): array
    {
        return $this->staticOptions([
            'website' => 'Sitio web',
            'email' => 'Email',
            'linkedin' => 'LinkedIn',
            'whatsapp' => 'WhatsApp',
            'phone' => 'Llamada telefonica',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'google_ads' => 'Google Ads',
            'social_media' => 'Redes sociales',
            'referral' => 'Referido',
            'campaign' => 'Campana',
            'partner' => 'Partner',
            'event' => 'Evento',
            'outbound' => 'Prospeccion',
            'inbound' => 'Inbound',
            'walk_in' => 'Visita presencial',
            'marketplace' => 'Marketplace',
            'other' => 'Otro',
        ]);
    }

    public function institutionTypes(): array
    {
        return $this->staticOptions([
            'government' => 'Gobierno',
            'education' => 'Educacion',
            'healthcare' => 'Salud',
            'public_company' => 'Empresa publica',
            'ngo' => 'ONG',
        ]);
    }

    public function companySizes(): array
    {
        return $this->staticOptions([
            'micro' => 'Micro',
            'small' => 'Pequena',
            'medium' => 'Mediana',
            'large' => 'Grande',
        ]);
    }

    public function industries(): array
    {
        $defaults = collect([
            'technology' => 'Tecnologia',
            'finance' => 'Finanzas',
            'retail' => 'Retail',
            'manufacturing' => 'Manufactura',
            'government' => 'Gobierno',
        ]);

        $tenantIndustries = Account::query()
            ->whereNotNull('industry')
            ->pluck('industry')
            ->filter()
            ->mapWithKeys(fn (string $industry) => [Str::slug($industry) => $industry]);

        return $this->staticOptions($defaults->merge($tenantIndustries)->all());
    }

    public function opportunityProducts(): array
    {
        $catalog = $this->catalogProductOptions();
        $inventory = $this->inventoryProductOptions();

        return $catalog->merge($inventory)->unique('uid')->values()->all();
    }

    public function lostReasonCategories(): array
    {
        return $this->staticOptions([
            'price' => 'Precio',
            'features' => 'Producto',
            'relationship' => 'Relacion',
            'timing' => 'Timing',
            'implementation' => 'Servicio',
            'other' => 'Otro',
        ]);
    }

    public function activityTypes(): array
    {
        return $this->staticOptions([
            'task' => 'Tarea',
            'call' => 'Llamada',
            'meeting' => 'Reunion',
            'email' => 'Email',
            'note' => 'Nota',
            'reminder' => 'Recordatorio',
        ]);
    }

    public function commissionPlanTypes(): array
    {
        $defaults = collect([
            'sale' => 'Venta',
            'margin' => 'Margen',
            'target' => 'Meta',
        ]);

        $tenantTypes = CommissionPlan::query()
            ->pluck('type')
            ->filter()
            ->mapWithKeys(fn (string $type) => [$type => ucfirst($type)]);

        return $this->staticOptions($defaults->merge($tenantTypes)->all());
    }

    private function staticOptions(array $options): array
    {
        return collect($options)
            ->map(fn (string $name, string $key) => [
                'uid' => $this->stableUid($key),
                'name' => $name,
                'key' => $key,
            ])
            ->values()
            ->all();
    }

    private function catalogProductOptions(): Collection
    {
        if (!Schema::hasTable('products')) {
            return collect();
        }

        return Product::query()
            ->when(Schema::hasColumn('products', 'status'), fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get()
            ->toBase()
            ->map(fn (Product $product) => [
                'uid' => $product->uid,
                'name' => $product->name,
                'key' => $product->sku,
            ]);
    }

    private function inventoryProductOptions(): Collection
    {
        if (!Schema::hasTable('inventory_products')) {
            return collect();
        }

        return InventoryProduct::query()
            ->when(Schema::hasColumn('inventory_products', 'is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->toBase()
            ->map(fn (InventoryProduct $product) => [
                'uid' => $product->uid,
                'name' => $product->name,
                'key' => $product->sku,
            ]);
    }

    private function stableUid(string $key): string
    {
        $hash = md5($key);

        return substr($hash, 0, 8)
            . '-' . substr($hash, 8, 4)
            . '-' . substr($hash, 12, 4)
            . '-' . substr($hash, 16, 4)
            . '-' . substr($hash, 20, 12);
    }
}
