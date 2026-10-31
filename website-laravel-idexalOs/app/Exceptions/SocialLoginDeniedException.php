<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a social sign-in cannot be completed — for example when the
 * provider is not allowed to create a new account because registration is
 * switched off. The message is safe to show to the visitor.
 */
class SocialLoginDeniedException extends RuntimeException {}
