<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Battlecard;
use App\Models\Contact;
use App\Models\Competitor;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Payment;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;

class TenantDemoDataService
{
    public function __construct(private readonly OpportunityStageProvisioner $stageProvisioner)
    {
    }

    public function seed(Tenant $tenant, ?User $owner = null): array
    {
        $owner ??= User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->first();

        if (! $owner) {
            throw new \RuntimeException('El tenant no tiene usuarios para asignar los datos demo.');
        }

        $this->stageProvisioner->provision($tenant);

        $accounts = $this->seedAccounts($tenant, $owner);
        $contacts = $this->seedContacts($tenant, $owner, $accounts);
        $opportunities = $this->seedOpportunities($tenant, $owner, $accounts);
        $inventory = $this->seedInventoryAndCatalog($tenant);
        $quotations = $this->seedQuotationsAndInvoices($tenant, $owner, $accounts, $opportunities, $inventory);
        $competitive = $this->seedCompetitiveIntelligence($tenant, $owner, $accounts, $opportunities);
        $tasks = $this->seedTasks($tenant, $owner, $accounts, $contacts);
        $activities = $this->seedActivities($tenant, $owner, $accounts, $contacts);

        return [
            'tenant_uid' => $tenant->uid,
            'owner_email' => $owner->email,
            'accounts' => count($accounts),
            'contacts' => count($contacts),
            'opportunities' => count($opportunities),
            'inventory_products' => count($inventory['inventory_products']),
            'catalog_products' => count($inventory['catalog_products']),
            'quotations' => count($quotations['quotations']),
            'invoices' => count($quotations['invoices']),
            'competitors' => count($competitive['competitors']),
            'battlecards' => count($competitive['battlecards']),
            'lost_reasons' => count($competitive['lost_reasons']),
            'tasks' => count($tasks),
            'activities' => count($activities),
        ];
    }

