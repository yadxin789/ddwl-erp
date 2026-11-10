<?php

namespace App\Http\Controllers;

use App\Models\Event\DomainEvent;
use App\Models\Event\IntegrationOutbox;

class EventController extends Controller
{
    public function outbox()
    {
        $rows = IntegrationOutbox::orderByDesc('id')->paginate(20);

        return view('event.outbox', compact('rows'));
    }

    public function domainEvents()
    {
        $rows = DomainEvent::orderByDesc('id')->paginate(20);

        return view('event.domain-events', compact('rows'));
    }
}
