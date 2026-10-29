<?php

namespace App\Exceptions;

use RuntimeException;

/** A delivery action the current state does not allow. The message is safe to show to staff and riders. */
class DeliveryException extends RuntimeException {}
