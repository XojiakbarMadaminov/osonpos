<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'Owner';
    case Manager = 'Manager';
    case Cashier = 'Cashier';
    case Waiter = 'Waiter';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Ega',
            self::Manager => 'Boshqaruvchi',
            self::Cashier => 'Kassir',
            self::Waiter => 'Ofitsiant',
        };
    }

    public function permissions(): array
    {
        return match ($this) {
            self::Owner => OrganizationPermission::cases(),
            self::Manager => array_values(array_filter(
                OrganizationPermission::cases(),
                fn (OrganizationPermission $permission): bool => ! in_array($permission, [
                    OrganizationPermission::RolesManage,
                    OrganizationPermission::PaymentsRefund,
                    OrganizationPermission::ExpensesView,
                    OrganizationPermission::ExpensesManage,
                ], true),
            )),
            self::Cashier => [
                OrganizationPermission::PosAccess,
                OrganizationPermission::OrdersView,
                OrganizationPermission::OrdersCreate,
                OrganizationPermission::OrdersUpdate,
                OrganizationPermission::PaymentsView,
                OrganizationPermission::PaymentsCreate,
                OrganizationPermission::TablesView,
                OrganizationPermission::ShiftsView,
                OrganizationPermission::ShiftsManage,
            ],
            self::Waiter => [
                OrganizationPermission::PosAccess,
                OrganizationPermission::OrdersView,
                OrganizationPermission::OrdersCreate,
                OrganizationPermission::OrdersUpdate,
                OrganizationPermission::TablesView,
            ],
        };
    }
}
