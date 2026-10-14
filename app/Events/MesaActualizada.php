<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Avisa por Reverb que algo cambió en la mesa. No lleva datos: cada
 * jugador pide su propia vista, así nunca viajan las cartas del rival.
 */
class MesaActualizada implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $codigo) {}

    public function broadcastOn(): Channel
    {
        return new Channel("sala.{$this->codigo}");
    }

    public function broadcastAs(): string
    {
        return 'mesa.actualizada';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
