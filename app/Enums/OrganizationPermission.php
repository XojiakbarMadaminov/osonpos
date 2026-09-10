<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case PosAccess = 'pos.access';
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case OrdersView = 'orders.view';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersCancel = 'orders.cancel';
    case OrdersReprint = 'orders.reprint';
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';
    case TablesView = 'tables.view';
    case TablesManage = 'tables.manage';
    case ReportsView = 'reports.view';
    case PrintersView = 'printers.view';
    case PrintersManage = 'printers.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case StoresView = 'stores.view';
    case StoresManage = 'stores.manage';
    case ShiftsView = 'shifts.view';
    case ShiftsManage = 'shifts.manage';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
