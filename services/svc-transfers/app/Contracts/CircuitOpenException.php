<?php

namespace App\Contracts;

/**
 * The circuit toward the interbank network is open (too many recent
 * failures): it's rejected without even attempting the external call.
 */
class CircuitOpenException extends InterbankException {}
