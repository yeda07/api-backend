<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => 'accounts.read', 'module' => 'accounts', 'action' => 'read', 'description' => 'Ver cuentas'],
            ['key' => 'accounts.create', 'module' => 'accounts', 'action' => 'create', 'description' => 'Crear cuentas'],
            ['key' => 'accounts.update', 'module' => 'accounts', 'action' => 'update', 'description' => 'Actualizar cuentas'],
            ['key' => 'accounts.delete', 'module' => 'accounts', 'action' => 'delete', 'description' => 'Eliminar cuentas'],
            ['key' => 'contacts.read', 'module' => 'contacts', 'action' => 'read', 'description' => 'Ver contactos'],
            ['key' => 'contacts.create', 'module' => 'contacts', 'action' => 'create', 'description' => 'Crear contactos'],
            ['key' => 'contacts.update', 'module' => 'contacts', 'action' => 'update', 'description' => 'Actualizar contactos'],
            ['key' => 'contacts.delete', 'module' => 'contacts', 'action' => 'delete', 'description' => 'Eliminar contactos'],
            ['key' => 'relations.read', 'module' => 'relations', 'action' => 'read', 'description' => 'Ver relaciones'],
            ['key' => 'relations.create', 'module' => 'relations', 'action' => 'create', 'description' => 'Crear relaciones'],
            ['key' => 'relations.delete', 'module' => 'relations', 'action' => 'delete', 'description' => 'Eliminar relaciones'],
            ['key' => 'crm-entities.read', 'module' => 'crm-entities', 'action' => 'read', 'description' => 'Ver entidades CRM'],
            ['key' => 'crm-entities.create', 'module' => 'crm-entities', 'action' => 'create', 'description' => 'Crear entidades CRM'],
            ['key' => 'crm-entities.update', 'module' => 'crm-entities', 'action' => 'update', 'description' => 'Actualizar entidades CRM'],
            ['key' => 'tags.manage', 'module' => 'tags', 'action' => 'manage', 'description' => 'Administrar etiquetas'],
            ['key' => 'search.use', 'module' => 'search', 'action' => 'use', 'description' => 'Usar busqueda dinamica'],
            ['key' => 'dashboard.read', 'module' => 'dashboard', 'action' => 'read', 'description' => 'Ver dashboard analitico'],
            ['key' => 'tasks.read', 'module' => 'tasks', 'action' => 'read', 'description' => 'Ver tareas'],
            ['key' => 'tasks.create', 'module' => 'tasks', 'action' => 'create', 'description' => 'Crear tareas'],
            ['key' => 'tasks.update', 'module' => 'tasks', 'action' => 'update', 'description' => 'Actualizar tareas'],
            ['key' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete', 'description' => 'Eliminar tareas'],
            ['key' => 'interactions.read', 'module' => 'interactions', 'action' => 'read', 'description' => 'Ver timeline de interacciones'],
            ['key' => 'interactions.create', 'module' => 'interactions', 'action' => 'create', 'description' => 'Registrar interacciones'],
            ['key' => 'activities.read', 'module' => 'activities', 'action' => 'read', 'description' => 'Ver actividades'],
            ['key' => 'activities.create', 'module' => 'activities', 'action' => 'create', 'description' => 'Crear actividades'],
            ['key' => 'activities.update', 'module' => 'activities', 'action' => 'update', 'description' => 'Actualizar actividades'],
            ['key' => 'activities.delete', 'module' => 'activities', 'action' => 'delete', 'description' => 'Eliminar actividades'],
            ['key' => 'documents.read', 'module' => 'documents', 'action' => 'read', 'description' => 'Ver documentos'],
            ['key' => 'documents.create', 'module' => 'documents', 'action' => 'create', 'description' => 'Subir documentos'],
            ['key' => 'inventory.read', 'module' => 'inventory', 'action' => 'read', 'description' => 'Ver inventario comercial'],
            ['key' => 'inventory.manage', 'module' => 'inventory', 'action' => 'manage', 'description' => 'Administrar catalogo y stock'],
            ['key' => 'inventory.reserve', 'module' => 'inventory', 'action' => 'reserve', 'description' => 'Reservar stock'],
            ['key' => 'inventory.report', 'module' => 'inventory', 'action' => 'report', 'description' => 'Ver reportes de inventario'],
            ['key' => 'quotations.read', 'module' => 'quotations', 'action' => 'read', 'description' => 'Ver cotizaciones'],
            ['key' => 'quotations.create', 'module' => 'quotations', 'action' => 'create', 'description' => 'Crear cotizaciones'],
            ['key' => 'quotations.update', 'module' => 'quotations', 'action' => 'update', 'description' => 'Actualizar cotizaciones'],
            ['key' => 'price-books.read', 'module' => 'price-books', 'action' => 'read', 'description' => 'Ver listas de precios'],
            ['key' => 'price-books.manage', 'module' => 'price-books', 'action' => 'manage', 'description' => 'Administrar listas de precios'],
            ['key' => 'commissions.read', 'module' => 'commissions', 'action' => 'read', 'description' => 'Ver comisiones'],
            ['key' => 'commissions.manage', 'module' => 'commissions', 'action' => 'manage', 'description' => 'Administrar comisiones'],
            ['key' => 'opportunities.read', 'module' => 'opportunities', 'action' => 'read', 'description' => 'Ver oportunidades'],
            ['key' => 'opportunities.manage', 'module' => 'opportunities', 'action' => 'manage', 'description' => 'Administrar pipeline y oportunidades'],
            ['key' => 'finance.read', 'module' => 'finance', 'action' => 'read', 'description' => 'Ver finanzas operativas'],
            ['key' => 'finance.manage', 'module' => 'finance', 'action' => 'manage', 'description' => 'Sincronizar finanzas operativas'],
            ['key' => 'custom-fields.manage', 'module' => 'custom-fields', 'action' => 'manage', 'description' => 'Administrar campos personalizados'],
            ['key' => 'logs.read', 'module' => 'logs', 'action' => 'read', 'description' => 'Ver logs del tenant'],
            ['key' => 'metrics.read', 'module' => 'metrics', 'action' => 'read', 'description' => 'Ver metricas del tenant'],
            ['key' => 'plans.manage', 'module' => 'plans', 'action' => 'manage', 'description' => 'Administrar planes'],
            ['key' => 'users.manage', 'module' => 'users', 'action' => 'manage', 'description' => 'Administrar usuarios'],
            ['key' => 'expenses.read', 'module' => 'expenses', 'action' => 'read', 'description' => 'Ver gastos y categorias'],
            ['key' => 'expenses.manage', 'module' => 'expenses', 'action' => 'manage', 'description' => 'Administrar gastos, categorias y proveedores'],
            ['key' => 'expenses.report', 'module' => 'expenses', 'action' => 'report', 'description' => 'Ver reportes de gastos'],
            ['key' => 'purchases.read', 'module' => 'purchases', 'action' => 'read', 'description' => 'Ver ordenes de compra'],
            ['key' => 'purchases.manage', 'module' => 'purchases', 'action' => 'manage', 'description' => 'Administrar ordenes de compra'],
            ['key' => 'competitive-intelligence.read', 'module' => 'competitive-intelligence', 'action' => 'read', 'description' => 'Ver inteligencia competitiva'],
            ['key' => 'competitive-intelligence.manage', 'module' => 'competitive-intelligence', 'action' => 'manage', 'description' => 'Administrar competidores, battlecards y lost reasons'],
            ['key' => 'competitive-intelligence.report', 'module' => 'competitive-intelligence', 'action' => 'report', 'description' => 'Ver analitica de inteligencia competitiva'],
        ];

        foreach ($permissions as $permission) {
            $model = Permission::query()->firstOrNew(['key' => $permission['key']]);
            $model->fill($permission);

            if (empty($model->uid)) {
                $model->uid = (string) Str::uuid();
            }

            $model->save();
        }
    }
}
