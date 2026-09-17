<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a recovery attempt is refused before it runs because the incident
 * is no longer in a state that permits it. The message is safe to show to the
 * operator; it never carries internal exception detail.
 */
class RecoveryNotRetryableException extends RuntimeException {}
