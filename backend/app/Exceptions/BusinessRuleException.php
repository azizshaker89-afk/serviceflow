<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request is valid input but breaks a business rule
 * (illegal status transition, insufficient stock, paying a cancelled invoice...).
 * Rendered as 422 with the message shown to the user as-is.
 */
class BusinessRuleException extends RuntimeException {}
