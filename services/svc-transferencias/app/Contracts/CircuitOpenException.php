<?php

namespace App\Contracts;

/**
 * El circuito hacia la red interbancaria esta abierto (demasiadas fallas
 * recientes): se rechaza sin siquiera intentar la llamada externa.
 */
class CircuitOpenException extends InterbankException
{
}
