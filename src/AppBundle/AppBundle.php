<?php

namespace AppBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class AppBundle
 * @package AppBundle
 */
class AppBundle extends Bundle
{
    const STRIPE_PUBLIC_KEY = 'stripe_public_key';
    const STRIPE_SECRET_KEY = 'stripe_secret_key';
}
