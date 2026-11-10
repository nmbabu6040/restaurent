<?php

namespace App\Exceptions;

use RuntimeException;

/** Something the counter cannot do right now. The message is safe to show to staff. */
class PosException extends RuntimeException {}
