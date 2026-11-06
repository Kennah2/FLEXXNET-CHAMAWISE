<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Thrown when a chama tries to collect via STK Push but has no enabled, fully-configured
 * PaymentGatewayCredential. Always caught and shown as a friendly "pay manually instead"
 * message — never allowed to bubble up as a generic 500.
 */
class DarajaNotConfiguredException extends RuntimeException {}
