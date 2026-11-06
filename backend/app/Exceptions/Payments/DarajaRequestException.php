<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Thrown when a call to Safaricom's Daraja API itself fails or is rejected — a transport error,
 * a non-2xx HTTP response, or a non-zero ResponseCode at STK Push initiation time. Distinct from
 * a later callback reporting the customer cancelled or the request timed out; this exception
 * means the request never successfully reached "awaiting the customer's PIN" in the first place.
 */
class DarajaRequestException extends RuntimeException {}