    private function seedAccounts(Tenant $tenant, User $owner): array
    {
        $rows = [
            [
                'document' => '900100001',
                'name' => 'Andina Tech S.A.S.',
                'email' => 'compras@andinatech.co',
                'industry' => 'Tecnologia',
                'website' => 'https://andinatech.co',
                'phone' => '+57 300 111 0001',
                'address' => 'Bogota',
            ],
            [
                'document' => '900100002',
                'name' => 'Clinica Norte',
                'email' => 'admin@clinicanorte.co',
                'industry' => 'Salud',
                'website' => 'https://clinicanorte.co',
                'phone' => '+57 300 222 0002',
                'address' => 'Medellin',
            ],
            [
                'document' => '900100003',
                'name' => 'Municipio San Rafael',
                'email' => 'contratacion@sanrafael.gov.co',
                'industry' => 'Gobierno',
                'website' => 'https://sanrafael.gov.co',
                'phone' => '+57 300 333 0003',
                'address' => 'San Rafael',
            ],
        ];

        return collect($rows)
            ->map(fn (array $row) => Account::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'document' => $row['document']],
                $row + [
                    'tenant_id' => $tenant->getKey(),
                    'owner_user_id' => $owner->getKey(),
                    'status' => 'active',
                ]
            ))
            ->all();
    }

    private function seedContacts(Tenant $tenant, User $owner, array $accounts): array
    {
        $rows = [
            [
                'account' => $accounts[0],
                'first_name' => 'Laura',
                'last_name' => 'Rojas',
                'email' => 'laura.rojas@andinatech.co',
                'phone' => '+57 301 111 0001',
                'position' => 'Directora Comercial',
                'is_public_entity' => false,
            ],
            [
                'account' => $accounts[1],
                'first_name' => 'Camilo',
                'last_name' => 'Perez',
                'email' => 'camilo.perez@clinicanorte.co',
                'phone' => '+57 301 222 0002',
                'position' => 'Jefe de Operaciones',
                'is_public_entity' => false,
            ],
            [
                'account' => $accounts[2],
                'first_name' => 'Secretaria',
                'last_name' => 'Planeacion',
                'email' => 'planeacion@sanrafael.gov.co',
                'phone' => '+57 301 333 0003',
                'position' => 'Entidad publica',
                'is_public_entity' => true,
            ],
        ];

        return collect($rows)
            ->map(fn (array $row) => Contact::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'email' => $row['email']],
            [
                'tenant_id' => $tenant->getKey(),
                'owner_user_id' => $owner->getKey(),
                    'account_id' => $row['account']->getKey(),
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'position' => $row['position'],
                    'status' => 'active',
                    'is_public_entity' => $row['is_public_entity'],
                ]
            ))
            ->all();
    }

    private function seedOpportunities(Tenant $tenant, User $owner, array $accounts): array
    {
        $stages = OpportunityStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->get()
            ->keyBy('key');

        $rows = [
            [
                'title' => 'Implementacion CRM Andina',
                'account' => $accounts[0],
                'stage' => 'leads',
                'amount' => 12000000,
                'lead_origin' => 'linkedin',
            ],
            [
                'title' => 'Renovacion soporte Clinica Norte',
                'account' => $accounts[1],
                'stage' => 'contactado',
                'amount' => 8500000,
                'lead_origin' => 'email',
            ],
            [
                'title' => 'Portal ciudadano San Rafael',
                'account' => $accounts[2],
                'stage' => 'negociacion',
                'amount' => 23500000,
                'lead_origin' => 'referido',
            ],
            [
                'title' => 'Expansion licencias Andina',
                'account' => $accounts[0],
                'stage' => 'cerrador',
                'amount' => 18000000,
                'lead_origin' => 'web',
                'won_at' => now()->subDays(1),
            ],
        ];

        return collect($rows)
            ->map(fn (array $row) => Opportunity::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'title' => $row['title']],
                [
                    'tenant_id' => $tenant->getKey(),
                    'owner_user_id' => $owner->getKey(),
                    'stage_id' => $stages[$row['stage']]->getKey(),
                    'opportunityable_type' => Account::class,
                    'opportunityable_id' => $row['account']->getKey(),
                    'email' => $row['account']->email,
                    'lead_origin' => $row['lead_origin'],
                    'amount' => $row['amount'],
                    'currency' => 'COP',
                    'expected_close_date' => now()->addDays(20)->toDateString(),
                    'description' => 'Dato demo creado para validar el flujo comercial.',
                    'won_at' => $row['won_at'] ?? null,
                    'lost_at' => null,
                ]
            ))
            ->all();
    }

    private function seedInventoryAndCatalog(Tenant $tenant): array
    {
        $category = InventoryCategory::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'key' => 'demo-tecnologia'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Tecnologia Demo',
                'description' => 'Categoria demo para productos de venta.',
            ]
        );

        $warehouseMain = Warehouse::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'code' => 'DEMO-BOG'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Bodega Demo Bogota',
                'location' => 'Bogota',
                'is_active' => true,
            ]
        );

        $warehouseBackup = Warehouse::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'code' => 'DEMO-MED'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Bodega Demo Medellin',
                'location' => 'Medellin',
                'is_active' => true,
            ]
        );

        $inventoryRows = [
            [
                'sku' => 'DEMO-LIC-001',
                'name' => 'Licencia CRM Profesional',
                'description' => 'Licencia anual para equipo comercial.',
                'cost_price' => 120000,
                'sale_price' => 240000,
                'discount_percent' => 5,
                'reorder_point' => 10,
                'stock_main' => 80,
                'stock_backup' => 30,
            ],
            [
                'sku' => 'DEMO-IMP-001',
                'name' => 'Kit Implementacion',
                'description' => 'Paquete fisico de instalacion y soporte inicial.',
                'cost_price' => 350000,
                'sale_price' => 650000,
                'discount_percent' => 3,
                'reorder_point' => 5,
                'stock_main' => 20,
                'stock_backup' => 8,
            ],
        ];

        $inventoryProducts = collect($inventoryRows)
            ->map(function (array $row) use ($tenant, $category, $warehouseMain, $warehouseBackup) {
                $product = InventoryProduct::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'sku' => $row['sku']],
                    [
                        'tenant_id' => $tenant->getKey(),
                        'category_id' => $category->getKey(),
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'cost_price' => $row['cost_price'],
                        'sale_price' => $row['sale_price'],
                        'discount_percent' => $row['discount_percent'],
                        'reorder_point' => $row['reorder_point'],
                        'is_active' => true,
                    ]
                );

                foreach ([[$warehouseMain, $row['stock_main']], [$warehouseBackup, $row['stock_backup']]] as [$warehouse, $stock]) {
                    InventoryStock::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->getKey(),
                            'product_id' => $product->getKey(),
                            'warehouse_id' => $warehouse->getKey(),
                        ],
                        [
                            'tenant_id' => $tenant->getKey(),
                            'physical_stock' => $stock,
                            'reserved_stock' => 0,
                        ]
                    );
                }

                return $product;
            })
            ->all();

        $catalogProducts = collect($inventoryProducts)
            ->map(fn (InventoryProduct $inventoryProduct) => Product::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'sku' => $inventoryProduct->sku],
                [
                    'tenant_id' => $tenant->getKey(),
                    'inventory_product_id' => $inventoryProduct->getKey(),
                    'name' => $inventoryProduct->name,
                    'type' => 'product',
                    'description' => $inventoryProduct->description,
                    'status' => 'active',
                    'default_price' => $inventoryProduct->sale_price,
                    'default_discount_percent' => $inventoryProduct->discount_percent,
                ]
            ))
            ->all();

        $service = Product::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'sku' => 'DEMO-SRV-001'],
            [
                'tenant_id' => $tenant->getKey(),
                'inventory_product_id' => null,
                'name' => 'Consultoria Comercial',
                'type' => 'service',
                'description' => 'Servicio demo sin reserva de stock.',
                'status' => 'active',
                'default_price' => 950000,
                'default_discount_percent' => 0,
            ]
        );

        $catalogProducts[] = $service;

        $priceBook = PriceBook::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'key' => 'demo-comercial'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Lista Comercial Demo',
                'channel' => 'B2B',
                'is_active' => true,
                'valid_from' => now()->startOfMonth()->toDateString(),
                'valid_until' => now()->addYear()->toDateString(),
            ]
        );

        foreach ($inventoryProducts as $inventoryProduct) {
            PriceBookItem::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'price_book_id' => $priceBook->getKey(),
                    'product_id' => $inventoryProduct->getKey(),
                ],
                [
                    'tenant_id' => $tenant->getKey(),
                    'unit_price' => $inventoryProduct->sale_price,
                    'currency' => 'COP',
                    'min_margin_percent' => 20,
                ]
            );
        }

        return [
            'category' => $category,
            'warehouses' => [$warehouseMain, $warehouseBackup],
            'inventory_products' => $inventoryProducts,
            'catalog_products' => $catalogProducts,
            'price_book' => $priceBook,
        ];
    }

    private function seedQuotationsAndInvoices(Tenant $tenant, User $owner, array $accounts, array $opportunities, array $inventory): array
    {
        $quoteable = $opportunities[0] ?? $accounts[0];
        $priceBook = $inventory['price_book'];
        $warehouse = $inventory['warehouses'][0];
        $catalogProducts = collect($inventory['catalog_products'])->keyBy('sku');
        $inventoryProducts = collect($inventory['inventory_products'])->keyBy('sku');

        $quotation = Quotation::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'quote_number' => 'COT-DEMO-1001'],
            [
                'tenant_id' => $tenant->getKey(),
                'owner_user_id' => $owner->getKey(),
                'created_by_user_id' => $owner->getKey(),
                'price_book_id' => $priceBook->getKey(),
                'quoteable_type' => $quoteable::class,
                'quoteable_id' => $quoteable->getKey(),
                'title' => 'Cotizacion demo CRM integral',
                'status' => 'approved',
                'currency' => 'COP',
                'exchange_rate' => 1,
                'local_currency' => 'COP',
                'valid_until' => now()->addDays(15)->toDateString(),
                'notes' => 'Cotizacion demo con productos fisicos y servicio.',
            ]
        );

        $itemRows = [
            ['sku' => 'DEMO-LIC-001', 'quantity' => 5],
            ['sku' => 'DEMO-IMP-001', 'quantity' => 1],
            ['sku' => 'DEMO-SRV-001', 'quantity' => 1],
        ];

        foreach ($itemRows as $row) {
            $catalogProduct = $catalogProducts[$row['sku']];
            $inventoryProduct = $inventoryProducts[$row['sku']] ?? null;
            $listPrice = (float) $catalogProduct->default_price;
            $discountPercent = (float) $catalogProduct->default_discount_percent;
            $discountAmount = round($listPrice * ($discountPercent / 100), 2);
            $netUnitPrice = round($listPrice - $discountAmount, 2);
            $unitCost = $inventoryProduct ? (float) $inventoryProduct->cost_price : 0;

            QuotationItem::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'quotation_id' => $quotation->getKey(),
                    'sku' => $row['sku'],
                ],
                [
                    'tenant_id' => $tenant->getKey(),
                    'product_id' => $inventoryProduct?->getKey(),
                    'catalog_product_id' => $catalogProduct->getKey(),
                    'warehouse_id' => $inventoryProduct ? $warehouse->getKey() : null,
                    'description' => $catalogProduct->name,
                    'quantity' => $row['quantity'],
                    'list_unit_price' => $listPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'net_unit_price' => $netUnitPrice,
                    'unit_cost' => $unitCost,
                    'unit_price' => $netUnitPrice,
                    'margin_amount' => round($netUnitPrice - $unitCost, 2),
                    'margin_percent' => $netUnitPrice > 0 ? round((($netUnitPrice - $unitCost) / $netUnitPrice) * 100, 2) : 0,
                    'min_margin_percent' => $inventoryProduct ? 20 : 0,
                    'below_min_margin' => false,
                ]
            );
        }

        $quotation->refresh();
        $subtotal = $quotation->subtotal;
        $discountTotal = $quotation->discount_total;
        $total = $quotation->total;

        $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'invoice_number' => 'FAC-DEMO-1001'],
            [
                'tenant_id' => $tenant->getKey(),
                'quotation_id' => $quotation->getKey(),
                'invoiceable_type' => $quoteable::class,
                'invoiceable_id' => $quoteable->getKey(),
                'status' => 'partial',
                'quote_currency' => 'COP',
                'exchange_rate' => 1,
                'currency' => 'COP',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_total' => round($total * 0.4, 2),
                'outstanding_total' => round($total * 0.6, 2),
                'issued_at' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(12)->toDateString(),
                'meta' => ['demo' => true],
            ]
        );

        Payment::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'external_reference' => 'PAGO-DEMO-1001'],
            [
                'tenant_id' => $tenant->getKey(),
                'invoice_id' => $invoice->getKey(),
                'amount' => round($total * 0.4, 2),
                'payment_date' => now()->subDay()->toDateString(),
                'method' => 'transfer',
                'meta' => ['demo' => true],
            ]
        );

        return [
            'quotations' => [$quotation],
            'invoices' => [$invoice],
        ];
    }

    private function seedCompetitiveIntelligence(Tenant $tenant, User $owner, array $accounts, array $opportunities): array
    {
        $competitors = [
            Competitor::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'key' => 'competidor-alpha'],
                [
                    'tenant_id' => $tenant->getKey(),
                    'name' => 'Competidor Alpha',
                    'website' => 'https://alpha.example.com',
                    'strengths' => ['Precio agresivo', 'Marca posicionada'],
                    'weaknesses' => ['Soporte limitado', 'Implementacion lenta'],
                    'notes' => 'Competidor demo para inteligencia competitiva.',
                    'is_active' => true,
                ]
            ),
            Competitor::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'key' => 'competidor-beta'],
                [
                    'tenant_id' => $tenant->getKey(),
                    'name' => 'Competidor Beta',
                    'website' => 'https://beta.example.com',
                    'strengths' => ['Integraciones listas'],
                    'weaknesses' => ['Costo alto', 'Poca flexibilidad'],
                    'notes' => 'Segundo competidor demo.',
                    'is_active' => true,
                ]
            ),
        ];

        $battlecards = collect($competitors)
            ->map(fn (Competitor $competitor) => Battlecard::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'competitor_id' => $competitor->getKey(), 'title' => 'Como competir contra '.$competitor->name],
                [
                    'tenant_id' => $tenant->getKey(),
                    'summary' => 'Battlecard demo para preparar conversaciones comerciales.',
                    'differentiators' => ['Mejor acompanamiento', 'Implementacion mas rapida', 'Mayor trazabilidad'],
                    'objection_handlers' => [
                        ['objection' => 'Son mas baratos', 'response' => 'Comparar costo total y soporte incluido.'],
                        ['objection' => 'Ya los conocemos', 'response' => 'Mostrar casos de migracion y reduccion de friccion.'],
                    ],
                    'recommended_actions' => ['Solicitar demo tecnica', 'Invitar al area operativa', 'Documentar criterio de decision'],
                    'is_active' => true,
                ]
            ))
            ->all();

        $lostOpportunity = $opportunities[2] ?? null;
        $closedStage = OpportunityStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', 'cerrador')
            ->first();

        if ($lostOpportunity && $closedStage) {
            $lostOpportunity->forceFill([
                'stage_id' => $closedStage->getKey(),
                'lost_at' => now()->subDays(2),
                'won_at' => null,
            ])->save();
        }

        $lostReasons = [];

        if ($lostOpportunity) {
            $lostReasons[] = LostReason::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'opportunity_id' => $lostOpportunity->getKey()],
                [
                    'tenant_id' => $tenant->getKey(),
                    'competitor_id' => $competitors[0]->getKey(),
                    'owner_user_id' => $owner->getKey(),
                    'lossable_type' => Account::class,
                    'lossable_id' => $accounts[2]->getKey(),
                    'reason_type' => 'price',
                    'summary' => 'Perdida demo por precio',
                    'details' => 'El cliente eligio una propuesta mas economica, aunque con menor alcance.',
                    'lost_at' => now()->subDays(2)->toDateString(),
                    'estimated_value' => $lostOpportunity->amount,
                    'currency' => 'COP',
                    'meta' => ['demo' => true],
                ]
            );
        }

        return [
            'competitors' => $competitors,
            'battlecards' => $battlecards,
            'lost_reasons' => $lostReasons,
        ];
    }

    private function seedTasks(Tenant $tenant, User $owner, array $accounts, array $contacts): array
    {
        $rows = [
            ['title' => 'Preparar propuesta Andina', 'target' => $accounts[0], 'priority' => 'high'],
            ['title' => 'Confirmar llamada Clinica Norte', 'target' => $contacts[1], 'priority' => 'medium'],
        ];

        return collect($rows)
            ->map(fn (array $row) => Task::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'title' => $row['title']],
                [
                    'tenant_id' => $tenant->getKey(),
                    'owner_user_id' => $owner->getKey(),
                    'assigned_user_id' => $owner->getKey(),
                    'description' => 'Tarea demo para seguimiento comercial.',
                    'status' => 'pending',
                    'priority' => $row['priority'],
                    'due_date' => now()->addDays(2)->toDateString(),
                    'taskable_type' => $row['target']::class,
                    'taskable_id' => $row['target']->getKey(),
                ]
            ))
            ->all();
    }

    private function seedActivities(Tenant $tenant, User $owner, array $accounts, array $contacts): array
    {
        $rows = [
            ['title' => 'Reunion inicial Andina', 'type' => 'meeting', 'target' => $accounts[0]],
            ['title' => 'Llamada seguimiento Clinica Norte', 'type' => 'call', 'target' => $contacts[1]],
        ];

        return collect($rows)
            ->map(fn (array $row) => Activity::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'title' => $row['title']],
                [
                    'tenant_id' => $tenant->getKey(),
                    'owner_user_id' => $owner->getKey(),
                    'assigned_user_id' => $owner->getKey(),
                    'type' => $row['type'],
                    'description' => 'Actividad demo para validar agenda e historial.',
                    'status' => 'pending',
                    'scheduled_at' => now()->addDays(1),
                    'activityable_type' => $row['target']::class,
                    'activityable_id' => $row['target']->getKey(),
                ]
            ))
            ->all();
    }
}
