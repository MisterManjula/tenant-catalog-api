<?php

use function Pest\Laravel\get;

it('responds on the health check endpoint', function () {
    get('/up')->assertOk();
});
