<?php

namespace App\Http\Controllers;

use App\Actions\PublishCatalog;
use App\Http\Resources\PublicationResource;
use App\Models\Publication;

class PublicationController extends Controller
{
    public function store(PublishCatalog $publish): PublicationResource
    {
        return new PublicationResource($publish->handle()->load('items'));
    }

    public function show(Publication $publication): PublicationResource
    {
        return new PublicationResource($publication->load(['items', 'deliveries']));
    }
}
