<?php

namespace App\Truco;

use DomainException;

/** Un jugador intentó algo que las reglas no permiten en este momento. */
final class AccionInvalida extends DomainException {}
