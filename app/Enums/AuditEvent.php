<?php

namespace App\Enums;

enum AuditEvent: string
{
    case OrderCancelled = 'order.cancel';
    case PaymentRefunded = 'payment.refund';
    case ProductPriceChanged = 'product.price_changed';
    case UserRoleChanged = 'user.role_changed';
    case RolePermissionsChanged = 'role.permissions_changed';
    case PrinterChanged = 'printer.changed';
    case PrintRouteChanged = 'print_route.changed';
    case StoreChanged = 'store.changed';
    case SubscriptionChanged = 'subscription.changed';
}
