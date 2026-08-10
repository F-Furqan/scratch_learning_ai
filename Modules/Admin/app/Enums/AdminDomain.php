<?php

namespace Modules\Admin\Enums;

enum AdminDomain: string
{
    case Catalog = 'catalog';
    case Editorial = 'editorial';
    case Learning = 'learning';
    case Commerce = 'commerce';
    case Community = 'community';
    case Growth = 'growth';
    case Operations = 'operations';
}
