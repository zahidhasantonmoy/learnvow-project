<?php

namespace App\Exceptions;

use Exception;

/** Thrown by AiService when AI is not configured (Phase 4 spec §7.1). */
class AiNotConfiguredException extends Exception {}
